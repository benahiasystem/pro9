<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal;
use App\Support\Venezuela\VendeyaDocumentPayloadNormalizer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendeyaDocumentPayloadNormalizerTest extends TestCase
{
    public function test_it_calculates_current_iva_for_vendeya_invoices(): void
    {
        config()->set('venezuela.currency.id', 'VES');
        config()->set('venezuela.tax.rate', 0.16);

        $payload = VendeyaDocumentPayloadNormalizer::normalize([
            'source_module' => 'VENDEYA',
            'codigo_tipo_moneda' => 'VES',
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

    /** @dataProvider retiredCurrencies */
    public function test_it_rejects_retired_or_missing_currency_without_converting_it(?string $currency): void
    {
        $this->expectException(ValidationException::class);
        VendeyaDocumentPayloadNormalizer::normalize([
            'source_module' => 'VENDEYA',
            'codigo_tipo_moneda' => $currency,
            'items' => [],
        ]);
    }

    public static function retiredCurrencies(): array
    {
        return [['PEN'], ['VED'], ['EUR'], [null]];
    }

    /** @dataProvider retiredAffectations */
    public function test_it_rejects_retired_affectations(string $affectation): void
    {
        try {
            VendeyaDocumentPayloadNormalizer::normalize([
                'source_module' => 'VENDEYA',
                'codigo_tipo_moneda' => 'VES',
                'items' => [['codigo_tipo_afectacion_igv' => $affectation]],
            ]);
            self::fail('A retired affectation must not be accepted.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('items.0.codigo_tipo_afectacion_igv', $exception->errors());
        }
    }

    public static function retiredAffectations(): array
    {
        return [['30'], ['40'], ['11'], ['21'], ['99']];
    }

    public function test_it_totals_taxed_and_exempt_usd_lines_with_the_configured_rate(): void
    {
        config()->set('venezuela.tax.rate', 0.08);
        $payload = VendeyaDocumentPayloadNormalizer::normalize([
            'source_module' => 'VENDEYA',
            'codigo_tipo_moneda' => 'USD',
            'items' => [
                ['cantidad' => 2, 'precio_unitario' => 54, 'codigo_tipo_afectacion_igv' => '10'],
                ['cantidad' => 3, 'precio_unitario' => 20, 'codigo_tipo_afectacion_igv' => '20'],
            ],
        ]);

        self::assertSame('USD', $payload['codigo_tipo_moneda']);
        self::assertSame(8.0, $payload['items'][0]['porcentaje_igv']);
        self::assertEqualsWithDelta(50, $payload['items'][0]['valor_unitario'], 0.000001);
        self::assertSame(0, $payload['items'][1]['total_igv']);
        self::assertEqualsWithDelta(60, $payload['items'][1]['total_item'], 0.000001);
        self::assertEqualsWithDelta(100, $payload['totales']['total_operaciones_gravadas'], 0.000001);
        self::assertEqualsWithDelta(60, $payload['totales']['total_operaciones_exoneradas'], 0.000001);
        self::assertEqualsWithDelta(8, $payload['totales']['total_impuestos'], 0.000001);
        self::assertEqualsWithDelta(160, $payload['totales']['total_valor'], 0.000001);
        self::assertEqualsWithDelta(168, $payload['totales']['total_venta'], 0.000001);
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

    public function test_vendeya_invoice_sums_declared_and_computed_amounts_as_decimals(): void
    {
        config()->set('venezuela.tax.rate', '0.08');
        $exempt = '9007199254740993.000000000001';
        $payload = VendeyaDocumentPayloadNormalizer::normalize([
            'source_module'=>'VENDEYA', 'codigo_tipo_documento'=>'01', 'codigo_tipo_moneda'=>'USD',
            'items'=>[
                ['cantidad'=>'1', 'precio_unitario'=>$exempt, 'total_item'=>$exempt, 'codigo_tipo_afectacion_igv'=>'20'],
                ['cantidad'=>'0.1', 'precio_unitario'=>'0.2', 'codigo_tipo_afectacion_igv'=>'10'],
            ],
        ]);

        self::assertSame($exempt, (string) $payload['items'][0]['total_item']);
        self::assertSame('0.02', (string) $payload['items'][1]['total_item']);
        self::assertSame(8.0, $payload['items'][1]['porcentaje_igv']);
        $base = Decimal::of('0.02')->dividedBy('1.08');
        self::assertSame(Decimal::represent($base), (string) $payload['totales']['total_operaciones_gravadas']);
        self::assertSame(Decimal::represent(Decimal::of('0.02')->minus($base)), (string) $payload['totales']['total_igv']);
        self::assertSame('9007199254740993.020000000001', (string) $payload['totales']['total_venta']);
    }
}
