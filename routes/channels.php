<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Room;
use App\Models\RoomMembership;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('rooms.{roomId}', function ($user, int $roomId): array|false {
    $membership = RoomMembership::query()
        ->with('user:id,name')
        ->where('room_id', $roomId)
        ->where('user_id', $user->id)
        ->first();

    if (! $membership) {
        return false;
    }

    return [
        'id' => $membership->user_id,
        'name' => $membership->user->name,
        'role' => $membership->role,
    ];
});

Broadcast::channel('rooms.{roomId}.masters', function ($user, int $roomId): bool {
    return RoomMembership::query()
        ->where('room_id', $roomId)
        ->where('user_id', $user->id)
        ->whereIn('role', [RoomMembership::ROLE_OWNER, RoomMembership::ROLE_MASTER])
        ->exists();
});

Broadcast::channel('users.{userId}', function ($user, int $userId): bool {
    return (int) $user->id === $userId;
});
