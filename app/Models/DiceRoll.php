<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiceRoll extends Model
{
    protected $fillable = [
        'room_id',
        'user_id',
        'command_id',
        'roll_index',
        'expression',
        'components',
        'total',
    ];

    protected function casts(): array
    {
        return ['components' => 'array'];
    }
}
