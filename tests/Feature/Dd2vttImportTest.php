<?php

namespace Tests\Feature;

use App\Models\MapImportPreview;
use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\RoomMap;
use App\Models\User;
use App\Services\Dd2vttParser;
use App\Services\RoomSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Dd2vttImportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name = 'geometria'): string
    {
        return file_get_contents(base_path("tests/Fixtures/dd2vtt/$name.dd2vtt"));
    }

    private function setupRoom(): array
    {
        $owner = User::factory()->create();
        $player = User::factory()->create();
        $room = Room::create(['owner_id' => $owner->id, 'title' => 'Importação sintética', 'system' => 'Próprio', 'is_public' => true]);
        foreach ([[$owner, 'owner'], [$player, 'player']] as [$u,$role]) {
            $room->memberships()->create(['user_id' => $u->id, 'role' => $role]);
        }

        return [$owner, $player, $room];
    }

    private function preview($owner, $room, string $name = 'geometria')
    {
        return $this->actingAs($owner)->postJson(route('maps.import.preview', $room), ['file' => UploadedFile::fake()->createWithContent("$name.dd2vtt", $this->fixture($name))]);
    }

    public function test_known_coordinates_and_optional_fields_are_explicit(): void
    {
        $v = app(Dd2vttParser::class)->parse($this->fixture());
        $this->assertSame(200, $v['width']);
        $this->assertSame(50, $v['cell_size']);
        $this->assertSame([['x' => 0.0, 'y' => 50.0], ['x' => 100.0, 'y' => 50.0]], $v['data']['walls'][0]['points']);
        $this->assertSame([['x' => 100.0, 'y' => 50.0], ['x' => 100.0, 'y' => 100.0]], $v['data']['portals'][0]['bounds']);
        $this->assertFalse($v['data']['portals'][0]['closed']);
        $this->assertSame(['x' => 75.0, 'y' => 125.0], $v['data']['lights'][0]['position']);
        $this->assertSame(150.0, $v['data']['lights'][0]['range']);
        $this->assertSame('ffcc88ff', $v['data']['lights'][0]['color']);
        $d = json_decode($this->fixture('minimo'), true);
        unset($d['lights'],$d['portals'],$d['line_of_sight'],$d['resolution']['map_origin']);
        $d['environment'] = ['ambient_light' => 'ffffffff'];
        $v = app(Dd2vttParser::class)->parse(json_encode($d));
        $this->assertSame(['walls' => 0, 'portals' => 0, 'lights' => 0, 'points' => 0], $v['counts']);
        $this->assertGreaterThanOrEqual(6, count($v['warnings']));
    }

    public function test_parser_rejects_bad_json_depth_schema_versions_bounds_images_and_limits(): void
    {
        $d = json_decode($this->fixture(), true);
        $cases = ['invalid', '{"x":'.str_repeat('[', 40).'0'.str_repeat(']', 40).'}'];
        foreach ([['format', 2], ['image', 'https://example.invalid/image'], ['image', $d['image']."\n"], ['line_of_sight', null], ['portals', array_fill(0, 1001, [])], ['lights', [['position' => ['x' => 11, 'y' => 21], 'range' => -1]]], ['line_of_sight', [[['x' => 0, 'y' => 0], ['x' => 1, 'y' => 1]]]], ['line_of_sight', [array_fill(0, 257, ['x' => 10, 'y' => 20])]]] as [$k,$value]) {
            $cases[] = json_encode([...$d, $k => $value]);
        }
        foreach ([['pixels_per_grid', 0], ['pixels_per_grid', '50'], ['map_size', ['x' => 0, 'y' => 4]], ['map_size', ['x' => 1000, 'y' => 1000]]] as [$k,$value]) {
            $cases[] = json_encode([...$d, 'resolution' => [...$d['resolution'], $k => $value]]);
        }
        $cases[] = json_encode([...$d, 'image' => base64_encode('not an image')]);
        $cases[] = json_encode([...$d, 'resolution' => [...$d['resolution'], 'pixels_per_grid' => 100]]);
        $png = base64_decode($d['image']);
        $png[50] = chr(ord($png[50]) ^ 1);
        $cases[] = json_encode([...$d, 'image'=>base64_encode($png)]);
        $cases[] = json_encode([...$d, 'image'=>base64_encode(substr(base64_decode($d['image']),0,-4))]);
        $cases[] = json_encode([...$d, 'line_of_sight'=>array_fill(0,1000,array_fill(0,21,['x'=>11,'y'=>21]))]);
        $cases[] = str_replace('"range":3','"range":1e400',json_encode($d));
        $cases[] = json_encode([...$d, 'image'=>str_repeat('A',4*(int)ceil(Dd2vttParser::MAX_IMAGE/3)+4)]);
        $cases[] = str_repeat(' ', Dd2vttParser::MAX_FILE + 1);
        foreach ($cases as $i => $json) {
            try {
                app(Dd2vttParser::class)->parse($json);
                $this->fail("Invalid case $i accepted");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('file', $e->errors());
            }
        }
        $this->assertDatabaseCount('room_maps', 0);
    }

    public function test_preview_cancel_confirm_replay_deliberate_reimport_and_private_projections(): void
    {
        Storage::fake('local');
        [$owner,$player,$room] = $this->setupRoom();
        $r = $this->preview($owner, $room)->assertOk();
        $id = $r->json('preview.id');
        $this->assertDatabaseCount('room_maps', 0);
        $this->assertDatabaseCount('room_assets', 0);
        $this->actingAs($owner)->get($r->json('preview.image_url'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($player)->get($r->json('preview.image_url'))->assertForbidden();
        $this->actingAs($owner)->deleteJson(route('maps.import.cancel', [$room, $id]))->assertNoContent();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $r = $this->preview($owner, $room)->assertOk();
        $id = $r->json('preview.id');
        $a = $this->actingAs($owner)->postJson(route('maps.import.confirm', [$room, $id]))->assertOk();
        $this->actingAs($owner)->postJson(route('maps.import.confirm', [$room, $id]))->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('mapId', $a->json('mapId'));
        $this->assertDatabaseCount('room_maps', 1);
        $this->assertDatabaseCount('room_assets', 1);
        $this->assertDatabaseCount('room_events', 1);
        $map = RoomMap::firstOrFail();
        $asset = RoomAsset::firstOrFail();
        $this->assertTrue($map->fog_enabled);
        $this->assertSame([], $map->fog_revealed);
        $this->assertNull($room->fresh()->active_map_id);
        Storage::disk('local')->assertExists($asset->path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->actingAs($owner)->get(route('maps.show', [$room, $map]))->assertInertia(fn ($p) => $p->has('import_details.walls', 1)->where('import_details.lights.0.position.x', 75));
        $this->actingAs($player)->get(route('maps.show', [$room, $map]))->assertInertia(fn ($p) => $p->where('import_details', null))->assertDontSee('ffcc88ff');
        $this->actingAs($player)->getJson(route('rooms.events', $room))->assertDontSee('ffcc88ff')->assertDontSee('map_origin')->assertDontSee('bounds');
        $this->actingAs($player)->get(route('maps.assets.show', [$room, $asset]))->assertOk();
        $this->actingAs($owner)->post(route('maps.duplicate', [$room, $map]), ['idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame($map->import_data, RoomMap::latest('id')->first()->import_data);
        $next = $this->preview($owner, $room)->assertOk()->json('preview.id');
        $this->actingAs($owner)->postJson(route('maps.import.confirm', [$room, $next]))->assertOk();
        $this->assertDatabaseCount('room_maps', 3);
        $this->assertDatabaseCount('room_assets', 2);
        $this->assertDatabaseCount('room_characters', 0);
        $this->assertDatabaseCount('room_map_objects', 0);
    }

    public function test_authorization_binding_expiration_and_changed_role_before_confirmation(): void
    {
        Storage::fake('local');
        [$owner,$player,$room] = $this->setupRoom();
        $this->preview($player, $room)->assertForbidden();
        $id = $this->preview($owner, $room)->assertOk()->json('preview.id');
        $other = Room::create(['owner_id' => $owner->id, 'title' => 'Outra', 'system' => 'Próprio']);
        $other->memberships()->create(['user_id' => $owner->id, 'role' => 'owner']);
        $this->actingAs($owner)->postJson(route('maps.import.confirm', [$other, $id]))->assertNotFound();
        $room->memberships()->create(['user_id' => ($master = User::factory()->create())->id, 'role' => 'master']);
        $this->actingAs($master)->postJson(route('maps.import.confirm', [$room, $id]))->assertNotFound();
        MapImportPreview::whereKey($id)->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($owner)->postJson(route('maps.import.confirm', [$room, $id]))->assertGone();
        $id = $this->preview($master, $room)->assertOk()->json('preview.id');
        $room->memberships()->where('user_id', $master->id)->update(['role' => 'player']);
        $this->actingAs($master)->postJson(route('maps.import.confirm', [$room, $id]))->assertForbidden();
        $this->assertDatabaseCount('room_maps', 0);
        $this->assertDatabaseCount('room_assets', 0);
    }

    public function test_file_and_database_failures_compensate_only_this_operation_and_retry_succeeds(): void
    {
        Storage::fake('local');
        [$owner,$player,$room] = $this->setupRoom();
        Storage::disk('local')->put('preexisting/keep.txt', 'KEEP');
        $id = $this->preview($owner, $room)->assertOk()->json('preview.id');
        $stage = MapImportPreview::findOrFail($id)->image_path;
        RoomMap::creating(function () {
            throw new \RuntimeException('Synthetic database failure after image write');
        });
        $this->actingAs($owner)->postJson(route('maps.import.confirm', [$room, $id]))->assertStatus(500);
        $this->assertDatabaseCount('room_maps', 0);
        $this->assertDatabaseCount('room_assets', 0);
        $this->assertDatabaseCount('room_events', 0);
        $this->assertEqualsCanonicalizing(['preexisting/keep.txt', $stage], Storage::disk('local')->allFiles());
        RoomMap::flushEventListeners();
        $this->actingAs($owner)->postJson(route('maps.import.confirm', [$room, $id]))->assertOk();
        $this->assertSame('KEEP', Storage::disk('local')->get('preexisting/keep.txt'));
        $this->assertDatabaseCount('room_maps', 1);
        $this->assertDatabaseCount('room_assets', 1);
        $id = $this->preview($owner, $room)->assertOk()->json('preview.id');
        $disk = Storage::disk('local');
        Storage::shouldReceive('disk')->with('local')->andReturn($mock = \Mockery::mock($disk)->makePartial());
        $mock->shouldReceive('get')->andReturnUsing(fn ($p) => $disk->get($p));
        $mock->shouldReceive('put')->andReturn(false);
        $mock->shouldReceive('delete')->andReturnUsing(fn ($p) => $disk->delete($p));
        $this->actingAs($owner)->postJson(route('maps.import.confirm', [$room, $id]))->assertStatus(500);
        $this->assertDatabaseCount('room_maps', 1);
        $this->assertDatabaseCount('room_assets', 1);
        $this->assertDatabaseCount('room_events', 1);
    }

    public function test_after_commit_failure_preserves_final_file_and_pruning_recovers_only_staging(): void
    {
        Storage::fake('local');
        [$owner,$player,$room] = $this->setupRoom();
        Storage::disk('local')->put('preexisting/keep.txt', 'KEEP');
        $id = $this->preview($owner, $room)->assertOk()->json('preview.id');
        $stage = MapImportPreview::findOrFail($id)->image_path;
        $real = new RoomSyncService;
        $mock = \Mockery::mock(RoomSyncService::class)->makePartial();
        $mock->shouldReceive('execute')->once()->andReturnUsing(function (...$args) use ($real) {
            $real->execute(...$args);
            // Simulate an exception after the durable command succeeded.
            throw new \RuntimeException('Synthetic post-commit dispatch failure');
        });
        $this->app->instance(RoomSyncService::class, $mock);
        $this->actingAs($owner)->postJson(route('maps.import.confirm', [$room, $id]))->assertStatus(500);
        $asset = RoomAsset::firstOrFail();
        Storage::disk('local')->assertExists($asset->path);
        Storage::disk('local')->assertExists($stage);
        $this->app->instance(RoomSyncService::class, $real);
        $this->artisan('maps:prune-import-previews')->assertSuccessful();
        Storage::disk('local')->assertMissing($stage);
        Storage::disk('local')->assertExists($asset->path);
        $this->actingAs($owner)->postJson(route('maps.import.confirm', [$room, $id]))->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('room_maps', 1);
        $this->assertDatabaseCount('room_assets', 1);
        $this->assertDatabaseCount('room_events', 1);
        $expired = $this->preview($owner, $room)->assertOk()->json('preview.id');
        MapImportPreview::whereKey($expired)->update(['expires_at' => now()->subMinute()]);
        $this->artisan('maps:prune-import-previews')->assertSuccessful();
        $this->assertDatabaseMissing('map_import_previews',['id' => $expired]);
        $this->assertSame('KEEP',Storage::disk('local')->get('preexisting/keep.txt'));
        RoomMap::firstOrFail()->delete();
        $this->actingAs($owner)->postJson(route('maps.import.confirm',[$room, $id]))->assertGone();
    }
}
