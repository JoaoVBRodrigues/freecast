<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomMacro;
use App\Models\RoomMembership;
use App\Models\RoomMessage;
use App\Models\RoomAsset;
use App\Models\RoomMap;
use App\Models\RoomMapLayer;
use App\Models\RoomMapObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RoomRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dice_roll_is_persisted_and_idempotent(): void
    {
        [$owner, , , $room] = $this->roomWithMembers();
        $key = 'dice-roll-idempotency-00001';

        $first = $this->actingAs($owner)->postJson(route('rooms.messages.store', $room), [
            'body' => '/r 2d6 + 1d4 - 3',
            'idempotency_key' => $key,
        ])->assertOk()->assertJsonPath('replayed', false)->json();

        $second = $this->actingAs($owner)->postJson(route('rooms.messages.store', $room), [
            'body' => '/r 2d6 + 1d4 - 3',
            'idempotency_key' => $key,
        ])->assertOk()->assertJsonPath('replayed', true)->json();

        $this->assertSame($first['result'], $second['result']);
        $this->assertSame($first['commandId'], $second['commandId']);
        $this->assertDatabaseCount('dice_rolls', 1);
        $this->assertDatabaseHas('room_events', [
            'room_id' => $room->id,
            'event_type' => 'dice.rolled',
            'command_id' => $first['commandId'],
        ]);

        $this->actingAs($owner)->postJson(route('rooms.messages.store', $room), [
            'body' => '/r 1d3',
            'idempotency_key' => 'dice-invalid-expression-01',
        ])->assertUnprocessable();
    }

    public function test_private_messages_are_not_in_third_party_history_or_room_events(): void
    {
        [$owner, $player, $thirdPlayer, $room] = $this->roomWithMembers();
        $this->actingAs($owner)->postJson(route('rooms.messages.store', $room), [
            'body' => 'Segredo da missão',
            'message_type' => 'private',
            'recipient_user_id' => $player->id,
            'idempotency_key' => 'private-message-command-001',
        ])->assertOk();

        $this->actingAs($thirdPlayer)
            ->getJson(route('rooms.messages.history', $room))
            ->assertOk()
            ->assertJsonCount(0, 'messages')
            ->assertDontSee('Segredo da missão');

        $this->actingAs($thirdPlayer)
            ->getJson(route('rooms.events', $room))
            ->assertOk()
            ->assertJsonCount(0, 'events')
            ->assertDontSee('Segredo da missão');

        $this->actingAs($player)
            ->getJson(route('rooms.messages.history', $room))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Segredo da missão');

        $this->assertDatabaseCount('room_events', 2);
        $this->assertDatabaseMissing('room_events', [
            'room_id' => $room->id,
            'audience' => 'room',
            'event_type' => 'message.created',
        ]);
    }

    public function test_macro_name_uses_documented_precedence_while_button_runs_selected_scope(): void
    {
        [$owner, $player, , $room] = $this->roomWithMembers();
        $local = $this->macro($room, $owner, RoomMacro::PERSONAL_ROOM, 'Atacar', 'Local primeiro');
        $this->macro($room, $owner, RoomMacro::PERSONAL_GLOBAL, 'Atacar', 'Global depois');
        $shared = $this->macro($room, null, RoomMacro::SHARED_ROOM, 'Atacar', 'Compartilhada por último');

        $this->actingAs($owner)->postJson(route('rooms.messages.store', $room), [
            'body' => 'At acar',
            'idempotency_key' => 'macro-name-command-000001',
        ])->assertOk();
        $this->assertDatabaseHas('room_messages', [
            'room_id' => $room->id,
            'body' => 'Local primeiro',
        ]);

        $this->actingAs($player)->postJson(route('rooms.macros.execute', [$room, $shared]), [
            'idempotency_key' => 'macro-button-command-00001',
        ])->assertOk();
        $this->assertDatabaseHas('room_messages', [
            'room_id' => $room->id,
            'user_id' => $player->id,
            'body' => 'Compartilhada por último',
        ]);

        $this->actingAs($player)->postJson(route('rooms.macros.store', $room), [
            'scope' => RoomMacro::SHARED_ROOM,
            'name' => 'Sem permissão',
            'body' => 'Não deve salvar',
            'idempotency_key' => 'macro-shared-denied-0001',
        ])->assertForbidden();

        $this->assertDatabaseHas('room_macros', ['id' => $local->id]);
    }

    public function test_pending_outbox_events_can_be_redispatched_after_worker_restart(): void
    {
        Queue::fake();
        [$owner, , , $room] = $this->roomWithMembers();
        $this->actingAs($owner)->postJson(route('rooms.messages.store', $room), [
            'body' => 'Evento para recuperar',
            'idempotency_key' => 'outbox-recovery-command-001',
        ])->assertOk();

        $event = $room->events()->where('event_type', 'message.created')->firstOrFail();
        $event->forceFill(['queued_at' => now()->subMinutes(2), 'published_at' => null])->save();

        $count = app(\App\Services\RoomOutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $count);
        Queue::assertPushed(\App\Jobs\DeliverRoomEvent::class, fn ($job) => $job->eventId === $event->id);
    }

    public function test_map_object_commands_reconcile_stale_revisions_and_replay_receipts(): void
    {
        [$owner, , , $room] = $this->roomWithMembers();
        [$map, $object] = $this->mapWithObject($owner, $room);
        $key = 'map-move-command-unique-001';
        $payload = [
            'x' => 125,
            'y' => 75,
            'expected_revision' => 1,
            'idempotency_key' => $key,
        ];

        $this->actingAs($owner)
            ->patchJson(route('maps.objects.update', [$room, $map, $object]), $payload)
            ->assertOk()
            ->assertJsonPath('revision', 2)
            ->assertJsonPath('replayed', false);

        $this->actingAs($owner)
            ->patchJson(route('maps.objects.update', [$room, $map, $object]), $payload)
            ->assertOk()
            ->assertJsonPath('revision', 2)
            ->assertJsonPath('replayed', true);

        $this->actingAs($owner)
            ->patchJson(route('maps.objects.update', [$room, $map, $object]), [
                ...$payload,
                'x' => 300,
                'idempotency_key' => 'map-stale-command-unique-01',
            ])
            ->assertConflict()
            ->assertJsonPath('revision', 2)
            ->assertJsonPath('object.x', 125);

        $this->assertDatabaseHas('room_map_objects', ['id' => $object->id, 'x' => 125, 'revision' => 2]);
        $this->assertDatabaseCount('room_events', 1);
    }

    public function test_master_only_map_events_are_not_replayed_to_players(): void
    {
        [$owner, $player, , $room] = $this->roomWithMembers();
        [$map, $object] = $this->mapWithObject($owner, $room, true);

        $this->actingAs($owner)
            ->patchJson(route('maps.objects.update', [$room, $map, $object]), [
                'x' => 200,
                'y' => 180,
                'expected_revision' => 1,
                'idempotency_key' => 'master-object-update-command-1',
            ])
            ->assertOk();

        $this->assertDatabaseHas('room_events', [
            'room_id' => $room->id,
            'audience' => 'masters',
            'event_type' => 'map.contents.changed',
        ]);

        $this->actingAs($player)->getJson(route('rooms.events', $room))
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.type', 'characters.changed')
            ->assertJsonPath('events.0.entityId', null)
            ->assertJsonPath('events.0.entityRevision', null)
            ->assertJsonPath('events.0.payload', []);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Room}
     */
    private function roomWithMembers(): array
    {
        $owner = User::factory()->create();
        $player = User::factory()->create();
        $thirdPlayer = User::factory()->create();
        $room = Room::query()->create([
            'owner_id' => $owner->id,
            'title' => 'Mesa sincronizada',
            'system' => 'Sistema próprio',
            'is_public' => true,
        ]);

        $room->memberships()->create(['user_id' => $owner->id, 'role' => RoomMembership::ROLE_OWNER]);
        $room->memberships()->create(['user_id' => $player->id, 'role' => RoomMembership::ROLE_PLAYER]);
        $room->memberships()->create(['user_id' => $thirdPlayer->id, 'role' => RoomMembership::ROLE_PLAYER]);

        return [$owner, $player, $thirdPlayer, $room];
    }

    private function macro(Room $room, ?User $user, string $scope, string $name, string $body): RoomMacro
    {
        $nameKey = strtoupper($name);

        return RoomMacro::query()->create([
            'room_id' => $scope === RoomMacro::PERSONAL_GLOBAL ? null : $room->id,
            'user_id' => $scope === RoomMacro::SHARED_ROOM ? null : $user?->id,
            'scope' => $scope,
            'scope_key' => $scope === RoomMacro::SHARED_ROOM ? 'room:'.$room->id : 'user:'.$user?->id,
            'name' => $name,
            'name_key' => $nameKey,
            'body' => $body,
        ]);
    }

    /**
     * @return array{0: RoomMap, 1: RoomMapObject}
     */
    private function mapWithObject(User $owner, Room $room, bool $masterOnly = false): array
    {
        $map = $room->maps()->create([
            'created_by' => $owner->id,
            'title' => 'Mapa de sincronização',
            'width' => 1000,
            'height' => 700,
            'cell_size' => 50,
        ]);
        $layer = $map->layers()->create([
            'type' => $masterOnly ? 'gm' : 'tokens',
            'name' => $masterOnly ? 'Somente mestre' : 'Tokens',
            'sort_order' => $masterOnly ? 3 : 2,
            'is_master_only' => $masterOnly,
        ]);
        $asset = RoomAsset::query()->create([
            'room_id' => $room->id,
            'uploaded_by' => $owner->id,
            'kind' => RoomAsset::KIND_TOKEN,
            'name' => 'Token sintético',
            'path' => 'synthetic/token.png',
            'mime_type' => 'image/png',
            'size' => 100,
            'width' => 64,
            'height' => 64,
            'is_master_only' => $masterOnly,
        ]);
        $object = $layer->objects()->create([
            'room_map_id' => $map->id,
            'room_asset_id' => $asset->id,
            'name' => 'Token',
            'x' => 50,
            'y' => 50,
            'width' => 50,
            'height' => 50,
        ]);

        return [$map, $object];
    }
}
