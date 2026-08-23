<?php

use App\Http\Controllers\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PlaygroundController::class, 'index'])->name('dashboard');
Route::get('/messages', [PlaygroundController::class, 'messages'])->name('messages');
Route::post('/publish', [PlaygroundController::class, 'publish'])->name('publish');
Route::post('/process', [PlaygroundController::class, 'process'])->name('process');
Route::post('/retry-dead', [PlaygroundController::class, 'retryDead'])->name('retry-dead');
