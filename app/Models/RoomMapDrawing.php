<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomMapDrawing extends Model
{
    protected $fillable = ['room_map_id', 'created_by', 'kind', 'color', 'stroke_width', 'points', 'revision'];
    protected function casts(): array { return ['points' => 'array', 'revision' => 'integer']; }
}
