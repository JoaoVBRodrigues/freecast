<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\RoomMembership;
use App\Models\User;

class RoomPolicy
{
    public function view(User $user, Room $room): bool
    {
        return $room->memberships()->where('user_id', $user->id)->exists();
    }

    public function favorite(User $user, Room $room): bool
    {
        return $room->is_public || $this->view($user, $room);
    }

    public function manage(User $user, Room $room): bool
    {
        return $room->memberships()
            ->where('user_id', $user->id)
            ->whereIn('role', [RoomMembership::ROLE_OWNER, RoomMembership::ROLE_MASTER])
            ->exists();
    }

    public function leave(User $user, Room $room): bool
    {
        return $room->memberships()
            ->where('user_id', $user->id)
            ->whereIn('role', [RoomMembership::ROLE_MASTER, RoomMembership::ROLE_PLAYER])
            ->exists();
    }
}
