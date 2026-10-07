<?php
// ######## INICIO API BCV ########
namespace Tests\Unit;

use App\Services\ExchangeRates\TenantExchangeRateService;
use App\Http\Controllers\Tenant\Api\ServiceController;
use App\Http\Resources\Tenant\ExchangeRateCollection;
use App\Models\Tenant\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BcvExchangeRateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00:00', 'America/Caracas'));
        config()->set('services.bcv', ['url' => 'http://api-bcv:3000', 'token' => str_repeat('ab', 32), 'timeout' => 15, 'connect_timeout' => 5]);
        $this->tenant('tenant');
        $tenancy = $this->getMockBuilder(\Hyn\Tenancy\Database\Connection::class)->disableOriginalConstructor()->onlyMethods(['tenantName'])->getMock();
        $tenancy->method('tenantName')->willReturnCallback(fn () => config('tenancy.db.tenant-connection-name', 'tenant'));
        app()->instance(\Hyn\Tenancy\Database\Connection::class, $tenancy);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('tenant');
        DB::purge('tenant_b');
        parent::tearDown();
    }

    private function tenant(string $name): void
    {
        config()->set('database.connections.'.$name, ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge($name);
        Schema::connection($name)->create('exchange_rates', function ($table) {
            $table->date('date')->primary();
            $table->date('date_original');
            foreach (['sale', 'purchase', 'sale_original', 'purchase_original'] as $column) $table->decimal($column, 18, 8);
            $table->timestamps();
        });
    }

    public function test_today_is_saved_once_and_reused_without_http(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['dolar' => '873.86712345', 'euro' => '977.21900000'])]);
        $service = app(TenantExchangeRateService::class);
        $rate = $service->exchange('2026-10-07');
        self::assertSame(['date' => '2026-10-07', 'purchase' => '873.86712345', 'sale' => '873.86712345'], $rate);
        self::assertSame($rate, $service->exchange('2026-10-07'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.str_repeat('ab', 32)) && $request->url() === 'http://api-bcv:3000/');
        $record = ExchangeRate::first();
        self::assertSame('2026-10-07', $record->date_original);
        self::assertSame('873.86712345', $record->sale_original);
        self::assertSame(1, ExchangeRate::count());
    }

    public function test_history_is_local_and_future_or_invalid_dates_are_rejected(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake();
        DB::connection('tenant')->table('exchange_rates')->insert(['date' => '2026-10-06', 'date_original' => '2026-10-06',
            'sale' => 800, 'purchase' => 800, 'sale_original' => 800, 'purchase_original' => 800]);
        self::assertSame('800.00000000', app(TenantExchangeRateService::class)->exchange('2026-10-06')['sale']);
        foreach (['2026-10-05', '2026-10-08', '2026-02-30', '2026-1-01', null] as $date) {
            try { app(TenantExchangeRateService::class)->exchange($date); self::fail('Debe rechazar la fecha.'); }
            catch (ValidationException $exception) { self::assertArrayHasKey('date', $exception->errors()); }
        }
        Http::assertNothingSent();
    }

    public function test_invalid_or_failed_upstream_responses_never_insert(): void
    {
        foreach ([[['dolar' => 90], 401], [['error' => 'offline'], 500], [['dolar' => '90junk'], 200],
            [['dolar' => '0.000000001'], 200], [['dolar' => -5], 200], [['dolar' => 10000000000], 200], ['invalid json', 200]] as [$body, $status]) {
            Http::swap(new \Illuminate\Http\Client\Factory());
            Http::fake(['*' => Http::response($body, $status)]);
            try { app(TenantExchangeRateService::class)->exchange('2026-10-07'); self::fail('Debe rechazar la respuesta.'); }
            catch (HttpException $exception) { self::assertSame(503, $exception->getStatusCode()); }
            self::assertSame(0, ExchangeRate::count());
        }
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        try { app(TenantExchangeRateService::class)->exchange('2026-10-07'); self::fail('Debe rechazar timeout.'); }
        catch (HttpException $exception) { self::assertSame(503, $exception->getStatusCode()); }
        self::assertSame(0, ExchangeRate::count());
    }

    public function test_missing_token_and_storage_errors_return_safe_json(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['dolar' => '100.00000000'])]);
        config()->set('services.bcv.token', '');
        try { app(TenantExchangeRateService::class)->exchange('2026-10-07'); self::fail('Debe rechazar configuración incompleta.'); }
        catch (HttpException $exception) {
            $response = app(\App\Exceptions\Handler::class)->render(Request::create('/services/exchange/2026-10-07'), $exception);
            self::assertSame(503, $response->getStatusCode());
            self::assertArrayNotHasKey('file', json_decode($response->getContent(), true));
        }
        Http::assertNothingSent();
        self::assertSame(0, ExchangeRate::count());
        config()->set('services.bcv.token', str_repeat('ab', 32));
        Schema::connection('tenant')->table('exchange_rates', function ($table) { $table->string('required_test_column'); });
        try { app(TenantExchangeRateService::class)->exchange('2026-10-07'); self::fail('Debe rechazar error de guardado.'); }
        catch (HttpException $exception) { self::assertSame(503, $exception->getStatusCode()); }
        self::assertSame(0, ExchangeRate::count());
    }

    public function test_another_tenant_does_not_reuse_the_first_tenant_rate(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['dolar' => '100.00000000'])]);
        app(TenantExchangeRateService::class)->exchange('2026-10-07');
        $this->tenant('tenant_b');
        $first = DB::connection('tenant');
        app('db')->setDefaultConnection('tenant_b');
        // UsesTenantConnection resolves the configured tenant connection name.
        config()->set('tenancy.db.tenant-connection-name', 'tenant_b');
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['dolar' => '200.00000000'])]);
        try {
            self::assertSame('200.00000000', app(TenantExchangeRateService::class)->exchange('2026-10-07')['sale']);
            self::assertSame(100.0, (float) $first->table('exchange_rates')->value('sale'));
            self::assertSame(1, DB::connection('tenant_b')->table('exchange_rates')->count());
        } finally { config()->set('tenancy.db.tenant-connection-name', 'tenant'); }
    }

    public function test_get_post_and_listing_contracts(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['dolar' => '100.12540000'])]);
        $controller = app(ServiceController::class);
        self::assertSame('100.12540000', $controller->exchangeRateTest('2026-10-07')['sale']);
        $post = $controller->exchange_rate(Request::create('/', 'POST', ['cur_date' => '2026-10-07']));
        self::assertTrue($post['success']);
        self::assertSame('100.12540000', $post['data']['2026-10-07']['sell']);
        self::assertSame('100.12540000', $controller->searchExchangeRateByDate(Request::create('/', 'POST', ['date' => '2026-10-07']))['sale']);
        $list = (new ExchangeRateCollection(ExchangeRate::all()))->toArray(new Request());
        self::assertSame('100.12540000', $list[0]['buy']);
        self::assertSame('100.12540000', $list[0]['sell']);
        Http::assertSentCount(1);
    }
}
// ######## FIN API BCV ########
