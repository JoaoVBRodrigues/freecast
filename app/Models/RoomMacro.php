<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomMacro extends Model
{
    public const PERSONAL_ROOM = 'personal_room';

    public const PERSONAL_GLOBAL = 'personal_global';

    public const SHARED_ROOM = 'shared_room';

    protected $attributes = [
        'revision' => 1,
    ];

    protected $fillable = [
        'room_id',
        'user_id',
        'scope',
        'scope_key',
        'name',
        'name_key',
        'body',
        'revision',
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
