<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NoteController;

Route::post('/notes/sync', [NoteController::class, 'sync']);
