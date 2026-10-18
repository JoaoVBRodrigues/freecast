<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomMembership;
use App\Models\RoomPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoomAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_player_can_join_a_public_room_and_persist_favorites_and_recents(): void
    {
        $owner = User::factory()->create();
        $player = User::factory()->create();
        $room = Room::query()->create([
            'owner_id' => $owner->id,
            'title' => 'Costa das Brumas',
            'system' => 'Sistema Próprio',
            'description' => '',
            'is_public' => true,
        ]);
        $room->memberships()->create([
            'user_id' => $owner->id,
            'role' => RoomMembership::ROLE_OWNER,
        ]);

        $this->actingAs($player)
            ->post(route('rooms.join', $room))
            ->assertRedirect(route('rooms.show', $room));

        $this->assertDatabaseHas('room_memberships', [
            'room_id' => $room->id,
            'user_id' => $player->id,
            'role' => RoomMembership::ROLE_PLAYER,
        ]);

        $this->get(route('rooms.show', $room))->assertOk();
        $this->post(route('rooms.favorite', $room))->assertRedirect();

        $this->assertDatabaseHas('room_favorites', [
            'room_id' => $room->id,
            'user_id' => $player->id,
        ]);
        $this->assertDatabaseHas('room_visits', [
            'room_id' => $room->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_only_room_members_can_read_a_room_and_private_rooms_check_the_password(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $room = Room::query()->create([
            'owner_id' => $owner->id,
            'title' => 'Jardim de Cinzas',
            'system' => 'D&D',
            'description' => '',
            'is_public' => false,
            'password_hash' => Hash::make('segredo-da-mesa'),
        ]);
        $room->memberships()->create([
            'user_id' => $owner->id,
            'role' => RoomMembership::ROLE_OWNER,
        ]);

        $this->actingAs($outsider)->get(route('rooms.show', $room))->assertForbidden();
        $this->post(route('rooms.join', $room), ['password' => 'senha-incorreta'])->assertForbidden();
        $this->post(route('rooms.join', $room), ['password' => 'segredo-da-mesa'])
            ->assertRedirect(route('rooms.show', $room));
        $this->get(route('rooms.show', $room))->assertOk();
    }

    public function test_a_room_owner_cannot_leave_their_room(): void
    {
        $owner = User::factory()->create();
        $room = Room::query()->create([
            'owner_id' => $owner->id,
            'title' => 'Mesa da Aurora',
            'system' => 'D&D',
            'is_public' => true,
        ]);
        $room->memberships()->create([
            'user_id' => $owner->id,
            'role' => RoomMembership::ROLE_OWNER,
        ]);

        $this->actingAs($owner)
            ->post(route('rooms.leave', $room))
            ->assertForbidden();

        $this->assertDatabaseHas('room_memberships', [
            'room_id' => $room->id,
            'user_id' => $owner->id,
            'role' => RoomMembership::ROLE_OWNER,
        ]);
    }

    public function test_room_panel_preferences_persist_and_messages_are_broadcast(): void
    {
        $owner = User::factory()->create();
        $room = Room::query()->create([
            'owner_id' => $owner->id,
            'title' => 'A Vigília do Farol',
            'system' => 'Sistema Próprio',
            'is_public' => true,
        ]);
        $room->memberships()->create([
            'user_id' => $owner->id,
            'role' => RoomMembership::ROLE_OWNER,
        ]);

        $this->actingAs($owner)
            ->patch(route('rooms.preferences', $room), [
                'panels' => ['library' => false, 'participants' => false],
            ])
            ->assertRedirect();

        $this->assertSame([
            'library' => false,
            'macros' => true,
            'combat' => true,
            'participants' => false,
        ], RoomPreference::query()
            ->where('room_id', $room->id)
            ->where('user_id', $owner->id)
            ->firstOrFail()
            ->panels);

        $this->postJson(route('rooms.messages.store', $room), [
            'body' => 'A porta se abriu.',
            'idempotency_key' => 'room-message-command-0001',
        ])->assertOk();

        $this->assertDatabaseHas('room_messages', [
            'room_id' => $room->id,
            'user_id' => $owner->id,
            'body' => 'A porta se abriu.',
        ]);
        $this->assertDatabaseHas('room_events', [
            'room_id' => $room->id,
            'event_type' => 'message.created',
        ]);
    }
}
