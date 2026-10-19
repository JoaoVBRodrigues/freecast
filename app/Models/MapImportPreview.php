<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapImportPreview extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['image_path', 'image_hash', 'validated'];

    protected function casts(): array
    {
        return ['validated' => 'array', 'expires_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}
