<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TeamController;

// Paste into routes/api.php
Route::get('/teams', [TeamController::class, 'index']);        // list + ?q=search
Route::get('/teams/{slug}', [TeamController::class, 'show']);  // show

Route::post('/teams', [TeamController::class, 'store']);       // create
Route::put('/teams/{slug}', [TeamController::class, 'update']); // full update
Route::patch('/teams/{slug}', [TeamController::class, 'update']); // partial update
Route::delete('/teams/{slug}', [TeamController::class, 'destroy']); // delete
