<?php

namespace Tests\Feature;

use App\Models\{Room, RoomAsset, RoomCharacter, RoomCombat, RoomMapObject, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CharactersCombatTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $owner = User::factory()->create();
        $player = User::factory()->create();
        $third = User::factory()->create();
        $room = Room::query()->create(['owner_id' => $owner->id, 'title' => '4A', 'system' => 'Próprio', 'is_public' => true]);
        foreach ([[$owner, 'owner'], [$player, 'player'], [$third, 'player']] as [$user, $role]) {
            $room->memberships()->create(['user_id' => $user->id, 'role' => $role]);
        }

        return [$owner, $player, $third, $room];
    }

    private function command(User $user, Room $room, array $input)
    {
        return $this->actingAs($user)->postJson(route('characters.command', $room), ['idempotency_key' => (string) \Illuminate\Support\Str::uuid(), ...$input]);
    }

    private function combat(User $user, Room $room, string $action, array $extra = [])
    {
        return $this->actingAs($user)->postJson(route('combat.command', $room), [
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'expected_revision' => RoomCombat::query()->where('room_id', $room->id)->value('revision') ?? 1,
            'action' => $action, ...$extra,
        ]);
    }

    public function test_sheet_permissions_privacy_limits_and_reassignment(): void
    {
        [$owner, $player, $third, $room] = $this->fixtures();
        $created = $this->command($owner, $room, ['action' => 'create', 'name' => 'Heroína', 'notes' => 'SEGREDO-4A', 'attributes' => [['name' => 'Força', 'value' => '12']], 'resources' => [['name' => 'Mana', 'current' => 2, 'max' => 5]], 'hp_max' => 20, 'hp_current' => 18, 'assigned_user_id' => $player->id])->assertOk()->json();
        $id = $created['characterId'];
        $this->actingAs($player)->getJson(route('characters.index', $room))->assertOk()->assertJsonPath('characters.0.notes', 'SEGREDO-4A');
        $this->actingAs($third)->getJson(route('characters.index', $room))->assertJsonCount(0, 'characters')->assertDontSee('SEGREDO-4A');
        $this->actingAs($third)->getJson(route('rooms.events', $room))->assertOk()->assertDontSee('SEGREDO-4A')->assertDontSee('Heroína');
        $this->command($third, $room, ['action' => 'update', 'character_id' => $id, 'expected_revision' => 1, 'hp_current' => 5])->assertForbidden();
        $this->command($player, $room, ['action' => 'update', 'character_id' => $id, 'expected_revision' => 1, 'hp_current' => 21])->assertUnprocessable();
        $this->command($player, $room, ['action' => 'update', 'character_id' => $id, 'expected_revision' => 1, 'hp_current' => -1])->assertUnprocessable();
        $this->command($player, $room, ['action' => 'update', 'character_id' => $id, 'expected_revision' => 1, 'resources' => [['name' => 'Mana', 'current' => 8, 'max' => 2]]])->assertUnprocessable();
        $this->command($player, $room, ['action' => 'update', 'character_id' => $id, 'expected_revision' => 1, 'is_public' => true])->assertForbidden();
        $key = 'hp-idempotent-4a-000001';
        $update = ['action' => 'update', 'character_id' => $id, 'expected_revision' => 1, 'hp_current' => 7, 'idempotency_key' => $key];
        $this->command($player, $room, $update)->assertOk()->assertJsonPath('revision', 2);
        $this->command($player, $room, $update)->assertOk()->assertJsonPath('replayed', true);
        $this->command($player, $room, [...$update, 'hp_current' => 6])->assertConflict();
        $this->command($player, $room, ['action' => 'update', 'character_id' => $id, 'expected_revision' => 1, 'hp_current' => 6])->assertConflict();
        $this->command($owner, $room, ['action' => 'update', 'character_id' => $id, 'expected_revision' => 2, 'assigned_user_id' => $third->id, 'is_public' => true])->assertOk();
        $this->actingAs($player)->getJson(route('characters.index', $room))->assertJsonPath('characters.0.hp_current', 7)->assertJsonMissingPath('characters.0.notes')->assertJsonMissingPath('characters.0.attributes')->assertJsonMissingPath('characters.0.resources');
        $this->command($player, $room, $update)->assertOk()->assertDontSee('SEGREDO-4A'); // receipt stores no private fields
        $otherRoom = Room::query()->create(['owner_id' => $third->id, 'title' => 'Outra', 'system' => 'Próprio']);
        $otherRoom->memberships()->create(['user_id' => $third->id, 'role' => 'owner']);
        $this->command($third, $otherRoom, ['action' => 'update', 'character_id' => $id, 'expected_revision' => 3, 'hp_current' => 1])->assertNotFound();
        $this->actingAs($owner)->getJson(route('characters.index', $otherRoom))->assertForbidden();
    }

    public function test_private_portrait_and_token_projection_use_current_authorization(): void
    {
        [$owner, $player, $third, $room] = $this->fixtures();
        Storage::disk('local')->put('portrait-4a.png', 'synthetic-image');
        $asset = RoomAsset::query()->create(['room_id' => $room->id, 'uploaded_by' => $owner->id, 'kind' => 'token', 'name' => 'Retrato secreto', 'path' => 'portrait-4a.png', 'mime_type' => 'image/png', 'size' => 15, 'width' => 64, 'height' => 64, 'is_master_only' => true]);
        $character = RoomCharacter::query()->create(['room_id' => $room->id, 'name' => 'NPC secreto', 'portrait_asset_id' => $asset->id, 'assigned_user_id' => $player->id]);
        $this->actingAs($player)->get(route('characters.portrait', [$room, $character]))->assertOk();
        $this->actingAs($player)->get(route('maps.assets.show', [$room, $asset]))->assertForbidden();
        $this->actingAs($third)->get(route('characters.portrait', [$room, $character]))->assertNotFound();
        $this->actingAs($owner)->deleteJson(route('maps.assets.destroy', [$room, $asset]))->assertUnprocessable();
        $map = $room->maps()->create(['created_by' => $owner->id, 'title' => 'Mapa', 'width' => 800, 'height' => 600, 'cell_size' => 50]);
        $layer = $map->layers()->create(['type' => 'tokens', 'name' => 'Tokens', 'sort_order' => 2]);
        $publicAsset = $asset->replicate(); $publicAsset->is_master_only = false; $publicAsset->save();
        $token = $layer->objects()->create(['room_map_id' => $map->id, 'room_asset_id' => $publicAsset->id, 'name' => 'Disfarce', 'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50]);
        $this->command($owner, $room, ['action' => 'link', 'character_id' => $character->id, 'expected_revision' => 1, 'token_id' => $token->id, 'token_revision' => 1])->assertOk();
        $projection = app(\App\Services\RoomCharacterData::class);
        $this->assertSame('NPC secreto', $projection->token($room, $player, $token->fresh())['name']);
        $this->assertNull($projection->token($room, $third, $token->fresh())['character']);
        $this->actingAs($player)->get(route('maps.show', [$room, $map]))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('layers.0.objects.0.character.name', 'NPC secreto')
            ->where('layers.0.objects.0.name', 'NPC secreto'));
        $this->actingAs($third)->get(route('maps.show', [$room, $map]))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('layers.0.objects.0.character', null)
            ->where('layers.0.objects.0.name', 'Disfarce'))->assertDontSee('NPC secreto');
        $this->command($owner, $room, ['action' => 'update', 'character_id' => $character->id, 'expected_revision' => 2, 'assigned_user_id' => null])->assertOk();
        $this->actingAs($player)->get(route('characters.portrait', [$room, $character]))->assertNotFound();
        $this->command($owner, $room, ['action' => 'delete', 'character_id' => $character->id, 'expected_revision' => 3])->assertOk();
        $this->assertNull($token->fresh()->character_id);
    }

    public function test_combat_order_round_empty_removal_replay_and_conflict(): void
    {
        [$owner, $player, , $room] = $this->fixtures();
        $a = RoomCharacter::query()->create(['room_id' => $room->id, 'name' => 'A', 'is_public' => true]);
        $b = RoomCharacter::query()->create(['room_id' => $room->id, 'name' => 'B', 'is_public' => true]);
        $c = RoomCharacter::query()->create(['room_id' => $room->id, 'name' => 'C segredo']);
        $this->combat($player, $room, 'start')->assertForbidden();
        $this->combat($owner, $room, 'next')->assertUnprocessable();
        $this->combat($owner, $room, 'start')->assertOk();
        $this->assertSame(0, RoomCombat::first()->round);
        foreach ([$a, $b, $c] as $character) $this->combat($owner, $room, 'add', ['character_id' => $character->id, 'initiative' => 10])->assertOk();
        $state = RoomCombat::first();
        $this->assertSame([$a->id, $b->id, $c->id], array_column($state->participants, 'character_id'));
        $next = ['action' => 'next', 'expected_revision' => $state->revision, 'idempotency_key' => 'combat-next-idempotent-0001'];
        $this->actingAs($owner)->postJson(route('combat.command', $room), $next)->assertOk();
        $this->actingAs($owner)->postJson(route('combat.command', $room), $next)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame($state->participants[1]['id'], $state->fresh()->active_id);
        $this->combat($owner, $room, 'next', ['expected_revision' => $state->revision])->assertConflict();
        $this->combat($owner, $room, 'remove', ['participant_id' => $state->participants[1]['id']])->assertOk();
        $this->assertSame($state->participants[2]['id'], $state->fresh()->active_id);
        $this->combat($owner, $room, 'next')->assertOk();
        $this->assertSame(2, $state->fresh()->round);
        $this->actingAs($player)->getJson(route('characters.index', $room))->assertJsonCount(1, 'combat.participants')->assertDontSee('C segredo');
        $this->combat($owner, $room, 'initiative', ['participant_id' => $state->participants[2]['id'], 'initiative' => 99])->assertOk();
        $this->assertSame($c->id, $state->fresh()->participants[0]['character_id']);
        $this->combat($owner, $room, 'end')->assertOk();
        $this->assertFalse($state->fresh()->active);
        foreach ($state->fresh()->participants as $p) $this->combat($owner, $room, 'remove', ['participant_id' => $p['id']])->assertOk();
        $this->assertSame([], $state->fresh()->participants);
    }
}
