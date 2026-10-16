<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\RoomAssetFolder;
use App\Models\RoomMap;
use App\Models\RoomMapObject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MapLibraryController extends Controller
{
    public function storeFolder(Request $request, Room $room): RedirectResponse
    {
        $this->authorizeRoom($request, $room, 'manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'parent_id' => ['nullable', 'integer', 'exists:room_asset_folders,id'],
            'is_master_only' => ['sometimes', 'boolean'],
        ]);

        $parent = isset($data['parent_id']) ? RoomAssetFolder::query()->findOrFail($data['parent_id']) : null;
        abort_if($parent && $parent->room_id !== $room->id, 404);

        $room->assetFolders()->create([
            'parent_id' => $parent?->id,
            'name' => $data['name'],
            'is_master_only' => (bool) ($parent?->is_master_only || ($data['is_master_only'] ?? false)),
        ]);

        return back();
    }

    public function updateFolder(Request $request, Room $room, RoomAssetFolder $folder): RedirectResponse
    {
        $this->authorizeRoom($request, $room, 'manage');
        $this->assertFolderBelongsToRoom($room, $folder);
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $folder->update($data);

        return back();
    }

    public function destroyFolder(Request $request, Room $room, RoomAssetFolder $folder): RedirectResponse
    {
        $this->authorizeRoom($request, $room, 'manage');
        $this->assertFolderBelongsToRoom($room, $folder);

        if ($folder->children()->exists() || $folder->assets()->exists()) {
            throw ValidationException::withMessages([
                'folder' => 'Mova ou remova os itens desta pasta antes de excluí-la.',
            ]);
        }

        $folder->delete();

        return back();
    }

    public function storeAsset(Request $request, Room $room): RedirectResponse
    {
        $this->authorizeRoom($request, $room, 'manage');
        $data = $request->validate([
            'file' => [
                'required',
                File::image()->max(20 * 1024)->dimensions(
                    Rule::dimensions()->minWidth(1)->minHeight(1)->maxWidth(8192)->maxHeight(8192),
                ),
                'mimes:jpg,jpeg,png,webp,gif',
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'kind' => ['required', Rule::in([RoomAsset::KIND_BACKGROUND, RoomAsset::KIND_TOKEN])],
            'folder_id' => ['nullable', 'integer', 'exists:room_asset_folders,id'],
            'is_master_only' => ['sometimes', 'boolean'],
        ]);

        $folder = isset($data['folder_id']) ? RoomAssetFolder::query()->findOrFail($data['folder_id']) : null;
        abort_if($folder && $folder->room_id !== $room->id, 404);
        $file = $data['file'];
        $imageInfo = getimagesize($file->getRealPath());

        if ($imageInfo === false || ! isset($imageInfo[0], $imageInfo[1], $imageInfo['mime'])) {
            throw ValidationException::withMessages(['file' => 'Não foi possível ler as dimensões desta imagem.']);
        }

        $path = $file->store("rooms/{$room->id}/library", 'local');
        abort_if(! is_string($path) || $path === '', 500, 'Não foi possível salvar a imagem no disco privado.');

        try {
            $asset = $room->assets()->create([
                'folder_id' => $folder?->id,
                'uploaded_by' => $request->user()->id,
                'kind' => $data['kind'],
                'name' => trim($data['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
                'path' => $path,
                'mime_type' => $imageInfo['mime'],
                'size' => $file->getSize(),
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'is_master_only' => (bool) ($folder?->is_master_only || $request->boolean('is_master_only')),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('uploaded_asset_id', $asset->id);
    }

    public function destroyAsset(Request $request, Room $room, RoomAsset $asset): RedirectResponse
    {
        $this->authorizeRoom($request, $room, 'manage');
        $this->assertAssetBelongsToRoom($room, $asset);

        if (
            RoomMap::query()->where('background_asset_id', $asset->id)->exists()
            || RoomMapObject::query()->where('room_asset_id', $asset->id)->exists()
        ) {
            throw ValidationException::withMessages([
                'asset' => 'Esta imagem está sendo usada por um mapa. Remova suas referências primeiro.',
            ]);
        }

        if (! Storage::disk('local')->delete($asset->path)) {
            abort(500, 'Não foi possível remover a imagem do disco privado.');
        }

        $asset->delete();

        return back();
    }

    public function showAsset(Request $request, Room $room, RoomAsset $asset): StreamedResponse
    {
        $this->authorizeRoom($request, $room, 'view');
        $this->assertAssetBelongsToRoom($room, $asset);
        $asset->loadMissing('folder');

        if ($asset->is_master_only || $asset->folder?->is_master_only) {
            Gate::authorize('manage', $room);
        }

        abort_unless(Storage::disk('local')->exists($asset->path), 404);

        return Storage::disk('local')->response($asset->path, $asset->name, [
            'Content-Type' => $asset->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private function authorizeRoom(Request $request, Room $room, string $ability): void
    {
        Gate::authorize($ability, $room);
    }

    private function assertFolderBelongsToRoom(Room $room, RoomAssetFolder $folder): void
    {
        abort_unless($folder->room_id === $room->id, 404);
    }

    private function assertAssetBelongsToRoom(Room $room, RoomAsset $asset): void
    {
        abort_unless($asset->room_id === $room->id, 404);
    }
}
