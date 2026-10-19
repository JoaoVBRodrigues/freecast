<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MapLibraryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomRealtimeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/mesas/{room}/personagens', [\App\Http\Controllers\CharacterController::class, 'index'])->name('characters.index');
    Route::post('/mesas/{room}/personagens/comandos', [\App\Http\Controllers\CharacterController::class, 'command'])->middleware('throttle:120,1')->name('characters.command');
    Route::get('/mesas/{room}/personagens/{character}/retrato', [\App\Http\Controllers\CharacterController::class, 'portrait'])->name('characters.portrait');
    Route::post('/mesas/{room}/combate/comandos', [\App\Http\Controllers\CharacterController::class, 'combat'])->middleware('throttle:120,1')->name('combat.command');
    Route::get('/', HomeController::class)->name('dashboard');
    Route::get('/mesas', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/mesas/nova', [RoomController::class, 'create'])->name('rooms.create');
    Route::post('/mesas', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/mesas/{room}', [RoomController::class, 'show'])->whereNumber('room')->name('rooms.show');
    Route::post('/mesas/{room}/entrar', [RoomController::class, 'join'])->whereNumber('room')->name('rooms.join');
    Route::post('/mesas/{room}/sair', [RoomController::class, 'leave'])->whereNumber('room')->name('rooms.leave');
    Route::delete('/mesas/{room}/participantes/{memberId}', [RoomController::class, 'removeMember'])->whereNumber(['room', 'memberId'])->name('rooms.members.destroy');
    Route::post('/mesas/{room}/favorita', [RoomController::class, 'toggleFavorite'])->whereNumber('room')->name('rooms.favorite');
    Route::patch('/mesas/{room}/preferencias', [RoomController::class, 'updatePreferences'])->whereNumber('room')->name('rooms.preferences');
    Route::get('/mesas/{room}/eventos', [RoomRealtimeController::class, 'events'])->whereNumber('room')->name('rooms.events');
    Route::get('/mesas/{room}/historico', [RoomRealtimeController::class, 'history'])->whereNumber('room')->name('rooms.messages.history');
    Route::post('/mesas/{room}/mensagens', [RoomRealtimeController::class, 'storeMessage'])->whereNumber('room')->name('rooms.messages.store');
    Route::post('/mesas/{room}/macros', [RoomRealtimeController::class, 'storeMacro'])->whereNumber('room')->name('rooms.macros.store');
    Route::patch('/mesas/{room}/macros/{macro}', [RoomRealtimeController::class, 'updateMacro'])->whereNumber(['room', 'macro'])->name('rooms.macros.update');
    Route::delete('/mesas/{room}/macros/{macro}', [RoomRealtimeController::class, 'deleteMacro'])->whereNumber(['room', 'macro'])->name('rooms.macros.destroy');
    Route::post('/mesas/{room}/macros/{macro}/executar', [RoomRealtimeController::class, 'executeMacro'])->whereNumber(['room', 'macro'])->name('rooms.macros.execute');
    Route::post('/mesas/{room}/mapas/{map}/ativar', [RoomRealtimeController::class, 'activateMap'])->whereNumber(['room', 'map'])->name('rooms.maps.activate');
    Route::post('/mesas/{room}/mapas/{map}/objetos/{object}/preview', [RoomRealtimeController::class, 'previewObject'])->middleware('throttle:120,1')->whereNumber(['room', 'map', 'object'])->name('maps.objects.preview');
    Route::get('/mesas/{room}/mapas', [MapController::class, 'index'])->whereNumber('room')->name('maps.index');
    Route::post('/mesas/{room}/mapas', [MapController::class, 'store'])->whereNumber('room')->name('maps.store');
    Route::get('/mesas/{room}/mapas/{map}', [MapController::class, 'show'])->whereNumber(['room', 'map'])->name('maps.show');
    Route::patch('/mesas/{room}/mapas/{map}', [MapController::class, 'update'])->whereNumber(['room', 'map'])->name('maps.update');
    Route::post('/mesas/{room}/mapas/{map}/duplicar', [MapController::class, 'duplicate'])->whereNumber(['room', 'map'])->name('maps.duplicate');
    Route::delete('/mesas/{room}/mapas/{map}', [MapController::class, 'destroy'])->whereNumber(['room', 'map'])->name('maps.destroy');
    Route::patch('/mesas/{room}/mapas/{map}/camadas/{layer}', [MapController::class, 'updateLayer'])->whereNumber(['room', 'map', 'layer'])->name('maps.layers.update');
    Route::post('/mesas/{room}/mapas/{map}/objetos', [MapController::class, 'storeObject'])->whereNumber(['room', 'map'])->name('maps.objects.store');
    Route::patch('/mesas/{room}/mapas/{map}/objetos/{object}', [MapController::class, 'updateObject'])->whereNumber(['room', 'map', 'object'])->name('maps.objects.update');
    Route::delete('/mesas/{room}/mapas/{map}/objetos/{object}', [MapController::class, 'destroyObject'])->whereNumber(['room', 'map', 'object'])->name('maps.objects.destroy');
    Route::post('/mesas/{room}/biblioteca/pastas', [MapLibraryController::class, 'storeFolder'])->whereNumber('room')->name('maps.folders.store');
    Route::patch('/mesas/{room}/biblioteca/pastas/{folder}', [MapLibraryController::class, 'updateFolder'])->whereNumber(['room', 'folder'])->name('maps.folders.update');
    Route::delete('/mesas/{room}/biblioteca/pastas/{folder}', [MapLibraryController::class, 'destroyFolder'])->whereNumber(['room', 'folder'])->name('maps.folders.destroy');
    Route::post('/mesas/{room}/biblioteca/recursos', [MapLibraryController::class, 'storeAsset'])->whereNumber('room')->name('maps.assets.store');
    Route::get('/mesas/{room}/recursos/{asset}', [MapLibraryController::class, 'showAsset'])->whereNumber(['room', 'asset'])->name('maps.assets.show');
    Route::delete('/mesas/{room}/biblioteca/recursos/{asset}', [MapLibraryController::class, 'destroyAsset'])->whereNumber(['room', 'asset'])->name('maps.assets.destroy');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
