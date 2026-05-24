<?php

use App\Http\Middleware\AddLinkHeaders;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MarkdownNegotiation;
use App\Http\Middleware\TrackPageView;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            MarkdownNegotiation::class,
            AddLinkHeaders::class,
            HandleInertiaRequests::class,
            TrackPageView::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            '/broadcasting/auth',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if (in_array($response->getStatusCode(), [200, 201, 204, 301, 302, 422])) {
                return $response;
            }

            if ($response->getStatusCode() === 419) {
                return back()->with([
                    'message' => 'The page expired, please try again.',
                ]);
            }

            if (in_array($response->getStatusCode(), [400, 403, 404, 500, 503])) {
                if ($request->hasHeader('X-Inertia') || $request->acceptsHtml()) {
                    $status = $response->getStatusCode();
                    if ($status === 503) {
                        $status = 500;
                    }

                    return Inertia::render('Error', [
                        'status' => $status,
                    ])
                        ->toResponse($request)
                        ->setStatusCode($response->getStatusCode());
                }
            }

            return $response;
        });
    })->create();
