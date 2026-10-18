<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomCommandReceipt extends Model
{
    protected $fillable = [
        'room_id',
        'user_id',
        'operation',
        'idempotency_key',
        'request_hash',
        'command_id',
        'response',
    ];

    protected function casts(): array
    {
        return ['response' => 'array'];
    }
}
