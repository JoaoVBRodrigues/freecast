<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\Dd2vttImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MapImportController extends Controller
{
    public function preview(Request $request, Room $room)
    {
        Gate::authorize('manage', $room);
        $r = $request->validate(['file' => ['required', 'file', 'max:29696']]);
        $file = $r['file'];
        abort_unless(strtolower($file->getClientOriginalExtension()) === 'dd2vtt', 422, 'Selecione um arquivo .dd2vtt.');
        $title = mb_substr(trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 0, 100) ?: 'Mapa importado';
        $p = app(Dd2vttImportService::class)->prepare($room, $request->user(), $file->get(), $title);

        return response()->json(['preview' => ['id' => $p->id, 'title' => $p->title, 'expires_at' => $p->expires_at->toIso8601String(), 'image_url' => route('maps.import.image', [$room, $p->id]), ...array_intersect_key($p->validated, array_flip(['width', 'height', 'cell_size', 'counts', 'warnings']))]]);
    }

    public function image(Request $request, Room $room, string $preview)
    {
        $p = app(Dd2vttImportService::class)->authorized($room, $request->user(), $preview);
        abort_if($p->confirmed_at || ! Storage::disk('local')->exists($p->image_path), 410);

        return Storage::disk('local')->response($p->image_path, 'preview', ['Content-Type' => $p->validated['mime'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], 'inline');
    }

    public function confirm(Request $request, Room $room, string $preview)
    {
        $result = app(Dd2vttImportService::class)->confirm($room, $request->user(), $preview);

        return response()->json([...$result, 'url' => route('maps.show', [$room, $result['mapId']])]);
    }

    public function cancel(Request $request, Room $room, string $preview)
    {
        app(Dd2vttImportService::class)->cancel($room, $request->user(), $preview);

        return response()->noContent();
    }
}
