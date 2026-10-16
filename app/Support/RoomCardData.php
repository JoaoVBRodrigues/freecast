<?php

namespace App\Support;

use App\Models\Room;
use App\Models\User;

class RoomCardData
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Room $room, User $user): array
    {
        $membership = $room->memberships->firstWhere('user_id', $user->id);

        return [
            'id' => $room->id,
            'title' => $room->title,
            'system' => $room->system,
            'description' => $room->description,
            'is_public' => $room->is_public,
            'requires_password' => ! $room->is_public,
            'member_count' => $room->members_count,
            'owner_name' => $room->owner->name,
            'is_member' => $membership !== null,
            'role' => $membership?->role,
            'is_favorite' => $room->favorites->contains('user_id', $user->id),
            'is_owner' => $room->owner_id === $user->id,
        ];
    }
}
