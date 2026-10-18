<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomMapLayer extends Model
{
    protected $attributes = [
        'revision' => 1,
    ];

    protected $fillable = [
        'room_map_id',
        'type',
        'name',
        'sort_order',
        'visible',
        'locked',
        'is_master_only',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'locked' => 'boolean',
            'is_master_only' => 'boolean',
            'revision' => 'integer',
        ];
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(RoomMap::class, 'room_map_id');
    }

    public function objects(): HasMany
    {
        return $this->hasMany(RoomMapObject::class, 'room_map_layer_id');
    }
}
