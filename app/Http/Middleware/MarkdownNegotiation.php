<?php

namespace App\Http\Middleware;

use App\Http\Controllers\WellKnownController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarkdownNegotiation
{
    public function handle(Request $request, Closure $next): Response
    {
        $accept = $request->header('Accept', '');

        if (str_contains($accept, 'text/markdown')) {
            $controller = new WellKnownController;
            $path = '/'.$request->path();

            return $controller->markdownPage($request, $path === '//' ? '/' : $path);
        }

        return $next($request);
    }
}
