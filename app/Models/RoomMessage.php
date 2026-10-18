<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomMessage extends Model
{
    protected $fillable = [
        'room_id',
        'user_id',
        'body',
        'message_type',
        'recipient_user_id',
        'asset_ids',
    ];

    protected function casts(): array
    {
        return ['asset_ids' => 'array'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
