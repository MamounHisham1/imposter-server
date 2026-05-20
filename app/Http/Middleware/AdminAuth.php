<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        $password = config('app.admin_password');

        // Session-based auth persists after first login
        if (session('admin_authed')) {
            return $next($request);
        }

        // One-time login via token query param
        if (! empty($password) && $request->query('token') === $password) {
            session(['admin_authed' => true]);

            // Strip token from URL and redirect
            return redirect($request->url());
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return redirect('/')->withErrors(['error' => 'Unauthorized – admin password not set or invalid.']);
    }
}
