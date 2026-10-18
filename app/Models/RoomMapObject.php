<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomMapObject extends Model
{
    protected $attributes = [
        'revision' => 1,
    ];

    protected $fillable = [
        'room_map_id',
        'room_map_layer_id',
        'room_asset_id',
        'assigned_user_id',
        'name',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'z_index',
        'visible',
        'locked',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
            'rotation' => 'float',
            'visible' => 'boolean',
            'locked' => 'boolean',
            'revision' => 'integer',
        ];
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(RoomMap::class, 'room_map_id');
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(RoomMapLayer::class, 'room_map_layer_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RoomAsset::class, 'room_asset_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
