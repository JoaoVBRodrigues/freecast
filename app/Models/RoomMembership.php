<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomMembership extends Model
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_MASTER = 'master';

    public const ROLE_PLAYER = 'player';

    protected $fillable = [
        'room_id',
        'user_id',
        'role',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
