<?php

namespace App\Http\Controllers;

use App\Models\{Room, RoomMap, RoomMapDrawing};
use App\Services\{MapVisibility, RoomSyncService};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MapToolsController extends Controller
{
    public function command(Request $request, Room $room, RoomMap $map, RoomSyncService $sync): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 32768, 413, 'Operação excede 32 KiB.');
        Gate::authorize('view', $room);
        abort_unless($map->room_id === $room->id, 404);
        $input = $request->validate([
            'action' => ['required', Rule::in(['draw', 'delete', 'fog', 'scale'])],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'drawing_id' => ['required_if:action,delete', 'integer'],
            'drawing_revision' => ['required_if:action,delete', 'integer', 'min:1'],
            'kind' => ['required_if:action,draw', Rule::in(['line', 'rectangle', 'ellipse', 'freehand'])],
            'color' => ['required_if:action,draw', Rule::in(['#eb3449', '#ffffff', '#ffd166', '#55c1ff', '#67d391', '#b692ff'])],
            'stroke_width' => ['required_if:action,draw', 'integer', Rule::in([2, 4, 8, 12])],
            'points' => ['required_if:action,draw', 'array', 'min:2', 'max:256'],
            'points.*' => ['array:x,y'],
            'points.*.x' => ['required', 'numeric', 'between:0,8000'],
            'points.*.y' => ['required', 'numeric', 'between:0,8000'],
            'mode' => ['required_if:action,fog', Rule::in(['enable', 'disable', 'reveal', 'cover', 'reveal_all', 'cover_all'])],
            'rect' => ['required_if:mode,reveal,cover', 'array:x,y,width,height'],
            'rect.x' => ['required_with:rect', 'numeric', 'between:0,8000'],
            'rect.y' => ['required_with:rect', 'numeric', 'between:0,8000'],
            'rect.width' => ['required_with:rect', 'numeric', 'gt:0', 'max:8000'],
            'rect.height' => ['required_with:rect', 'numeric', 'gt:0', 'max:8000'],
            'confirmed' => ['sometimes', 'boolean'],
            'distance_scale' => ['required_if:action,scale', 'numeric', 'between:0.001,1000000'],
            'distance_unit' => ['required_if:action,scale', 'string', 'max:20', 'regex:/^[\pL\pN ._-]+$/u'],
        ]);
        if (in_array($input['action'], ['fog', 'scale'])) Gate::authorize('manage', $room);
        return response()->json($sync->execute($room, $request->user(), 'map-tools', $input['idempotency_key'], ['map_id' => $map->id, ...$input],
            function ($commandId) use ($request, $room, $map, $sync, $input) {
                $map = RoomMap::query()->whereKey($map->id)->lockForUpdate()->firstOrFail();
                if (in_array($input['action'], ['fog', 'scale'])) Gate::authorize('manage', $room);
                if ($map->revision !== $input['expected_revision']) throw new ConflictHttpException('Mapa alterado. Atualize antes de tentar novamente.');
                if ($input['action'] === 'draw') {
                    abort_if(RoomMapDrawing::where('room_map_id', $map->id)->count() >= 500, 422, 'Limite de 500 desenhos por mapa.');
                    abort_if($input['kind'] !== 'freehand' && count($input['points']) !== 2, 422);
                    foreach ($input['points'] as $point) abort_if($point['x'] > $map->width || $point['y'] > $map->height, 422);
                    $input['points'] = array_map(fn ($p) => ['x' => (float) $p['x'], 'y' => (float) $p['y']], $input['points']);
                    $drawing = new RoomMapDrawing(['room_map_id' => $map->id, 'created_by' => $request->user()->id, ...array_intersect_key($input, array_flip(['kind', 'color', 'stroke_width', 'points']))]);
                    abort_unless(Gate::allows('manage', $room) || app(MapVisibility::class)->drawing($map, $drawing), 403);
                    $drawing->save();
                } elseif ($input['action'] === 'delete') {
                    $drawing = RoomMapDrawing::where('room_map_id', $map->id)->whereKey($input['drawing_id'])->firstOrFail();
                    abort_unless(Gate::allows('manage', $room) || ($drawing->created_by === $request->user()->id && app(MapVisibility::class)->drawing($map, $drawing)), 403);
                    if ($drawing->revision !== $input['drawing_revision']) throw new ConflictHttpException('Desenho alterado.');
                    $drawing->delete();
                } elseif ($input['action'] === 'scale') {
                    $map->distance_scale = $input['distance_scale']; $map->distance_unit = $input['distance_unit'];
                } else {
                    $mode = $input['mode'];
                    abort_if(in_array($mode, ['reveal_all', 'cover_all']) && ! ($input['confirmed'] ?? false), 422, 'Confirmação obrigatória.');
                    if (in_array($mode, ['enable', 'disable'])) $map->fog_enabled = $mode === 'enable';
                    else {
                        $columns = (int) ceil($map->width / MapVisibility::CELL); $rows = (int) ceil($map->height / MapVisibility::CELL);
                        $cells = array_fill_keys($map->fog_revealed ?? [], true);
                        if ($mode === 'cover_all') $cells = [];
                        elseif ($mode === 'reveal_all') $cells = array_fill_keys(range(0, $columns * $rows - 1), true);
                        else {
                            $r = $input['rect'];
                            abort_if($r['x'] + $r['width'] > $map->width || $r['y'] + $r['height'] > $map->height, 422);
                            for ($y = (int) floor($r['y'] / 64); $y < (int) ceil(($r['y'] + $r['height']) / 64); $y++) {
                                for ($x = (int) floor($r['x'] / 64); $x < (int) ceil(($r['x'] + $r['width']) / 64); $x++) {
                                    $i = $y * $columns + $x;
                                    if ($mode === 'reveal') $cells[$i] = true; else unset($cells[$i]);
                                }
                            }
                        }
                        $map->fog_revealed = array_map('intval', array_keys($cells));
                    }
                }
                $map->revision++; $map->save();
                $sync->record($room, 'room', 'map.contents.changed', null, null, $commandId, $input['idempotency_key'], ['map_id' => $map->id]);
                return ['revision' => $map->revision];
            }));
    }
}
