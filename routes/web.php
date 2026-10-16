<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('dashboard');
    Route::get('/mesas', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/mesas/nova', [RoomController::class, 'create'])->name('rooms.create');
    Route::post('/mesas', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/mesas/{room}', [RoomController::class, 'show'])->whereNumber('room')->name('rooms.show');
    Route::post('/mesas/{room}/entrar', [RoomController::class, 'join'])->whereNumber('room')->name('rooms.join');
    Route::post('/mesas/{room}/sair', [RoomController::class, 'leave'])->whereNumber('room')->name('rooms.leave');
    Route::post('/mesas/{room}/favorita', [RoomController::class, 'toggleFavorite'])->whereNumber('room')->name('rooms.favorite');
    Route::patch('/mesas/{room}/preferencias', [RoomController::class, 'updatePreferences'])->whereNumber('room')->name('rooms.preferences');
    Route::post('/mesas/{room}/mensagens', [RoomController::class, 'storeMessage'])->whereNumber('room')->name('rooms.messages.store');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
