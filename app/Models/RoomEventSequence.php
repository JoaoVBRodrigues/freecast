<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomEventSequence extends Model
{
    public $timestamps = false;

    protected $fillable = ['room_id', 'audience', 'sequence'];
}
