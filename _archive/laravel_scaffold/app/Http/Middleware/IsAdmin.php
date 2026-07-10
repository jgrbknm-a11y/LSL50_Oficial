<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Verifica role_id = 1 en tabla pivot role_user
        $isAdmin = DB::table('role_user')
            ->where('user_id', $user->id)
            ->where('role_id', 1)
            ->exists();

        if (!$isAdmin) {
            abort(403, 'No autorizado.');
        }

        return $next($request);
    }
}