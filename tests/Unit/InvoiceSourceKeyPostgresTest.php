<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\InputRequest;
use App\CoreFacturalo\Requests\Api\Validation\DocumentValidation as ApiDocumentValidation;
use App\CoreFacturalo\Requests\Inputs\DocumentInput;
use App\CoreFacturalo\Requests\Web\Validation\DocumentValidation as WebDocumentValidation;
use App\Http\Controllers\Tenant\SaleNoteController;
use App\Models\Tenant\{Cash, Item, SaleNote};
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Order\Http\Controllers\OrderNoteController;
use Modules\Order\Http\Requests\OrderNoteRequest;
use Modules\Order\Models\OrderNote;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceSourceKeyPostgresTest extends TestCase
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

    public function test_hotel_invoice_checkout_validates_the_series_key_before_lookup(): void
    {
        [$db, , $input] = (new ReflectionMethod($this->fixture, 'bootHkaFixture'))->invoke($this->fixture);
        $branch = $input['establishment_id'];
        $category = $db->table('hotel_categories')->insertGetId(['description' => 'Prueba de cierre', 'establishment_id' => $branch]);
        $floor = $db->table('hotel_floors')->insertGetId(['description' => 'Prueba de cierre', 'establishment_id' => $branch]);
        $room = $db->table('hotel_rooms')->insertGetId([
            'name' => 'Prueba de cierre', 'hotel_category_id' => $category, 'hotel_floor_id' => $floor,
            'item_id' => $input['items'][0]['item_id'], 'status' => 'OCUPADO', 'establishment_id' => $branch,
        ]);
        $rent = $db->table('hotel_rents')->insertGetId([
            'customer_id' => $input['customer_id'], 'customer' => json_encode($input['customer'], JSON_THROW_ON_ERROR),
            'hotel_room_id' => $room, 'payment_status' => 'DEBT', 'establishment_id' => $branch,
            'output_date' => '2026-10-27', 'output_time' => '12:00',
        ]);
        $controller = new \Modules\Hotel\Http\Controllers\HotelRentController();
        $series = (string) $db->table('series')->where('document_type_id', '01')->value('id');
        unset($input['series']);
        foreach (['9223372036854775808', $series.'x', '0'.$series, $series.'.0', [$series]] as $invalid) {
            $request = \Illuminate\Http\Request::create('/', 'POST', [
                'operation_key' => (string) \Illuminate\Support\Str::uuid(),
                'document' => array_replace($input, ['series_id' => $invalid]),
            ]);
            try {
                $controller->atomicCheckout($request, $rent);
                self::fail('La serie inválida debe rechazarse como un error de validación.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('series_id', $error->errors());
            }
        }

        self::assertSame(0, $db->table('documents')->count());
        self::assertSame(0, $db->table('document_payments')->count());
        self::assertSame('OCUPADO', $db->table('hotel_rooms')->where('id', $room)->value('status'));
        self::assertNotSame('FINALIZADO', $db->table('hotel_rents')->where('id', $rent)->value('status'));
        self::assertSame(0, $db->transactionLevel());
    }

    public function test_invoice_creation_validates_the_customer_key_before_lookup(): void
    {
        [$db, , $input] = (new ReflectionMethod($this->fixture, 'bootHkaFixture'))->invoke($this->fixture);
        $customerId = (string) $input['customer_id'];
        $inventory = $db->table('item_warehouse')->get()->toArray();
        $entryPoints = [
            'factura directa' => static fn (array $data) => (new Facturalo())->forWebDocuments()->save($data),
            'normalización' => [DocumentInput::class, 'set'],
        ];
        foreach ($entryPoints as $entry => $create) {
            foreach (['9223372036854775808', $customerId.'x', '0'.$customerId, $customerId.'.0', [$customerId], null] as $invalid) {
                try {
                    $create(array_replace($input, ['customer_id' => $invalid]));
                    self::fail($entry.' debe rechazar la clave de cliente inválida.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('customer_id', $error->errors());
                }
            }
        }

        self::assertSame(0, $db->table('documents')->count());
        self::assertSame(0, $db->table('document_payments')->count());
        self::assertEquals($inventory, $db->table('item_warehouse')->get()->toArray());
        $invoice = (new Facturalo())->forWebDocuments()->save(array_replace($input, ['customer_id' => $customerId]));
        self::assertSame($customerId, (string) $invoice->getDocument()->customer_id);
    }

    public function test_invoice_creation_validates_the_establishment_key_before_using_it(): void
    {
        [$db, , $input] = (new ReflectionMethod($this->fixture, 'bootHkaFixture'))->invoke($this->fixture);
        $branch = (string) $input['establishment_id'];
        $entryPoints = [
            'factura directa' => static fn (array $data) => (new Facturalo())->forWebDocuments()->save($data),
            'serie web' => [WebDocumentValidation::class, 'validation'],
            'normalización' => [DocumentInput::class, 'set'],
        ];
        $inventory = $db->table('item_warehouse')->get()->toArray();
        foreach ($entryPoints as $entry => $create) {
            foreach ([$branch.'x', $branch.'.0', '0'.$branch, '9223372036854775808', null] as $invalid) {
                try {
                    $create(array_replace($input, ['establishment_id' => $invalid]));
                    self::fail($entry.' debe rechazar la clave de sucursal no canónica.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('establishment_id', $error->errors());
                }
            }
        }
        $auth = auth();
        $operator = $auth->user();
        $auth->setUser(null);
        try {
            $apiInput = array_replace($input, ['establishment_id' => $branch.'x']);
            try {
                ApiDocumentValidation::validation($apiInput);
                self::fail('La factura API de invitado debe validar la clave antes de resolver la serie.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('establishment_id', $error->errors());
            }
        } finally {
            $auth->setUser($operator);
        }

        self::assertSame(0, $db->table('documents')->count());
        self::assertSame(0, $db->table('document_payments')->count());
        self::assertEquals($inventory, $db->table('item_warehouse')->get()->toArray());
        self::assertSame(0, $db->transactionLevel());

        $valid = WebDocumentValidation::validation(array_replace($input, ['establishment_id' => $branch]));
        self::assertSame($input['series'], $valid['series']);
        self::assertSame($branch, $valid['establishment_id']);
    }

    public function test_invoice_edit_middleware_and_notes_reject_invalid_establishment_keys(): void
    {
        [$db, , $input] = (new ReflectionMethod($this->fixture, 'bootHkaFixture'))->invoke($this->fixture);
        $branch = (string) $input['establishment_id'];
        $invalid = array_replace($input, ['establishment_id' => $branch.'x']);
        $request = \Illuminate\Http\Request::create('/documents/1/update', 'POST', $invalid);
        $request->attributes->set('sab.strict_input_exceptions', true);

        try {
            (new InputRequest())->handle($request, static fn () => self::fail('La solicitud inválida llegó al controlador.'),
                'documentUpdate', 'web');
            self::fail('La edición debe rechazar la sucursal desde el middleware.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('establishment_id', $error->errors());
        }

        foreach (['07' => 'FC01', '08' => 'FD01'] as $type => $series) {
            $noteInput = array_replace($invalid, ['document_type_id' => (string) $type, 'series' => $series]);
            foreach ([[WebDocumentValidation::class, 'validation'], [DocumentInput::class, 'set']] as $validate) {
                try {
                    $validate($noteInput);
                    self::fail('La nota debe validar la sucursal antes de buscar datos.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('establishment_id', $error->errors());
                }
            }
            $valid = WebDocumentValidation::validation(array_replace($noteInput, ['establishment_id' => $branch]));
            self::assertSame($branch, $valid['establishment_id']);
        }
        self::assertSame(0, $db->table('documents')->count());
        self::assertSame(0, $db->transactionLevel());
    }

    public static function invalidInvoiceSourceKeys(): array
    {
        $overflow = '9223372036854775808';
        return [
            'nota directa' => [['sale_note_id' => $overflow], 'sale_note_id'],
            'nota relacionada' => [['sale_notes_relateds' => [['id' => $overflow]]], 'sale_notes_relateds'],
            'cobro de origen' => [['payments' => [['source_sale_note_payment_id' => $overflow]]], 'source_sale_note_payment_id'],
            'cobro anterior' => [['payments' => [['sale_note_id' => '1', 'id' => $overflow]]], 'source_sale_note_payment_id'],
        ];
    }

    /** @dataProvider invalidInvoiceSourceKeys */
    public function test_invoice_rejects_source_keys_outside_postgresql_bigint(array $changes, string $field): void
    {
        [$db, , $input] = (new ReflectionMethod($this->fixture, 'bootHkaFixture'))->invoke($this->fixture);
        $stock = $db->table('item_warehouse')->get()->toArray();

        try {
            (new Facturalo())->forWebDocuments()->save(array_replace($input, $changes));
            self::fail('La factura debe rechazar la clave de origen fuera de rango.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey($field, $error->errors());
        }
        self::assertSame(0, $db->table('documents')->count());
        self::assertSame(0, $db->table('document_payments')->count());
        self::assertEquals($stock, $db->table('item_warehouse')->get()->toArray());
        self::assertSame(0, $db->transactionLevel());
    }

    public function test_invoice_from_sale_note_rejects_noncanonical_order_keys_and_keeps_the_origin(): void
    {
        [$db, $app, $input] = (new ReflectionMethod($this->fixture, 'bootHkaFixture'))->invoke($this->fixture, false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        Cash::create(['user_id'=>$input['user_id'], 'state'=>true, 'date_opening'=>'2026-09-10',
            'time_opening'=>'09:00:00', 'beginning_balance'=>'0']);
        (new ReflectionMethod($this->fixture, 'bootGlobalInventory'))->invoke($this->fixture, $app);
        $url = $this->createMock(UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(UrlGenerator::class, $url);

        $orderId = '9223372036854775807';
        $db->selectOne("SELECT setval(pg_get_serial_sequence('order_notes','id'), CAST(? AS bigint), false)", [$orderId]);
        $input['items'][0]['quantity'] = '0.333333333333';
        $input['items'][0]['quantity_input'] = ['amount'=>'1', 'unit_price'=>'3'];
        $orders = $this->getMockBuilder(OrderNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $savedOrder = $orders->store(OrderNoteRequest::create('/', 'POST', array_replace($input, ['prefix'=>'PED', 'prepayments'=>[]])));
        $order = OrderNote::findOrFail($savedOrder['data']['id']);
        self::assertSame($orderId, (string) $order->id);

        $notes = $this->getMockBuilder(SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $savedNote = $notes->storeWithData(array_replace($input, [
            'order_note_id'=>$orderId, 'series'=>'NV01', 'document_type_id'=>'80', 'prefix'=>'NV',
            'payments'=>[], 'payment_condition_id'=>'02',
        ]));
        self::assertTrue($savedNote['success'], $savedNote['message'] ?? '');
        $note = SaleNote::findOrFail($savedNote['data']['id']);
        self::assertSame($orderId, (string) $note->order_note_id);

        $itemId = $input['items'][0]['item_id'];
        $warehouseId = $input['items'][0]['warehouse_id'];
        $globalStock = fn () => Item::findOrFail($itemId)->exactValue('stock');
        $warehouseStock = fn () => ItemWarehouse::where('item_id', $itemId)->where('warehouse_id', $warehouseId)->firstOrFail()->exactValue('stock');
        $globalBefore = $globalStock();
        $warehouseBefore = $warehouseStock();
        $invoice = array_replace($input, ['sale_note_id'=>$note->id, 'payment_condition_id'=>'02', 'payments'=>[]]);

        foreach (['9223372036854775808', '09223372036854775807', '9223372036854775807.0'] as $invalidKey) {
            try {
                (new Facturalo())->forWebDocuments()->save(array_replace($invoice, ['order_note_id'=>$invalidKey]));
                self::fail('Una clave de pedido inválida no debe convertirse en la del pedido original: '.$invalidKey);
            } catch (ValidationException $error) {
                self::assertArrayHasKey('order_note_id', $error->errors());
            }
            self::assertSame(0, $db->table('documents')->count());
            self::assertNull($note->fresh()->document_id);
            self::assertTrue($globalStock()->isEqualTo($globalBefore));
            self::assertTrue($warehouseStock()->isEqualTo($warehouseBefore));
            self::assertSame(0, $db->transactionLevel());
        }

        $document = (new Facturalo())->forWebDocuments()->save(array_replace($invoice, ['order_note_id'=>$orderId]))->getDocument();
        self::assertSame($orderId, (string) $document->order_note_id);
        self::assertSame($document->id, $note->fresh()->document_id);
        self::assertTrue($document->items()->firstOrFail()->exactValue('quantity')->isEqualTo('1/3'));
        self::assertTrue($globalStock()->isEqualTo($globalBefore));
        self::assertTrue($warehouseStock()->isEqualTo($warehouseBefore));
    }
}
