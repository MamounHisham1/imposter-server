<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddLinkHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->path() === '/') {
            $baseUrl = config('app.url');

            $links = [
                '</.well-known/api-catalog>; rel="api-catalog"; type="application/linkset+json"',
                '</.well-known/agent-skills/index.json>; rel="service-doc"; type="application/json"',
            ];

            $response->headers->set('Link', implode(', ', $links));
        }

        return $response;
    }
}
