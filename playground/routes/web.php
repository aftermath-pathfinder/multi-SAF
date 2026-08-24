<?php

use App\Http\Controllers\PlaygroundController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PlaygroundController::class, 'index'])->name('dashboard');
Route::get('/messages', [PlaygroundController::class, 'messages'])->name('messages');
Route::post('/publish', [PlaygroundController::class, 'publish'])->name('publish');
Route::post('/process', [PlaygroundController::class, 'process'])->name('process');
Route::post('/retry-dead', [PlaygroundController::class, 'retryDead'])->name('retry-dead');

Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
