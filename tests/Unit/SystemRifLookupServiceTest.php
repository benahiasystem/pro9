<?php

namespace Tests\Unit;

use App\Exceptions\System\RifLookupException;
use App\Services\System\RifLookupService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// ########## INICIO CAMBIO RIF SUPER ADMIN
class SystemRifLookupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.super_admin_rif_lookup', [
            'enabled' => true,
            'url' => 'https://rif.example.test/contributors/{rif}',
            'token' => 'backend-secret',
            'timeout' => 10,
        ]);
    }

    /** @test */
    public function it_hides_lookup_when_configuration_is_disabled_or_incomplete(): void
    {
        $service = app(RifLookupService::class);
        $validConfiguration = config('services.super_admin_rif_lookup');

        foreach ([
            ['enabled' => false],
            ['url' => ''],
            ['url' => 'https://rif.example.test/contributors'],
            ['url' => 'https://rif.example.test/{rif}/{rif}'],
            ['token' => ''],
        ] as $override) {
            config()->set('services.super_admin_rif_lookup', array_merge(
                $validConfiguration,
                $override
            ));
            self::assertFalse($service->isAvailable());
        }
    }

    /** @test */
    public function it_sends_only_the_normalized_rif_and_backend_token_and_adapts_the_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'razon_social' => 'EMPRESA DEMO, C.A.',
                    'nombre_comercial' => 'DEMO',
                ],
            ]),
        ]);

        $result = app(RifLookupService::class)->lookup('j-123.456 789');

        self::assertSame([
            'name' => 'EMPRESA DEMO, C.A.',
            'trade_name' => 'DEMO',
        ], $result);
        Http::assertSent(static function (Request $request): bool {
            return $request->url() === 'https://rif.example.test/contributors/J123456789'
                && $request->hasHeader('Authorization', 'Bearer backend-secret');
        });
    }

    /** @test */
    public function it_rejects_invalid_rif_without_calling_the_provider(): void
    {
        Http::fake();

        try {
            app(RifLookupService::class)->lookup('X123');
            self::fail('Se esperaba una excepción de validación.');
        } catch (RifLookupException $exception) {
            self::assertSame(422, $exception->responseStatus());
        }

        Http::assertNothingSent();
    }

    /** @test */
    public function it_returns_controlled_errors_for_provider_statuses_and_invalid_payloads(): void
    {
        $cases = [
            [404, [], 404],
            [401, [], 502],
            [403, [], 502],
            [429, [], 503],
            [500, [], 502],
            [200, ['data' => ['trade_name' => 'SIN RAZÓN SOCIAL']], 502],
        ];
        foreach ($cases as [$providerStatus, $payload, $expectedStatus]) {
            $factory = new Factory();
            $factory->fake(['*' => Http::response($payload, $providerStatus)]);
            Http::swap($factory);

            try {
                app(RifLookupService::class)->lookup('J123456789');
                self::fail("Se esperaba error para HTTP {$providerStatus}.");
            } catch (RifLookupException $exception) {
                self::assertSame($expectedStatus, $exception->responseStatus(), "HTTP {$providerStatus}");
                self::assertStringNotContainsString('backend-secret', $exception->getMessage());
                self::assertStringNotContainsString('rif.example.test', $exception->getMessage());
            }
        }
    }

    /** @test */
    public function it_handles_invalid_json_and_connection_failures_without_exposing_secrets(): void
    {
        Http::fake(['*' => Http::response('not-json', 200, ['Content-Type' => 'application/json'])]);

        try {
            app(RifLookupService::class)->lookup('J123456789');
            self::fail('Se esperaba error por JSON inválido.');
        } catch (RifLookupException $exception) {
            self::assertSame(502, $exception->responseStatus());
        }

        Http::fake(static function (): void {
            throw new ConnectionException('timeout');
        });

        try {
            app(RifLookupService::class)->lookup('J123456789');
            self::fail('Se esperaba error de conexión.');
        } catch (RifLookupException $exception) {
            self::assertSame(503, $exception->responseStatus());
            self::assertStringNotContainsString('backend-secret', $exception->getMessage());
        }
    }
}
// ######### FIN CAMBIO RIF SUPER ADMIN
