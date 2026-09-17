<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NoteController;

Route::get('/notes', [NoteController::class, 'index']);

Route::post('/notes/sync', [NoteController::class, 'sync']);

Route::post('/notes/resolve/local', [
    NoteController::class,
    'resolveKeepLocal'
]);

Route::post('/notes/resolve/server', [
    NoteController::class,
    'resolveKeepServer'
]);