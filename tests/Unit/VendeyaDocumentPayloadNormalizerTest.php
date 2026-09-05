<?php

namespace Tests\Unit;

use App\Support\Venezuela\VendeyaDocumentPayloadNormalizer;
use Tests\TestCase;

class VendeyaDocumentPayloadNormalizerTest extends TestCase
{
    public function test_it_normalizes_legacy_currency_and_iva_for_vendeya_invoices(): void
    {
        config()->set('venezuela.currency.id', 'VES');
        config()->set('venezuela.tax.rate', 0.16);

        $payload = VendeyaDocumentPayloadNormalizer::normalize([
            'source_module' => 'VENDEYA',
            'codigo_tipo_moneda' => 'PEN',
            'items' => [[
                'cantidad' => 1,
                'precio_unitario' => 116,
                'codigo_tipo_afectacion_igv' => '10',
                'porcentaje_igv' => 18,
                'total_item' => 116,
            ]],
            'totales' => [
                'total_igv' => 17.694915,
                'total_venta' => 116,
            ],
        ]);

        self::assertSame('VES', $payload['codigo_tipo_moneda']);
        self::assertSame(16.0, $payload['items'][0]['porcentaje_igv']);
        self::assertEqualsWithDelta(100, $payload['items'][0]['total_base_igv'], 0.000001);
        self::assertEqualsWithDelta(16, $payload['items'][0]['total_igv'], 0.000001);
        self::assertEqualsWithDelta(100, $payload['totales']['total_operaciones_gravadas'], 0.000001);
        self::assertEqualsWithDelta(16, $payload['totales']['total_igv'], 0.000001);
        self::assertEqualsWithDelta(116, $payload['totales']['total_venta'], 0.000001);
    }

    public function test_it_does_not_change_payloads_from_other_sources(): void
    {
        $payload = [
            'source_module' => 'EXTERNAL_API',
            'codigo_tipo_moneda' => 'USD',
            'items' => [],
        ];

        self::assertSame($payload, VendeyaDocumentPayloadNormalizer::normalize($payload));
    }

    public function test_api_transform_keeps_the_documents_endpoint_and_repairs_an_invalid_vendeya_seller(): void
    {
        $source = file_get_contents(base_path('app/CoreFacturalo/Requests/Api/Transform/DocumentTransform.php'));
        $routes = file_get_contents(base_path('routes/api.php'));

        self::assertStringContainsString("Route::post('documents', 'Tenant\\Api\\DocumentController@store')", $routes);
        self::assertStringContainsString("=== 'VENDEYA'", $source);
        self::assertStringContainsString('User::query()->whereKey($sellerId)->exists()', $source);
        self::assertStringContainsString('$inputs[\'codigo_vendedor\'] = auth()->id();', $source);
    }
}
