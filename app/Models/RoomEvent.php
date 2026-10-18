<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'room_id',
        'audience',
        'sequence',
        'event_type',
        'entity_id',
        'entity_revision',
        'command_id',
        'payload',
        'occurred_at',
        'queued_at',
        'published_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'queued_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
