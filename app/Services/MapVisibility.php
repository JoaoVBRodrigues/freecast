<?php

namespace App\Services;

use App\Models\{RoomMap, RoomMapObject, RoomMapDrawing, RoomCharacter};

/** Conservative server projection. Fixed fog cells do not change with visual grid. */
class MapVisibility
{
    public const CELL = 64;

    public function point(RoomMap $map, float $x, float $y): bool
    {
        if (! $map->fog_enabled) return true;
        if ($x < 0 || $y < 0 || $x >= $map->width || $y >= $map->height) return false;
        $index = (int) floor($y / self::CELL) * (int) ceil($map->width / self::CELL) + (int) floor($x / self::CELL);
        return in_array($index, $map->fog_revealed ?? [], true);
    }

    public function object(RoomMapObject $object, ?RoomMap $map = null, ?array $position = null): bool
    {
        $map ??= $object->map;
        return $object->visible && $object->layer->visible && ! $object->layer->is_master_only
            && ! $object->asset->is_master_only && ! $object->asset->folder?->is_master_only
            && $object->asset->room_id === $map->room_id
            && $this->point($map, ($position['x'] ?? $object->x) + $object->width / 2, ($position['y'] ?? $object->y) + $object->height / 2);
    }

    public function character(RoomCharacter $character): bool
    {
        // Any covered linked token hides the entire sheet, even from its assignee.
        return ! RoomMapObject::query()->where('character_id', $character->id)->with('map')->get()
            ->contains(fn ($token) => ! $this->point($token->map, $token->x + $token->width / 2, $token->y + $token->height / 2));
    }

    public function drawing(RoomMap $map, RoomMapDrawing $drawing): bool
    {
        if (! $map->fog_enabled) return true;
        $xs = array_column($drawing->points, 'x'); $ys = array_column($drawing->points, 'y');
        // Entire bounding box must be revealed: no hidden geometry in HTTP.
        $pad = $drawing->stroke_width / 2;
        $left = min($xs) - $pad; $top = min($ys) - $pad;
        $right = max($xs) + $pad; $bottom = max($ys) + $pad;
        if ($left < 0 || $top < 0 || $right >= $map->width || $bottom >= $map->height) return false;
        $revealed = array_fill_keys($map->fog_revealed ?? [], true);
        $columns = (int) ceil($map->width / self::CELL);
        for ($y = floor($top / self::CELL); $y <= floor($bottom / self::CELL); $y++) {
            for ($x = floor($left / self::CELL); $x <= floor($right / self::CELL); $x++) {
                if (! isset($revealed[(int) ($y * $columns + $x)])) return false;
            }
        }
        return true;
    }
}
