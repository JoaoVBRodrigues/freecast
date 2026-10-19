<?php

namespace App\Services;

use App\Models\{Room, RoomCharacter, RoomCombat, RoomMapObject, User};
use Illuminate\Support\Facades\Gate;

/** Current authorization is evaluated on every snapshot; events contain no sheet data. */
class RoomCharacterData
{
    public function canEdit(Room $room, User $user, RoomCharacter $character): bool
    {
        return Gate::forUser($user)->allows('manage', $room) || ($character->assigned_user_id === $user->id && app(MapVisibility::class)->character($character));
    }

    public function visible(Room $room, User $user, RoomCharacter $character): bool
    {
        return Gate::forUser($user)->allows('manage', $room) || (app(MapVisibility::class)->character($character) && ($character->is_public || $character->assigned_user_id === $user->id));
    }

    public function character(Room $room, User $user, RoomCharacter $character): array
    {
        $editable = $this->canEdit($room, $user, $character);
        $portrait = $character->portrait;
        $publicPortrait = $portrait && ! $portrait->is_master_only && ! $portrait->folder?->is_master_only;
        $data = [
            'id' => $character->id, 'name' => $character->name,
            'hp_current' => $character->hp_current, 'hp_max' => $character->hp_max,
            'revision' => $character->revision, 'can_edit' => $editable,
            'portrait_url' => $portrait && ($editable || $publicPortrait)
                ? route('characters.portrait', [$room, $character]) : null,
        ];
        if ($editable) {
            $data += [
                'notes' => $character->notes, 'attributes' => $character->attributes,
                'resources' => $character->resources, 'assigned_user_id' => $character->assigned_user_id,
                'portrait_asset_id' => $character->portrait_asset_id, 'is_public' => $character->is_public,
            ];
        }

        return $data;
    }

    public function snapshot(Room $room, User $user): array
    {
        $characters = RoomCharacter::query()->with('portrait.folder')->where('room_id', $room->id)->orderBy('id')->get()
            ->filter(fn ($character) => $this->visible($room, $user, $character));
        $combat = RoomCombat::query()->where('room_id', $room->id)->first();
        $linkedTokens = RoomMapObject::query()->whereIn('id', array_filter(array_column($combat?->participants ?? [], 'token_id')))
            ->whereHas('map', fn ($query) => $query->where('room_id', $room->id))->with(['map', 'layer', 'asset.folder'])->get()->keyBy('id');
        $participants = collect($combat?->participants ?? [])->filter(fn ($p) => $characters->contains('id', $p['character_id']) && (Gate::forUser($user)->allows('manage', $room) || ! $p['token_id'] || ($linkedTokens->has($p['token_id']) && app(MapVisibility::class)->object($linkedTokens->get($p['token_id'])))))
            ->map(fn ($p) => [...$p, 'token_id' => Gate::forUser($user)->allows('manage', $room) && ($linkedTokens->get($p['token_id'])?->character_id === $p['character_id']) ? $p['token_id'] : null, 'name' => $characters->firstWhere('id', $p['character_id'])->name])->values();

        return [
            'characters' => $characters->map(fn ($character) => $this->character($room, $user, $character))->values(),
            'combat' => [
                'active' => $combat?->active ?? false, 'round' => $combat?->round ?? 0,
                'revision' => $combat?->revision ?? 1, 'participants' => $participants,
                'active_id' => $participants->contains('id', $combat?->active_id) ? $combat?->active_id : null,
            ],
        ];
    }

    public function token(Room $room, User $user, RoomMapObject $object): array
    {
        $character = $object->character;
        if (! $character || $character->room_id !== $room->id || ! $this->visible($room, $user, $character)) {
            return ['character_id' => null, 'character' => null];
        }

        return ['character_id' => $character->id, 'character' => [
            'name' => $character->name, 'hp_current' => $character->hp_current, 'hp_max' => $character->hp_max,
        ], 'name' => $character->name];
    }
}
