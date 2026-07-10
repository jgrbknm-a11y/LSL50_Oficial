<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController;

// Ruta principal para probar conexión
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'ok'   => true,
        'role' => 'web',
        'app'  => config('app.name'),
        'ts'   => now()->toDateTimeString(),
    ]);
});

// =============================
//  RUTAS DE AUTENTICACIÓN
// =============================

/*  ====== Rutas de autenticación y admin (temporalmente comentadas) ======
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'is_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    });
========================================================================= */