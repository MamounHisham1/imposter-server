<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        $password = env('ADMIN_PASSWORD');

        // No password configured — allow access (dev mode)
        if (! $password) {
            return $next($request);
        }

        // Session-based auth persists after first login
        if (session('admin_authed')) {
            return $next($request);
        }

        // One-time login via token query param
        if ($request->query('token') === $password) {
            session(['admin_authed' => true]);

            // Strip token from URL and redirect
            return redirect($request->url());
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return redirect('/')->withErrors(['error' => 'Unauthorized – append ?token=YOUR_PASSWORD to access.']);
    }
}
