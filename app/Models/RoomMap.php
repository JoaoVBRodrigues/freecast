<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomMap extends Model
{
    protected $attributes = [
        'revision' => 1,
    ];

    protected $fillable = [
        'fog_enabled', 'fog_revealed', 'distance_scale', 'distance_unit',
        'room_id',
        'background_asset_id',
        'created_by',
        'title',
        'width',
        'height',
        'cell_size',
        'grid_color',
        'grid_opacity',
        'grid_enabled',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'fog_enabled' => 'boolean', 'fog_revealed' => 'array', 'distance_scale' => 'float',
            'grid_opacity' => 'float',
            'grid_enabled' => 'boolean',
            'revision' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function background(): BelongsTo
    {
        return $this->belongsTo(RoomAsset::class, 'background_asset_id');
    }

    public function layers(): HasMany
    {
        return $this->hasMany(RoomMapLayer::class)->orderBy('sort_order');
    }

    public function objects(): HasMany
    {
        return $this->hasMany(RoomMapObject::class);
    }
}
