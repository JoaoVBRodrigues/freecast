<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\RoomMap;
use App\Models\RoomMapLayer;
use App\Models\RoomMapObject;
use App\Models\RoomMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MapEditorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_duplicate_rename_reorder_and_delete_maps(): void
    {
        [$owner, , $room] = $this->roomWithMembers();
        $this->actingAs($owner)
            ->post(route('maps.store', $room), [
                'title' => 'Mapa de teste',
                'width' => 1200,
                'height' => 800,
                'cell_size' => 40,
                'idempotency_key' => 'create-map-command-0001',
            ])
            ->assertRedirect();

        $map = RoomMap::query()->where('room_id', $room->id)->firstOrFail();
        $this->assertDatabaseCount('room_map_layers', 4);

        $objectsLayer = $map->layers()->where('type', 'objects')->firstOrFail();
        $this->actingAs($owner)
            ->patch(route('maps.layers.update', [$room, $map, $objectsLayer]), [
                'sort_order' => 3,
                'expected_revision' => $objectsLayer->revision,
                'idempotency_key' => 'reorder-layer-command-001',
            ])
            ->assertRedirect();

        $this->assertSame(
            ['background' => 0, 'tokens' => 1, 'gm' => 2, 'objects' => 3],
            $map->layers()->orderBy('sort_order')->pluck('sort_order', 'type')->all(),
        );

        $this->actingAs($owner)
            ->post(route('maps.duplicate', [$room, $map]), ['idempotency_key' => 'duplicate-map-command-01'])
            ->assertRedirect();
        $copy = RoomMap::query()->where('room_id', $room->id)->where('title', 'Mapa de teste (cópia)')->firstOrFail();
        $this->actingAs($owner)
            ->patch(route('maps.update', [$room, $copy]), [
                'title' => 'Mapa renomeado',
                'expected_revision' => $copy->revision,
                'idempotency_key' => 'rename-map-command-0001',
            ])
            ->assertRedirect();
        $this->actingAs($owner)
            ->delete(route('maps.destroy', [$room, $copy]), ['idempotency_key' => 'delete-map-command-00001'])
            ->assertRedirect(route('maps.index', $room));

        $this->assertDatabaseMissing('room_maps', ['id' => $copy->id]);
    }

    public function test_players_receive_only_visible_shared_map_data_and_private_asset_downloads_are_denied(): void
    {
        Storage::fake('local');
        [$owner, $player, $room] = $this->roomWithMembers();
        [$map, $layers] = $this->createMap($owner, $room);
        $publicAsset = $this->createAsset($room, $owner, 'public-token.png');
        $secretAsset = $this->createAsset($room, $owner, 'secret-token.png', true);

        $playerToken = $layers['tokens']->objects()->create([
            'room_map_id' => $map->id,
            'room_asset_id' => $publicAsset->id,
            'assigned_user_id' => $player->id,
            'name' => 'Token do jogador',
            'x' => 50,
            'y' => 100,
            'width' => 48,
            'height' => 48,
        ]);
        $layers['tokens']->objects()->create([
            'room_map_id' => $map->id,
            'room_asset_id' => $publicAsset->id,
            'name' => 'Token oculto',
            'x' => 100,
            'y' => 100,
            'width' => 48,
            'height' => 48,
            'visible' => false,
        ]);
        $layers['gm']->objects()->create([
            'room_map_id' => $map->id,
            'room_asset_id' => $secretAsset->id,
            'name' => 'Segredo do mestre',
            'x' => 200,
            'y' => 200,
            'width' => 48,
            'height' => 48,
        ]);

        $this->actingAs($player)
            ->get(route('maps.show', [$room, $map]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Maps/Editor')
                ->has('layers', 3)
                ->where('layers.2.objects.0.name', 'Token do jogador')
                ->where('layers.2.objects.0.assigned_user_id', $player->id)
                ->where('layers.2.objects.0.can_move', true)
                ->where('assets', fn ($assets) => $assets->every(fn ($asset) => ! $asset['is_master_only']))
            )
            ->assertDontSee('Segredo do mestre')
            ->assertDontSee('Token oculto')
            ->assertDontSee('secret-token.png');

        $this->actingAs($player)
            ->get(route('maps.assets.show', [$room, $secretAsset]))
            ->assertForbidden();
        $this->actingAs($owner)
            ->get(route('maps.show', [$room, $map]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('layers', 4)
                ->where('layers.3.objects.0.name', 'Segredo do mestre')
            );
    }

    public function test_player_can_move_only_their_authorized_token_and_cannot_edit_maps_or_layers(): void
    {
        Storage::fake('local');
        [$owner, $player, $room] = $this->roomWithMembers();
        [$map, $layers] = $this->createMap($owner, $room);
        $asset = $this->createAsset($room, $owner, 'token.png');
        $assigned = $layers['tokens']->objects()->create([
            'room_map_id' => $map->id,
            'room_asset_id' => $asset->id,
            'assigned_user_id' => $player->id,
            'name' => 'Token autorizado',
            'x' => 0,
            'y' => 0,
            'width' => 48,
            'height' => 48,
        ]);
        $other = $layers['tokens']->objects()->create([
            'room_map_id' => $map->id,
            'room_asset_id' => $asset->id,
            'name' => 'Token alheio',
            'x' => 50,
            'y' => 0,
            'width' => 48,
            'height' => 48,
        ]);

        $this->actingAs($player)
            ->patch(route('maps.update', [$room, $map]), ['grid_enabled' => false])
            ->assertForbidden();
        $this->actingAs($player)
            ->patch(route('maps.layers.update', [$room, $map, $layers['tokens']]), ['locked' => true])
            ->assertForbidden();
        $this->actingAs($player)
            ->patch(route('maps.objects.update', [$room, $map, $other]), ['x' => 200, 'y' => 100])
            ->assertForbidden();
        $this->actingAs($player)
            ->patch(route('maps.objects.update', [$room, $map, $assigned]), ['name' => 'Alteração proibida'])
            ->assertForbidden();
        $this->actingAs($player)
            ->patchJson(route('maps.objects.update', [$room, $map, $assigned]), [
                'x' => 140,
                'y' => 90,
                'expected_revision' => $assigned->revision,
                'idempotency_key' => 'move-token-command-0001',
            ])
            ->assertOk()
            ->assertJsonPath('revision', 2);

        $this->assertDatabaseHas('room_map_objects', [
            'id' => $assigned->id,
            'x' => 140,
            'y' => 90,
            'name' => 'Token autorizado',
        ]);
        $this->assertDatabaseHas('room_map_objects', [
            'id' => $other->id,
            'x' => 50,
            'y' => 0,
        ]);
    }

    public function test_private_image_upload_is_stored_and_served_only_to_room_managers(): void
    {
        Storage::fake('local');
        [$owner, $player, $room] = $this->roomWithMembers();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0k8AAAAASUVORK5CYII=', true);
        $this->assertIsString($png);

        $this->actingAs($owner)
            ->post(route('maps.assets.store', $room), [
                'file' => UploadedFile::fake()->createWithContent('hidden-map.png', $png),
                'kind' => 'background',
                'name' => 'Fundo secreto',
                'is_master_only' => true,
            ])
            ->assertRedirect();

        $asset = RoomAsset::query()->where('room_id', $room->id)->firstOrFail();
        Storage::disk('local')->assertExists($asset->path);
        $this->actingAs($player)
            ->get(route('maps.assets.show', [$room, $asset]))
            ->assertForbidden();
        $this->actingAs($player)
            ->get(route('maps.index', $room))
            ->assertInertia(fn (Assert $page) => $page->where('assets', []));
        $this->actingAs($owner)
            ->get(route('maps.assets.show', [$room, $asset]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * @return array{0: User, 1: User, 2: Room}
     */
    private function roomWithMembers(): array
    {
        $owner = User::factory()->create();
        $player = User::factory()->create();
        $room = Room::query()->create([
            'owner_id' => $owner->id,
            'title' => 'Mesa de testes',
            'system' => 'Sistema Próprio',
            'description' => '',
            'is_public' => true,
        ]);
        $room->memberships()->create(['user_id' => $owner->id, 'role' => RoomMembership::ROLE_OWNER]);
        $room->memberships()->create(['user_id' => $player->id, 'role' => RoomMembership::ROLE_PLAYER]);

        return [$owner, $player, $room];
    }

    /**
     * @return array{0: RoomMap, 1: array<string, RoomMapLayer>}
     */
    private function createMap(User $owner, Room $room): array
    {
        $map = $room->maps()->create([
            'created_by' => $owner->id,
            'title' => 'Mapa de testes',
            'width' => 1200,
            'height' => 800,
            'cell_size' => 50,
        ]);
        $layers = [];
        foreach ([
            'background' => ['name' => 'Fundo', 'sort_order' => 0, 'locked' => true, 'is_master_only' => false],
            'objects' => ['name' => 'Objetos', 'sort_order' => 1, 'locked' => false, 'is_master_only' => false],
            'tokens' => ['name' => 'Tokens', 'sort_order' => 2, 'locked' => false, 'is_master_only' => false],
            'gm' => ['name' => 'Somente mestre', 'sort_order' => 3, 'locked' => false, 'is_master_only' => true],
        ] as $type => $attributes) {
            $layers[$type] = $map->layers()->create(['type' => $type, ...$attributes]);
        }

        return [$map, $layers];
    }

    private function createAsset(Room $room, User $owner, string $path, bool $masterOnly = false): RoomAsset
    {
        Storage::disk('local')->put("rooms/{$room->id}/{$path}", 'synthetic image fixture');

        return $room->assets()->create([
            'uploaded_by' => $owner->id,
            'kind' => RoomAsset::KIND_TOKEN,
            'name' => pathinfo($path, PATHINFO_FILENAME),
            'path' => "rooms/{$room->id}/{$path}",
            'mime_type' => 'image/png',
            'size' => 23,
            'width' => 48,
            'height' => 48,
            'is_master_only' => $masterOnly,
        ]);
    }
}
