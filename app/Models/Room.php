<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'title',
        'system',
        'description',
        'is_public',
        'password_hash',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(RoomMembership::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(RoomMembership::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(RoomFavorite::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(RoomVisit::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RoomMessage::class);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(RoomPreference::class);
    }

    public function maps(): HasMany
    {
        return $this->hasMany(RoomMap::class);
    }

    public function assetFolders(): HasMany
    {
        return $this->hasMany(RoomAssetFolder::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(RoomAsset::class);
    }
}
