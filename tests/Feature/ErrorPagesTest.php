<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a 404 Not Found exception returns the custom Inertia Error page.
     */
    public function test_404_not_found_returns_custom_inertia_error_page(): void
    {
        $response = $this->get('/non-existent-route-path-that-fails-with-404');

        $response->assertStatus(404);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404)
        );
    }

    /**
     * Test that a 403 Forbidden exception returns the custom Inertia Error page.
     */
    public function test_403_forbidden_returns_custom_inertia_error_page(): void
    {
        // We will register a temporary web route that aborts with 403
        $this->app['router']->get('/test-403-error-route', function () {
            abort(403);
        });

        $response = $this->get('/test-403-error-route');

        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 403)
        );
    }

    /**
     * Test that a 400 Bad Request exception returns the custom Inertia Error page.
     */
    public function test_400_bad_request_returns_custom_inertia_error_page(): void
    {
        // We will register a temporary web route that aborts with 400
        $this->app['router']->get('/test-400-error-route', function () {
            abort(400);
        });

        $response = $this->get('/test-400-error-route');

        $response->assertStatus(400);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 400)
        );
    }

    /**
     * Test that a 500 Internal Server Error returns the custom Inertia Error page.
     */
    public function test_500_server_error_returns_custom_inertia_error_page(): void
    {
        // We will register a temporary web route that aborts with 500
        $this->app['router']->get('/test-500-error-route', function () {
            abort(500);
        });

        $response = $this->get('/test-500-error-route');

        $response->assertStatus(500);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 500)
        );
    }

    /**
     * Test that a JSON request still returns standard JSON response instead of Inertia.
     */
    public function test_json_requests_get_standard_json_errors_instead_of_inertia(): void
    {
        $response = $this->getJson('/non-existent-route-path-that-fails-with-404');

        $response->assertStatus(404);
        // Should NOT be Inertia
        $response->assertHeaderMissing('X-Inertia');
    }
}
