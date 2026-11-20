<?php

namespace Tests\Unit;

use Hyn\Tenancy\Contracts\CurrentHostname;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class PublicDocumentSearchRoutesTest extends TestCase
{
    /** @dataProvider searchStates */
    public function test_public_search_routes_follow_the_global_switch(bool $enabled): void
    {
        config(['tenant.public_document_search_enabled' => $enabled]);

        foreach (['bbc.localhost', 'hotel.localhost'] as $tenant) {
            $router = $this->loadRoutes($tenant);
            $this->assertSearchRoutes($router, $tenant, $enabled, [
                ['GET', '/buscar'], ['GET', '/search'], ['GET', '/search/tables'],
                ['POST', '/search'], ['POST', '/api/consultas/search'],
            ]);
            foreach (['GET', 'POST'] as $method) {
                $internal = $router->getRoutes()->match(Request::create("http://{$tenant}/consultas", $method));
                self::assertContains('auth', $internal->middleware());
                self::assertStringStartsWith('tenant.public_search.form', $internal->getName());
            }
            $download = $router->getRoutes()->match(Request::create("http://{$tenant}/downloads/document/pdf/fixture-id"));
            self::assertSame('tenant.download.external_id', $download->getName());
        }

        $base = env('APP_URL_BASE') ?: 'central.example.test';
        $prefix = env('PREFIX_URL');
        $central = $prefix ? $prefix.'.'.$base : $base;
        $this->assertSearchRoutes($this->loadRoutes(null), $central, $enabled, [
            ['GET', '/consultas'], ['POST', '/consultas'], ['GET', '/consultas/widget'],
            ['GET', '/consultas/widget/bbc.localhost'], ['POST', '/consultas/widget/bbc.localhost'],
            ['GET', '/consultas/embed.js'], ['POST', '/api/consultas/search'],
        ]);
    }

    public static function searchStates(): array
    {
        return ['suspended' => [false], 'enabled' => [true]];
    }

    private function loadRoutes(?string $tenant): Router
    {
        $this->app->instance(CurrentHostname::class, $tenant ? (new Hostname())->forceFill(['fqdn' => $tenant]) : null);
        $router = new Router($this->app['events'], $this->app);
        Route::swap($router);
        Route::namespace('App\Http\Controllers')->group(base_path('routes/web.php'));
        Route::prefix('api')->namespace('App\Http\Controllers')->group(base_path('routes/api.php'));

        return $router;
    }

    private function assertSearchRoutes(Router $router, string $host, bool $enabled, array $routes): void
    {
        foreach ($routes as [$method, $path]) {
            $request = Request::create("http://{$host}{$path}", $method);
            try {
                $route = $router->getRoutes()->match($request);
            } catch (NotFoundHttpException) {
                self::assertFalse($enabled, "{$method} {$host}{$path} debe estar disponible");
                continue;
            }

            self::assertTrue($enabled, "{$method} {$host}{$path} debe estar suspendida");
            self::assertStringContainsString('SearchController@', $route->getActionName());
        }
    }
}
