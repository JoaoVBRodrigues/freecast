<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAsset extends Model
{
    public const KIND_BACKGROUND = 'background';

    public const KIND_TOKEN = 'token';

    protected $fillable = [
        'room_id',
        'folder_id',
        'uploaded_by',
        'kind',
        'name',
        'path',
        'mime_type',
        'size',
        'width',
        'height',
        'is_master_only',
    ];

    protected function casts(): array
    {
        return ['is_master_only' => 'boolean'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(RoomAssetFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
