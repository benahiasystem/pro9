<?php

namespace Tests\Unit;

use App\Models\Tenant\Person;
use App\Models\Tenant\User;
use App\Services\CentrifugoService;
use App\Services\CentrifugoTokenService;
use App\Tenancy\Contracts\CurrentHostname;
use App\Tenancy\Models\Hostname;
use Illuminate\Config\Repository;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Modules\Restaurant\Http\Controllers\RealtimeController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Isolated container: no Laravel Kernel, .env, database, queue or external HTTP. */
class CentrifugoSecurityTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('config', new Repository(['centrifugo' => [
            'url' => 'http://centrifugo.test:8000',
            'api_key' => 'test-private-api-key',
            'token_hmac_secret_key' => str_repeat('test-secret-', 4),
            'token_ttl' => 300,
        ]]));
        $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,
            new ResponseFactory(Mockery::mock(ViewFactory::class), Mockery::mock(Redirector::class)));
        $this->app->instance(HttpFactory::class, new HttpFactory());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $this->hostname('one.test', 1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    private function hostname(string $fqdn, int $websiteId): void
    {
        $host = new Hostname();
        $host->fqdn = $fqdn;
        $host->website_id = $websiteId;
        $this->app->instance(CurrentHostname::class, $host);
    }

    private function request($user, string $host = 'one.test', array $input = []): Request
    {
        $request = Request::create('http://'.$host.'/api/restaurant/realtime/token', 'GET', $input);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    private function actor(string $type = 'admin', bool $locked = false): User
    {
        return (new User())->forceFill(['id' => 1, 'type' => $type, 'locked' => $locked]);
    }

    private function claims(string $jwt): array
    {
        [$header, $payload, $signature] = explode('.', $jwt);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $header.'.'.$payload,
            config('centrifugo.token_hmac_secret_key'), true)), '+/', '-_'), '=');
        self::assertSame($expected, $signature);
        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_tokens_are_short_lived_signed_and_bound_to_the_resolved_company(): void
    {
        $controller = new RealtimeController();
        $response = $controller->token($this->request($this->actor(), 'one.test', [
            'channel' => 'restaurant:two.test', 'website_id' => 2, 'user_id' => 99,
        ]), new CentrifugoTokenService());
        $first = $response->getData(true);
        self::assertSame('restaurant:one.test', $first['channel']);
        $connection = $this->claims($first['token']);
        $subscription = $this->claims($first['subscription_token']);
        self::assertSame('website:1:user:1', $connection['sub']);
        self::assertArrayNotHasKey('channel', $connection);
        self::assertSame('restaurant:one.test', $subscription['channel']);
        self::assertSame(300, $connection['exp'] - $connection['iat']);
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        // Same local user ID in another company must have a different subject and channel.
        $this->hostname('two.test', 2);
        $second = $controller->token($this->request($this->actor(), 'two.test'), new CentrifugoTokenService())->getData(true);
        self::assertSame('website:2:user:1', $this->claims($second['token'])['sub']);
        self::assertSame('restaurant:two.test', $this->claims($second['subscription_token'])['channel']);
    }

    /** @dataProvider rejectedActors */
    public function test_untrusted_actors_cannot_get_realtime_credentials($kind): void
    {
        $user = match ($kind) {
            'guest' => null,
            'ecommerce' => new Person(['id' => 1]),
            'locked' => $this->actor('admin', true),
            'without_role' => Mockery::mock(User::class)->makePartial(),
        };
        if ($kind === 'without_role') {
            $user->type = 'seller';
            $user->locked = false;
            $relation = Mockery::mock();
            $relation->shouldReceive('exists')->once()->andReturn(false);
            $user->shouldReceive('restaurant_role')->once()->andReturn($relation);
        }
        $tokens = Mockery::mock(CentrifugoTokenService::class);
        $tokens->shouldNotReceive('issue');
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        (new RealtimeController())->token($this->request($user), $tokens);
    }

    public static function rejectedActors(): array
    {
        return array_map(fn ($kind) => [$kind], ['guest', 'ecommerce', 'locked', 'without_role']);
    }

    public function test_a_waiter_with_a_restaurant_role_can_get_credentials(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 5;
        $user->type = 'seller';
        $user->locked = false;
        $relation = Mockery::mock();
        $relation->shouldReceive('exists')->once()->andReturn(true);
        $user->shouldReceive('restaurant_role')->once()->andReturn($relation);
        $response = (new RealtimeController())->token($this->request($user), new CentrifugoTokenService());
        self::assertSame('website:1:user:5', $this->claims($response->getData(true)['token'])['sub']);
    }

    public function test_a_mismatched_tenant_context_is_denied_before_signing(): void
    {
        $tokens = Mockery::mock(CentrifugoTokenService::class);
        $tokens->shouldNotReceive('issue');
        $this->expectException(HttpException::class);
        (new RealtimeController())->token($this->request($this->actor(), 'two.test'), $tokens);
    }

    public function test_missing_secret_fails_closed(): void
    {
        config(['centrifugo.token_hmac_secret_key' => '']);
        $this->expectException(\RuntimeException::class);
        (new CentrifugoTokenService())->issue($this->actor(), 'one.test', 1);
    }

    /** @dataProvider rejectedPublications */
    public function test_rejected_publications_are_logged_without_credentials_or_payload($body, int $status): void
    {
        Http::fake(['*' => Http::response($body, $status)]);
        Log::shouldReceive('warning')->once()->with('Centrifugo publish rejected', Mockery::on(function ($context) use ($status) {
            return $context['status'] === $status && !isset($context['data']) && !isset($context['api_key']);
        }));
        (new CentrifugoService())->publish('restaurant:one.test', ['event' => 'stock-updated', 'payload' => []]);
        Http::assertSent(fn ($request) => $request->url() === 'http://centrifugo.test:8000/api/publish'
            && $request->hasHeader('X-API-Key', 'test-private-api-key')
            && $request['channel'] === 'restaurant:one.test');
    }

    public static function rejectedPublications(): array
    {
        return [
            'unauthorized' => ['', 401],
            'unknown_namespace' => [['error' => ['code' => 102, 'message' => 'unknown channel']], 200],
            'invalid_response' => ['<html>proxy error</html>', 200],
        ];
    }

    public function test_successful_publication_does_not_log_a_failure(): void
    {
        Http::fake(['*' => Http::response(['result' => (object) []], 200)]);
        Log::shouldReceive('warning')->never();
        (new CentrifugoService())->publish('restaurant:one.test', ['event' => 'stock-updated']);
        Http::assertSentCount(1);
    }
}
