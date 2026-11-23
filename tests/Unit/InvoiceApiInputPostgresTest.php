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

    public function test_vendeya_note_transform_preserves_large_and_fractional_totals(): void
    {
        [$db, $payload] = $this->invoicePayload();
        $payload['source_module'] = 'VENDEYA';
        $payload['codigo_tipo_moneda'] = 'VES';
        $payload['codigo_tipo_nota'] = '01';
        $payload['motivo_o_sustento_de_nota'] = 'Ajuste de prueba';
        $payload['documento_afectado'] = ['external_id' => (string) \Illuminate\Support\Str::uuid()];
        $large = $payload['items'][0];
        $large['cantidad'] = '1'; $large['precio_unitario'] = '9007199254740993.000000000001';
        $large['codigo_tipo_afectacion_igv'] = '20'; unset($large['total_item']);
        $fraction = $payload['items'][0];
        $fraction['cantidad'] = '0.1'; $fraction['precio_unitario'] = '0.2';
        $fraction['codigo_tipo_afectacion_igv'] = '10'; unset($fraction['total_item']);
        $payload['items'] = [$large, $fraction];
        foreach (['07', '08'] as $type) {
            $payload['codigo_tipo_documento'] = $type;
            $result = DocumentTransform::transform($payload);
            self::assertSame('9007199254740993.000000000001', (string) $result['items'][0]['total']);
            self::assertSame('0.02', (string) $result['items'][1]['total']);
            self::assertSame('9007199254740993.020000000001', (string) $result['total']);
        }
        self::assertSame(0, $db->table('documents')->count());
    }

    public function test_api_notes_reference_original_lines_without_creating_products(): void
    {
        [$db, $payload] = $this->invoicePayload();
        $send = static function (array $data) {
            $request = Request::create('/api/documents', 'POST', $data);
            $request->attributes->set('sab.strict_input_exceptions', true);
            return (new InputRequest())->handle($request,
                fn (Request $input) => (new ApiSaleCreation())->persistDocument($input->all()), 'document', 'api');
        };
        $invoice = $send($payload)->getDocument()->fresh();
        $line = $invoice->items()->firstOrFail();
        self::assertSame((string) $line->id, $invoice->getApiResourceFind()['items'][0]['id']);
        $originalItems = $db->table('items')->count();
        foreach (['07'=>'FC01', '08'=>'FD01'] as $type=>$series) {
            $note = $payload;
            $note['source_module']='VENDEYA'; $note['codigo_tipo_documento']=$type; $note['serie_documento']=$series;
            $note['codigo_tipo_nota']=$type === '07' ? '04' : '02';
            $note['motivo_o_sustento_de_nota']='Ajuste API';
            $note['note_creation_key']=(string) \Illuminate\Support\Str::uuid();
            $note['documento_afectado']=['external_id'=>$invoice->external_id];
            $note['items'][0]['affected_document_item_id']=(string) $line->id;
            $note['items'][0]['adjustment_amount']='1';
            $note['items'][0]['codigo_interno']='NO-CREAR-PRODUCTO';
            if ($type === '07') {
                foreach (['missing_uuid','missing_line','duplicate_line'] as $case) {
                    $invalid=$note;
                    if ($case === 'missing_uuid') unset($invalid['note_creation_key']);
                    if ($case === 'missing_line') unset($invalid['items'][0]['affected_document_item_id']);
                    if ($case === 'duplicate_line') $invalid['items'][]=$invalid['items'][0];
                    $before=$db->table('documents')->count();
                    try { $send($invalid); self::fail('Debe rechazar '.$case); }
                    catch (ValidationException $error) { self::assertNotEmpty($error->errors()); }
                    self::assertSame($before,$db->table('documents')->count());
                    self::assertSame($originalItems,$db->table('items')->count());
                }
            }
            $created=$send($note)->getDocument()->fresh();
            self::assertSame($type,$created->document_type_id);
            self::assertSame((string) $line->id, (string) $created->items()->firstOrFail()->affected_document_item_id);
            self::assertSame((string) $created->id,(string) $send($note)->getDocument()->id);
            $altered=$note; $altered['items'][0]['adjustment_amount']='2';
            try { $send($altered); self::fail('Una clave repetida no admite contenido distinto.'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { self::assertSame(409, $error->getStatusCode()); }
            self::assertSame($originalItems,$db->table('items')->count());
        }
        $invalid=$note; $invalid['note_creation_key']=(string) \Illuminate\Support\Str::uuid();
        $invalid['items'][0]['affected_document_item_id']='999999999';
        $before=$db->table('documents')->count();
        try { $send($invalid); self::fail('Debe rechazar una partida ajena.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('items.0',$error->errors()); }
        self::assertSame($before,$db->table('documents')->count());
        self::assertSame($originalItems,$db->table('items')->count());

        $charge=$note; $charge['note_creation_key']=(string) \Illuminate\Support\Str::uuid();
        $charge['codigo_tipo_nota']='01'; unset($charge['items'][0]['affected_document_item_id']);
        $charge['items'][0]['codigo_interno']='SERVICIO-INEXISTENTE';
        try { $send($charge); self::fail('El cargo requiere un servicio existente.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('items',$error->errors()); }
        self::assertSame($originalItems,$db->table('items')->count());
        $service=\App\Models\Tenant\Item::findOrFail($line->item_id)->replicate();
        $service->forceFill(['internal_id'=>'SERVICIO-API-EXISTENTE','unit_type_id'=>'SERV'])->save();
        $charge['items'][0]['codigo_interno']='SERVICIO-API-EXISTENTE';
        $charge['items'][0]['unidad_de_medida']='SERV';
        $count=$db->table('items')->count();
        $created=$send($charge)->getDocument()->fresh();
        self::assertSame((string) $service->id,(string) $created->items()->firstOrFail()->item_id);
        self::assertSame($count,$db->table('items')->count());
        $duplicate=$service->replicate(); $duplicate->save();
        $charge['note_creation_key']=(string) \Illuminate\Support\Str::uuid();
        try { $send($charge); self::fail('Un código de servicio duplicado debe rechazarse.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('items',$error->errors()); }
        self::assertSame($count+1,$db->table('items')->count());
        $charge['codigo_tipo_nota']='02';
        try { $send($charge); self::fail('El motivo requiere identificar la partida original.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('items.affected_document_item_id',$error->errors()); }
    }

    public function test_api_annulment_derives_original_lines_without_creating_submitted_products(): void
    {
        [$db, $payload] = $this->invoicePayload();
        $send = static function (array $data) {
            $request = Request::create('/api/documents', 'POST', $data);
            $request->attributes->set('sab.strict_input_exceptions', true);
            return (new InputRequest())->handle($request,
                fn (Request $input) => (new ApiSaleCreation())->persistDocument($input->all()), 'document', 'api');
        };
        $invoice=$send($payload)->getDocument()->fresh();
        $line=$invoice->items()->firstOrFail(); $count=$db->table('items')->count();
        $note=$payload; $note['source_module']='VENDEYA'; $note['codigo_tipo_documento']='07';
        $note['serie_documento']='FC01'; $note['codigo_tipo_nota']='01';
        $note['motivo_o_sustento_de_nota']='Anulación API';
        $note['note_creation_key']=(string) \Illuminate\Support\Str::uuid();
        $note['documento_afectado']=['external_id'=>$invoice->external_id];
        $note['items'][0]['codigo_interno']='PRODUCTO-IGNORADO';
        $created=$send($note)->getDocument()->fresh();
        self::assertSame((string) $line->id,(string) $created->items()->firstOrFail()->affected_document_item_id);
        self::assertSame($count,$db->table('items')->count());
    }

    public function test_api_note_cannot_reference_another_sellers_invoice(): void
    {
        [$db, $payload] = $this->invoicePayload();
        $send = static function (array $data) {
            $request = Request::create('/api/documents', 'POST', $data);
            $request->attributes->set('sab.strict_input_exceptions', true);
            return (new InputRequest())->handle($request,
                fn (Request $input) => (new ApiSaleCreation())->persistDocument($input->all()), 'document', 'api');
        };
        $invoice=$send($payload)->getDocument()->fresh();
        $actor=auth()->user();
        $other=$db->table('users')->insertGetId(['name'=>'Otro vendedor','email'=>'api-note-other@example.test',
            'password'=>'not-a-login-hash','type'=>'seller','establishment_id'=>$invoice->establishment_id]);
        $note=$payload; $note['source_module']='VENDEYA'; $note['codigo_tipo_documento']='07';
        $note['serie_documento']='FC01'; $note['codigo_tipo_nota']='04'; $note['motivo_o_sustento_de_nota']='Prueba';
        $note['note_creation_key']=(string) \Illuminate\Support\Str::uuid();
        $note['documento_afectado']=['external_id'=>$invoice->external_id];
        $note['items'][0]['affected_document_item_id']=(string) $invoice->items()->firstOrFail()->id;
        $note['items'][0]['adjustment_amount']='1';
        $before=$db->table('documents')->count(); $products=$db->table('items')->count();
        try {
            auth()->setUser(\App\Models\Tenant\User::findOrFail($other));
            try { $send($note); self::fail('No debe acceder a una factura de otro vendedor.'); }
            catch (\Illuminate\Database\Eloquent\ModelNotFoundException $error) { self::assertNotEmpty($error->getModel()); }
            self::assertSame($before,$db->table('documents')->count());
            self::assertSame($products,$db->table('items')->count());
        } finally { auth()->setUser($actor); }
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
