<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $password = config('app.admin_password');
        $token = $request->query('token') ?? $request->header('X-Admin-Token');

        if (empty($password) || $token !== $password) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
