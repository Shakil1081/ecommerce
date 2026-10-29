<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Providers\CommonServiceProvider;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    /**
     * Test that modular autoloading and CommonServiceProvider are loaded.
     */
    public function test_modular_common_service_provider_is_registered(): void
    {
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(
            CommonServiceProvider::class,
            $providers,
            'CommonServiceProvider is registered in the Laravel application container.'
        );
    }

    /**
     * Test application health endpoint.
     */
    public function test_application_up_health_endpoint(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }

    /**
     * Test welcome route returns successful Inertia response with required props.
     */
    public function test_welcome_route_renders_via_inertia(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('laravelVersion')
            ->has('phpVersion')
        );
    }
}
