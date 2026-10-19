<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomCombat extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['active' => false, 'round' => 0, 'participants' => '[]', 'revision' => 1];

    protected function casts(): array
    {
        return ['participants' => 'array', 'active' => 'boolean', 'round' => 'integer', 'revision' => 'integer'];
    }
}
