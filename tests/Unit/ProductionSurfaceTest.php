<?php

namespace Tests\Unit;

use App\Http\Middleware\ProductionSurface;
use App\Tenancy\Contracts\CurrentHostname;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductionSurfaceTest extends TestCase
{
    private Container $previous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous = Container::getInstance();
        $app = new Application();
        $app->instance('env', 'production');
        $app->instance('config', new Repository(['app' => ['url_base' => 'example.test', 'url' => 'https://example.test']]));
        $app->bind(CurrentHostname::class, static fn () => null);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previous);
        parent::tearDown();
    }

    public function test_production_rejects_external_integration_controllers(): void
    {
        $request = $this->request('https://example.test/records', 'Modules\\Webhook\\Http\\Controllers\\WebhookController@records');
        $this->expectException(NotFoundHttpException::class);

        (new ProductionSurface())->handle($request, static fn () => 'dispatched');
    }

    public function test_production_rejects_unregistered_hosts(): void
    {
        $request = $this->request('https://unknown.example.test/login', 'App\\Http\\Controllers\\System\\LoginController@index');
        $this->expectException(NotFoundHttpException::class);

        (new ProductionSurface())->handle($request, static fn () => 'dispatched');
    }

    public function test_production_allows_local_sales(): void
    {
        $request = $this->request('https://example.test/documents', 'App\\Http\\Controllers\\Tenant\\DocumentController@store');

        self::assertSame('dispatched', (new ProductionSurface())->handle($request, static fn () => 'dispatched'));
    }

    private function request(string $url, string $action): Request
    {
        $request = Request::create($url);
        $request->setRouteResolver(static fn () => new Route('GET', $request->path(), ['uses' => $action, 'controller' => $action]));

        return $request;
    }
}
