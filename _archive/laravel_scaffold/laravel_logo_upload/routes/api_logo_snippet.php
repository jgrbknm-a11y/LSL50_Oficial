<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TeamController;

// Paste into routes/api.php (proteger con auth:sanctum)
Route::post('/teams/{slug}/logo', [TeamController::class, 'uploadLogo']);
Route::post('/teams/{slug}/banner', [TeamController::class, 'uploadBanner']);
Route::post('/teams/{slug}/badge', [TeamController::class, 'uploadBadge']);
