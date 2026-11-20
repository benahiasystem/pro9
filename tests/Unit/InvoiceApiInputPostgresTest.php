<?php

namespace Tests\Unit;

use App\CoreFacturalo\InputRequest;
use App\CoreFacturalo\Requests\Api\Transform\DocumentTransform;
use App\CoreFacturalo\Requests\Inputs\Transform\DocumentWebTransform;
use App\Http\Controllers\Tenant\Api\DocumentController;
use App\Services\Fiscal\FiscalIdentity;
use App\Services\Sellnow\ApiSaleCreation;
use Carbon\Carbon;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\PostgresTestEnvironment;

/** @group postgres-integration */
final class InvoiceApiInputPostgresTest extends TestCase
{
    private FiscalEmissionSchemaTest $fixture;

    protected function setUp(): void
    {
        $this->fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($this->fixture, 'setUp'))->invoke($this->fixture);
    }

    protected function tearDown(): void
    {
        try {
            (new ReflectionMethod($this->fixture, 'tearDown'))->invoke($this->fixture);
        } finally {
            parent::tearDown();
        }
    }

    public function test_api_validates_invoice_line_shape_and_unit_before_saving(): void
    {
        [$db, $payload] = $this->invoicePayload();
        $before = $db->table('item_warehouse')->get()->toArray();
        $invalidUnit = $payload['items'];
        $invalidUnit[0]['unidad_de_medida'] = ['UND'];
        foreach ([
            ['items.0.unidad_de_medida', $invalidUnit],
            ['items.0', ['UND']],
            ['items', 'UND'],
        ] as [$field, $items]) {
            $request = Request::create('/api/documents', 'POST', array_replace($payload, ['items' => $items]));
            try {
                (new InputRequest())->handle($request, fn () => self::fail('Una partida inválida llegó al guardado.'), 'document', 'api');
                self::fail('La estructura de la partida debe producir un error de validación.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey($field, $error->errors());
            }
            self::assertSame(0, $db->transactionLevel());
            self::assertSame(0, $db->table('documents')->count());
            self::assertEquals($before, $db->table('item_warehouse')->get()->toArray());
        }

        $payload['items'][0]['unidad_de_medida'] = 'und';
        $operation = (new InputRequest())->handle(Request::create('/api/documents', 'POST', $payload),
            fn (Request $input) => (new ApiSaleCreation())->persistDocument($input->all()), 'document', 'api');
        self::assertSame('UND', $operation->getDocument()->items()->sole()->item->unit_type_id);
        self::assertSame(1, $db->table('documents')->count());
    }

    public function test_vendeya_uses_the_active_actor_when_a_seller_key_is_invalid_or_absent(): void
    {
        [$db, $payload] = $this->invoicePayload();
        $payload['source_module'] = 'VENDEYA';
        $actor = auth()->id();
        self::assertNotNull($actor);

        foreach (['9223372036854775808', $actor.'x', '0'.$actor, [$actor], null] as $invalid) {
            $payload['codigo_vendedor'] = $invalid;
            self::assertSame($actor, DocumentTransform::transform($payload)['seller_id']);
        }

        $payload['codigo_vendedor'] = (string) $actor;
        self::assertSame((string) $actor, DocumentTransform::transform($payload)['seller_id']);
        self::assertSame(0, $db->table('documents')->count());
    }

    public function test_api_invoice_lookup_handles_invalid_bigint_keys_and_keeps_the_response_contract(): void
    {
        [$db, $payload] = $this->invoicePayload();
        $operation = (new InputRequest())->handle(Request::create('/api/documents', 'POST', $payload),
            fn (Request $input) => (new ApiSaleCreation())->persistDocument($input->all()), 'document', 'api');
        $invoice = $operation->getDocument()->fresh();
        $responses = $this->createMock(ResponseFactory::class);
        $responses->method('json')->willReturnCallback(static fn ($body, $status = 200) => new JsonResponse($body, $status));
        app()->instance(ResponseFactory::class, $responses);
        $controller = new DocumentController();
        $db->enableQueryLog();
        foreach (['9223372036854775808', $invoice->id.'x', '0'.$invoice->id, $invoice->id.'.0', '0'] as $invalid) {
            $db->flushQueryLog();
            $missing = $controller->record($invalid);
            self::assertSame(404, $missing->getStatusCode());
            self::assertFalse($missing->getData()->success);
            self::assertSame('No se encontró el comprobante solicitado.', $missing->getData()->message);
            self::assertSame([], $db->getQueryLog());
        }
        $db->disableQueryLog();
        self::assertSame(404, $controller->record('9223372036854775807')->getStatusCode());
        $result = $controller->record((string) $invoice->id);
        self::assertSame(200, $result->getStatusCode());
        self::assertSame($invoice->series, $result->getData()->data->series);
        self::assertSame($invoice->invoice->date_of_due->toDateString(), $result->getData()->data->date_of_due);
        self::assertSame('01', $result->getData()->data->document_type_id);
        self::assertSame(1, $db->table('documents')->count());

        $otherType = $invoice->replicate()->forceFill(['document_type_id' => '07', 'series' => 'FC01']);
        $otherType->unsetRelation('document_type');
        self::assertSame([['number' => $invoice->series]], FiscalIdentity::seriesForType([$otherType, $invoice, $invoice], '01')->all());
        self::assertSame([['number' => 'FC01']], FiscalIdentity::seriesForType([$invoice, $otherType], '07')->all());
        self::assertSame([], FiscalIdentity::seriesForType([$invoice, $otherType], '08')->all());
    }

    public function test_api_invoice_validation_resolves_the_series_and_checks_the_shipping_window(): void
    {
        [$db, $payload] = $this->invoicePayload();
        $db->table('configurations')->update(['restrict_receipt_date' => true, 'shipping_time_days' => 4]);
        $normalize = static function (array $data) {
            $request = Request::create('/api/documents', 'POST', $data);
            $request->attributes->set('sab.strict_input_exceptions', true);
            return (new InputRequest())->handle($request, static fn (Request $request) => $request->all(), 'document', 'api');
        };
        $previous = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::parse('2026-11-19 12:00:00'));
        try {
            $payload['fecha_de_emision'] = '2026-11-16';
            $payload['fecha_de_vencimiento'] = '2026-11-25';
            $normalized = $normalize($payload);
            self::assertSame('FF01', $normalized['series']);
            self::assertSame('2026-11-16', $normalized['date_of_issue']);
            try {
                $normalize(array_replace($payload, ['serie_documento' => 'FC01']));
                self::fail('Una serie de nota de crédito no debe seleccionarse para facturar.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('series', $error->errors());
            }
            try {
                $normalize(array_replace($payload, ['fecha_de_emision' => '2026-11-15']));
                self::fail('La fecha en el límite del plazo debe rechazarse.');
            } catch (\Exception $error) {
                self::assertStringContainsString('menor a 4 día(s)', $error->getMessage());
            }
            self::assertSame(0, $db->table('documents')->count());
            self::assertSame(0, $db->transactionLevel());
        } finally {
            Carbon::setTestNow($previous);
        }
    }

    private function invoicePayload(): array
    {
        [$db, , $input] = (new ReflectionMethod($this->fixture, 'bootHkaFixture'))->invoke($this->fixture);
        PostgresTestEnvironment::assertServer($db);
        $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $payload = DocumentWebTransform::transform(array_replace($input, [
            'date_of_issue' => now()->toDateString(), 'date_of_due' => now()->toDateString(),
            'operation_type_id' => '0101', 'payment_condition_id' => '02', 'payments' => [],
        ]));
        $payload['datos_del_cliente_o_receptor']['codigo_pais'] = 'VE';
        $payload['datos_del_cliente_o_receptor']['ubigeo'] = '000619';
        return [$db, $payload];
    }
}
