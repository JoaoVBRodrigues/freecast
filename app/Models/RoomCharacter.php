<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomCharacter extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['notes' => '', 'attributes' => '[]', 'resources' => '[]', 'hp_current' => 0, 'hp_max' => 0, 'is_public' => false, 'revision' => 1];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'resources' => 'array', 'is_public' => 'boolean', 'revision' => 'integer', 'hp_current' => 'integer', 'hp_max' => 'integer'];
    }

    public function portrait(): BelongsTo
    {
        return $this->belongsTo(RoomAsset::class, 'portrait_asset_id');
    }
}
