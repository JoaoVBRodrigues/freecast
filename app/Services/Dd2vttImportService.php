<?php

namespace App\Services;

use App\Models\MapImportPreview;
use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Throwable;

class Dd2vttImportService
{
    public function prepare(Room $room, User $user, string $json, string $title): MapImportPreview
    {
        Gate::forUser($user)->authorize('manage', $room);
        $v = app(Dd2vttParser::class)->parse($json);
        $id = (string) Str::uuid();
        $path = "imports/staging/$id/image";
        try {
            if (! Storage::disk('local')->put($path, $v['image'])) {
                throw new \RuntimeException('Falha ao gravar imagem temporária.');
            }
            $hash = hash('sha256', $v['image']);
            unset($v['image']);

            return MapImportPreview::create(['id' => $id, 'room_id' => $room->id, 'user_id' => $user->id, 'title' => $title, 'image_path' => $path, 'image_hash' => $hash, 'validated' => $v, 'expires_at' => now()->addMinutes(30)]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function authorized(Room $room, User $user, string $id): MapImportPreview
    {
        Gate::forUser($user)->authorize('manage', $room);
        $p = MapImportPreview::whereKey($id)->where('room_id', $room->id)->where('user_id', $user->id)->firstOrFail();
        abort_if($p->confirmed_at && ! $p->map_id, 410, 'Mapa importado foi removido; prepare uma nova importação.');
        if (! $p->confirmed_at && $p->expires_at->isPast()) {
            Storage::disk('local')->delete($p->image_path);
            throw new GoneHttpException('Prévia expirada; selecione novamente o arquivo.');
        }

        return $p;
    }

    public function confirm(Room $room, User $user, string $id): array
    {
        $this->authorized($room, $user, $id);
        $written = [];
        try {
            $result = app(RoomSyncService::class)->execute($room, $user, 'map-import', $id, ['preview_id' => $id], function (string $command) use ($room, $user, $id, &$written) {
                // Revalidate current role under the same room lock as every durable command.
                Gate::forUser($user)->authorize('manage', $room);
                $p = $this->authorized($room, $user, $id);
                if ($p->confirmed_at) {
                    abort_unless($p->map_id, 410, 'Mapa importado foi removido.');

                    return ['mapId' => $p->map_id];
                }
                $v = $p->validated;
                $bytes = Storage::disk('local')->get($p->image_path);
                abort_unless(is_string($bytes) && hash_equals($p->image_hash, hash('sha256', $bytes)), 410, 'Imagem temporária ausente ou alterada.');
                $path = "rooms/{$room->id}/library/".Str::uuid().($v['mime'] === 'image/png' ? '.png' : '.webp');
                $written[] = $path;
                if (! Storage::disk('local')->put($path, $bytes)) {
                    throw new \RuntimeException('Falha ao gravar fundo privado.');
                }
                $asset = $room->assets()->create(['uploaded_by' => $user->id, 'kind' => 'background', 'name' => $p->title, 'path' => $path, 'mime_type' => $v['mime'], 'size' => strlen($bytes), 'width' => $v['width'], 'height' => $v['height'], 'is_master_only' => false]);
                $title = $p->title;
                $suffix = 2;
                while ($room->maps()->where('title', $title)->exists()) {
                    $title = mb_substr($p->title, 0, 90).' ('.$suffix++.')';
                }
                $map = $room->maps()->create(['created_by' => $user->id, 'background_asset_id' => $asset->id, 'title' => $title, 'width' => $v['width'], 'height' => $v['height'], 'cell_size' => $v['cell_size'], 'fog_enabled' => true, 'fog_revealed' => [], 'import_data' => [...$v['data'], 'warnings' => $v['warnings'], 'counts' => $v['counts']]]);
                foreach ([['background', 'Fundo', true, false], ['objects', 'Objetos', false, false], ['tokens', 'Tokens', false, false], ['gm', 'Somente mestre', false, true]] as $i => $layer) {
                    $map->layers()->create(['type' => $layer[0], 'name' => $layer[1], 'sort_order' => $i, 'locked' => $layer[2], 'is_master_only' => $layer[3]]);
                }
                $p->update(['map_id' => $map->id, 'confirmed_at' => now()]);
                app(RoomSyncService::class)->record($room, 'room', 'map.created', (string) $map->id, $map->revision, $command, $id, ['map_id' => $map->id, 'title' => $map->title]);

                return ['mapId' => $map->id];
            });
            $p = MapImportPreview::findOrFail($id);
            try {
                if ($p->image_path !== '' && Storage::disk('local')->delete($p->image_path)) {
                    $p->update(['image_path' => '']);
                }
            } catch (Throwable $cleanupFailure) {
                // Import already committed. Scheduled pruning retries staging only.
                report($cleanupFailure);
            }

            return $result;
        } finally {
            // Also safe for deadlock retries and after-commit dispatcher failures:
            // only paths allocated by this call, and never a committed asset file.
            foreach ($written as $path) {
                if (! RoomAsset::where('path', $path)->exists()) {
                    Storage::disk('local')->delete($path);
                }
            }
        }
    }

    public function cancel(Room $room, User $user, string $id): void
    {
        DB::transaction(function () use ($room, $user, $id) {
            Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            $p = $this->authorized($room, $user, $id);
            abort_if($p->confirmed_at, 409, 'Importação já confirmada.');
            if (! Storage::disk('local')->delete($p->image_path)) {
                throw new \RuntimeException('Falha ao descartar a prévia.');
            }
            $p->delete();
        });
    }
}
