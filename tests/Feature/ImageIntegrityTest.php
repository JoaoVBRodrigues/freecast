<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\MapImportPreview;
use App\Models\User;
use App\Services\Dd2vttParser;
use App\Services\ImportImageDecoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImageIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function brokenPngFixture(): array
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/dd2vtt/minimo.dd2vtt')), true);
        $bytes = base64_decode($fixture['image']);
        $png = substr($bytes, 0, 8);
        $offset = 8;
        while ($offset < strlen($bytes)) {
            $length = unpack('N', substr($bytes, $offset, 4))[1];
            $type = substr($bytes, $offset + 4, 4);
            $data = substr($bytes, $offset + 8, $length);
            if ($type === 'IDAT') {
                // Correct PNG chunk/CRC, deliberately invalid zlib pixel stream.
                $data = str_repeat('X', $length);
                $this->assertFalse(@gzuncompress($data));
            }
            $png .= pack('N', strlen($data)).$type.$data.pack('H*', hash('crc32b', $type.$data));
            $offset += $length + 12;
        }
        $fixture['image'] = base64_encode($png);

        return $fixture;
    }

    private function room(): Room
    {
        $owner = User::factory()->create();
        $room = Room::create(['owner_id' => $owner->id, 'title' => 'Image integrity reproduction', 'system' => 'Synthetic']);
        $room->memberships()->create(['user_id' => $owner->id, 'role' => 'owner']);
        $this->actingAs($owner);

        return $room;
    }

    public function test_http_preview_rejects_crc_valid_png_with_invalid_pixels_before_any_write(): void
    {
        Storage::fake('local');
        $room = $this->room();
        $fixture = $this->brokenPngFixture();
        $this->postJson(route('maps.import.preview', $room), [
            'file' => UploadedFile::fake()->createWithContent('broken.dd2vtt', json_encode($fixture)),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('map_import_previews', 0);
        $this->assertDatabaseCount('room_maps', 0);
        $this->assertDatabaseCount('room_assets', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_direct_http_confirmation_rechecks_legacy_preview_pixels_and_keeps_storage_unchanged(): void
    {
        Storage::fake('local');
        $room = $this->room();
        // Simulate a preview accepted by the older container-only validator.
        $valid = app(Dd2vttParser::class)->parse(file_get_contents(base_path('tests/Fixtures/dd2vtt/minimo.dd2vtt')));
        unset($valid['image']);
        $bytes = base64_decode($this->brokenPngFixture()['image']);
        $path = 'imports/staging/legacy/image';
        Storage::disk('local')->put($path, $bytes);
        $preview = MapImportPreview::create(['id' => (string) Str::uuid(), 'room_id' => $room->id, 'user_id' => $room->owner_id, 'title' => 'Legacy invalid pixels', 'image_path' => $path, 'image_hash' => hash('sha256', $bytes), 'validated' => $valid, 'expires_at' => now()->addMinutes(30)]);
        // No browser image load occurs in this request.
        $this->postJson(route('maps.import.confirm', [$room, $preview->id]))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('room_maps', 0);
        $this->assertDatabaseCount('room_assets', 0);
        $this->assertDatabaseCount('room_command_receipts', 0);
        $this->assertNull($preview->fresh()->confirmed_at);
        $this->assertSame([$path], Storage::disk('local')->allFiles());
    }

    public function test_missing_gd_fails_closed_before_staging(): void
    {
        Storage::fake('local');
        $this->app->instance(ImportImageDecoder::class, new class extends ImportImageDecoder {
            protected function available(): bool { return false; }
        });
        $room = $this->room();
        $this->postJson(route('maps.import.preview', $room), [
            'file' => UploadedFile::fake()->createWithContent('valid.dd2vtt', file_get_contents(base_path('tests/Fixtures/dd2vtt/minimo.dd2vtt'))),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('map_import_previews', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_webp_pixels_are_decoded_and_corrupt_payload_is_rejected(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/dd2vtt/minimo.dd2vtt')), true);
        $bitmap = imagecreatefromstring(base64_decode($fixture['image']));
        ob_start();
        imagewebp($bitmap, null, 80);
        $webp = ob_get_clean();
        imagedestroy($bitmap);
        $fixture['image'] = base64_encode($webp);
        $this->assertSame('image/webp', app(Dd2vttParser::class)->parse(json_encode($fixture))['mime']);
        $this->assertSame('VP8 ', substr($webp, 12, 4));
        // Retain RIFF/chunk lengths and the frame dimensions, corrupt compressed pixels.
        $webp = substr($webp, 0, 30).str_repeat('X', strlen($webp) - 30);
        $this->assertSame(200, getimagesizefromstring($webp)[0]);
        Storage::fake('local');
        $room = $this->room();
        $fixture['image'] = base64_encode($webp);
        $this->postJson(route('maps.import.preview', $room), [
            'file' => UploadedFile::fake()->createWithContent('broken-webp.dd2vtt', json_encode($fixture)),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('map_import_previews', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_unavailable_decoder_process_fails_closed_without_fallback(): void
    {
        Storage::fake('local');
        $room = $this->room();
        $previous = getenv('PHP_BINARY');
        putenv('PHP_BINARY='.base_path('storage/app/testing/no-such-php-executable'));
        try {
            $this->postJson(route('maps.import.preview', $room), [
                'file' => UploadedFile::fake()->createWithContent('valid.dd2vtt', file_get_contents(base_path('tests/Fixtures/dd2vtt/minimo.dd2vtt'))),
            ])->assertUnprocessable()->assertJsonValidationErrors('file');
            $this->assertDatabaseCount('map_import_previews', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            putenv($previous === false ? 'PHP_BINARY' : 'PHP_BINARY='.$previous);
        }
    }
}
