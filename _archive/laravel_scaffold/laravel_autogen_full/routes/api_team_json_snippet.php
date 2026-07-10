<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Sirve el team.json como endpoint público (además de la API Eloquent)
Route::get('/public/teams/{slug}/json', function (string $slug) {
    $path = "public/teams/{$slug}/team.json";
    if (!Storage::exists($path)) {
        abort(404, 'team.json not found');
    }
    return response()->file(storage_path('app/'.$path));
});
