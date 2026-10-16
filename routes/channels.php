<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Room;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('rooms.{roomId}', function ($user, int $roomId): bool {
    return Room::query()
        ->whereKey($roomId)
        ->whereHas('memberships', fn ($query) => $query->where('user_id', $user->id))
        ->exists();
});
