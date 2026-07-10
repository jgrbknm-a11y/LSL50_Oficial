<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TeamRegistrationController;

// Paste into routes/api.php (admin endpoints)
Route::post('/team-registrations', [TeamRegistrationController::class, 'store']); // pública (o con captcha)
Route::get('/team-registrations', [TeamRegistrationController::class, 'index'])->middleware('auth:sanctum');
Route::post('/team-registrations/{id}/approve', [TeamRegistrationController::class, 'approve'])->middleware('auth:sanctum');
Route::post('/team-registrations/{id}/reject', [TeamRegistrationController::class, 'reject'])->middleware('auth:sanctum');
