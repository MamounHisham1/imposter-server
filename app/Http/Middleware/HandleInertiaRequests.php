<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function handle($request, \Closure $next)
    {
        // Set locale from session (room language) or request input or app default
        $locale = session('locale')
            ?? $request->input('language')
            ?? $request->query('language')
            ?? app()->getLocale();

        if (in_array($locale, ['en', 'ar'])) {
            app()->setLocale($locale);
        }

        return parent::handle($request, $next);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'locale' => app()->getLocale(),
        ]);
    }
}
