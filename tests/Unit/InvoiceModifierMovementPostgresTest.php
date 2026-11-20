<?php

namespace Tests\Unit;

use App\CoreFacturalo\{Facturalo, InputRequest};
use App\CoreFacturalo\Requests\Inputs\Transform\DocumentWebTransform;
use App\Models\Tenant\Item;
use App\Services\Sellnow\ApiSaleCreation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\ItemWarehouse;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\PostgresTestEnvironment;

/** @group postgres-integration */
final class InvoiceModifierMovementPostgresTest extends TestCase
{
    public function test_api_maps_item_modifiers_to_the_submitted_invoice_line(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            PostgresTestEnvironment::assertServer($db);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $base = Item::findOrFail($input['items'][0]['item_id']);
            $modifier = $base->replicate()->forceFill(['description' => 'Adicional API', 'internal_id' => 'MOD-API-PG', 'stock' => '10']);
            $modifier->save();
            $warehouse = $input['items'][0]['warehouse_id'];
            $stock = ItemWarehouse::create(['item_id' => $modifier->id, 'warehouse_id' => $warehouse, 'stock' => '10']);
            $input = array_replace($input, [
                'date_of_issue' => now()->toDateString(), 'date_of_due' => now()->toDateString(),
                'operation_type_id' => '0101', 'payment_condition_id' => '02', 'payments' => [],
            ]);
            $input['items'] = [$input['items'][0], array_replace($input['items'][0], ['quantity' => '0.125'])];
            $payload = DocumentWebTransform::transform($input);
            $payload['datos_del_cliente_o_receptor']['codigo_pais'] = 'VE';
            $payload['datos_del_cliente_o_receptor']['ubigeo'] = '000619';
            $selection = ['type' => 'item', 'item_id' => (string) $modifier->id];
            $payload['items'][1]['modifiersApplied'] = [['items' => [$selection]], ['items' => [$selection]]];

            $persist = static function (array $data) {
                $request = Request::create('/api/documents', 'POST', $data);
                $request->attributes->set('sab.strict_input_exceptions', true);
                return (new InputRequest())->handle($request,
                    fn (Request $prepared) => (new ApiSaleCreation())->persistDocument($prepared->all()),
                    'document', 'api')->getDocument()->fresh();
            };

            $invalid = $payload;
            $invalid['items'][1]['modifiersApplied'][0]['items'][0]['item_id'] = '0'.$modifier->id;
            try {
                $persist($invalid);
                self::fail('Se aceptó una clave no canónica de modificador.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('items', $exception->errors());
            }
            self::assertSame(0, $db->table('documents')->count());
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo('10'));

            $invoice = $persist($payload);
            $lines = $invoice->items()->orderBy('id')->get();
            self::assertCount(2, $lines);
            self::assertSame([], \App\Services\Fiscal\InvoiceModifiers::components($lines[0]->item));
            $components = \App\Services\Fiscal\InvoiceModifiers::components($lines[1]->item);
            self::assertSame(['n' => '2', 'd' => '1'], (array) $components[0]['quantity_exact']);
            self::assertTrue($invoice->inventory_kardex()->where('item_id', $modifier->id)
                ->sole()->exactValue('quantity')->isEqualTo('-0.25'));
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo('9.75'));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_direct_invoice_applies_each_modifier_once(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            PostgresTestEnvironment::assertServer($db);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $base = Item::findOrFail($input['items'][0]['item_id']);
            $modifier = $base->replicate()->forceFill(['description' => 'Modificador sintético', 'internal_id' => 'MOD-PG', 'stock' => '10']);
            $modifier->save();
            $warehouse = $input['items'][0]['warehouse_id'];
            ItemWarehouse::create(['item_id' => $modifier->id, 'warehouse_id' => $warehouse, 'stock' => '10']);

            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $row = $input['items'][0];
            $row['quantity'] = '0.333333333333';
            $row['quantity_input'] = ['amount' => '1', 'unit_price' => '3'];
            $input['items'] = [$row, $input['items'][0]];
            $input['data_json'] = ['items' => [
                ['cantidad' => '0.333333333333', 'modifiersApplied' => [['items' => [['type' => 'item', 'item_id' => $modifier->id]]]]],
                ['cantidad' => '2'],
            ]];
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            self::assertCount(2, $invoice->items);
            $movements = $invoice->inventory_kardex()->where('item_id', $modifier->id)->get();
            self::assertCount(1, $movements, 'Cada modificador se procesa una única vez en su partida.');
            self::assertTrue($movements->sole()->exactValue('quantity')->isEqualTo('-1/3'));
            $stock = ItemWarehouse::where('item_id', $modifier->id)->where('warehouse_id', $warehouse)->firstOrFail();
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo('29/3'));
            $lines = $invoice->items()->orderBy('id')->get();
            self::assertCount(1, \App\Services\Fiscal\InvoiceModifiers::components($lines[0]->item));
            self::assertSame([], \App\Services\Fiscal\InvoiceModifiers::components($lines[1]->item));

            $edit = array_replace($input, [
                'id' => $invoice->id, 'series' => $invoice->series, 'number' => $invoice->number,
                'received_retentions' => [], 'guarantee_fund' => null, 'data_json' => null,
            ]);
            $edit['items'][0]['record_id'] = $lines[0]->id;
            $edit['items'][1]['record_id'] = $lines[1]->id;
            $edit['items'][0]['quantity'] = '0.5';
            unset($edit['items'][0]['quantity_input']);
            (new Facturalo())->update($edit, $invoice->id);
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo('9.5'));
            self::assertSame(3, $invoice->inventory_kardex()->where('item_id', $modifier->id)->count());

            $invoice->fresh()->forceFill(['state_type_id' => '11'])->save();
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo('10'));
            self::assertSame(4, $invoice->inventory_kardex()->where('item_id', $modifier->id)->count());

            $input['external_id'] = Str::uuid()->toString();
            $next = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $first = $next->items()->orderBy('id')->firstOrFail();
            $creditInput = (new ReflectionMethod($fixture, 'noteInput'))->invoke($fixture, $input, $next, '07', '07', [
                ['affected_document_item_id' => $first->id, 'quantity' => '0.1'],
            ]);
            $credit = (new Facturalo())->forWebDocuments()->save($creditInput)->getDocument()->fresh();
            self::assertTrue($credit->inventory_kardex()->where('item_id', $modifier->id)->sole()->exactValue('quantity')->isEqualTo('1/10'));
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo('293/30'));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_api_invoice_freezes_merged_products_per_line_and_restores_their_stock(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            PostgresTestEnvironment::assertServer($db);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $base = Item::findOrFail($input['items'][0]['item_id']);
            $warehouse = $input['items'][0]['warehouse_id'];
            $stocks = [];
            foreach (['ALFA', 'BETA'] as $label) {
                $product = $base->replicate()->forceFill(['description' => 'Componente '.$label, 'internal_id' => 'FUSION-'.$label, 'stock' => '10']);
                $product->save();
                $stocks[] = ItemWarehouse::create(['item_id' => $product->id, 'warehouse_id' => $warehouse, 'stock' => '10']);
            }
            [$alpha, $beta] = $stocks;
            $input['payments'] = [];
            $input['payment_condition_id'] = '02';
            $input['operation_type_id'] = '0101';
            $input['date_of_issue'] = $input['date_of_due'] = now()->toDateString();
            $input['items'] = [array_replace($input['items'][0], ['quantity' => '3']), $input['items'][0]];
            $payload = DocumentWebTransform::transform($input);
            $payload['datos_del_cliente_o_receptor']['codigo_pais'] = 'VE';
            $payload['datos_del_cliente_o_receptor']['ubigeo'] = '000619';
            $payload['items'][0]['esFusionado'] = true;
            $payload['items'][0]['productosFusionados'] = [
                ['id' => $base->id, 'quantity' => '3'],
                ['id' => $alpha->item_id, 'quantity' => '0.25'],
                ['id' => $alpha->item_id, 'quantity' => '1.75'],
            ];
            $payload['items'][1]['esFusionado'] = true;
            $payload['items'][1]['productosFusionados'] = [['id' => $beta->item_id, 'quantity' => '0.5']];
            $persist = static function (array $data) {
                $request = Request::create('/api/documents', 'POST', $data);
                $request->attributes->set('sab.strict_input_exceptions', true);
                return (new InputRequest())->handle($request,
                    fn (Request $prepared) => (new ApiSaleCreation())->persistDocument($prepared->all()),
                    'document', 'api')->getDocument()->fresh();
            };

            $invalid = $payload;
            $invalid['items'][1]['productosFusionados'][0]['quantity'] = '-1';
            try {
                $persist($invalid);
                self::fail('Una cantidad fusionada negativa debe invalidar la factura.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('items', $error->errors());
            }
            self::assertSame(0, $db->table('documents')->count());
            self::assertTrue($alpha->fresh()->exactValue('stock')->isEqualTo('10'));

            $invoice = $persist($payload);
            $lines = $invoice->items()->orderBy('id')->get();
            self::assertCount(2, $lines);
            self::assertTrue($invoice->inventory_kardex()->where('item_id', $alpha->item_id)->sole()->exactValue('quantity')->isEqualTo('-2'));
            self::assertTrue($invoice->inventory_kardex()->where('item_id', $beta->item_id)->sole()->exactValue('quantity')->isEqualTo('-1/2'));
            self::assertSame(['n' => '2', 'd' => '3'], (array) \App\Services\Fiscal\InvoiceModifiers::components($lines[0]->item)[0]['quantity_exact']);
            self::assertTrue($alpha->fresh()->exactValue('stock')->isEqualTo('8'));
            self::assertTrue($beta->fresh()->exactValue('stock')->isEqualTo('9.5'));

            $edit = array_replace($input, ['id' => $invoice->id, 'series' => $invoice->series, 'number' => $invoice->number,
                'received_retentions' => [], 'guarantee_fund' => null, 'data_json' => null]);
            $edit['items'][0]['record_id'] = $lines[0]->id;
            $edit['items'][1]['record_id'] = $lines[1]->id;
            $edit['items'][0]['quantity'] = '1.5';
            (new Facturalo())->update($edit, $invoice->id);
            self::assertTrue($alpha->fresh()->exactValue('stock')->isEqualTo('9'));
            self::assertTrue($beta->fresh()->exactValue('stock')->isEqualTo('9.5'));
            $invoice->fresh()->forceFill(['state_type_id' => '11'])->save();
            self::assertTrue($alpha->fresh()->exactValue('stock')->isEqualTo('10'));
            self::assertTrue($beta->fresh()->exactValue('stock')->isEqualTo('10'));

            $next = $persist($payload);
            $first = $next->items()->orderBy('id')->firstOrFail();
            $creditInput = (new ReflectionMethod($fixture, 'noteInput'))->invoke($fixture, $input, $next, '07', '07', [
                ['affected_document_item_id' => $first->id, 'quantity' => '1'],
            ]);
            $credit = (new Facturalo())->forWebDocuments()->save($creditInput)->getDocument()->fresh();
            self::assertTrue($credit->inventory_kardex()->where('item_id', $alpha->item_id)->sole()->exactValue('quantity')->isEqualTo('2/3'));
            self::assertFalse($credit->inventory_kardex()->where('item_id', $beta->item_id)->exists());
            self::assertTrue($alpha->fresh()->exactValue('stock')->isEqualTo('26/3'));
            self::assertTrue($beta->fresh()->exactValue('stock')->isEqualTo('9.5'));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_subprecision_credit_notifies_restaurant_stock_after_return(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            PostgresTestEnvironment::assertServer($db);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input['payments'] = [];
            $input['payment_condition_id'] = '02';
            $input['items'][0]['quantity'] = '0';
            $input['items'][0]['unit_price'] = '3000000000000';
            $input['items'][0]['quantity_input'] = ['amount' => '1', 'unit_price' => '3000000000000'];
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();

            $stock = new class {
                public array $updated = [];
                public function calculateAndUpdateStock($itemId): void { $this->updated[] = $itemId; }
            };
            $app->instance(\Modules\Restaurant\Services\RestaurantStockService::class, $stock);
            $source = $invoice->items->sole();
            $creditInput = (new ReflectionMethod($fixture, 'noteInput'))->invoke($fixture, $input, $invoice, '07', '01', [
                ['affected_document_item_id' => $source->id],
            ]);
            $credit = (new Facturalo())->forWebDocuments()->save($creditInput)->getDocument()->fresh();
            $line = $credit->items->sole();
            self::assertSame('0', $line->inventory_quantity);
            self::assertTrue($line->exactValue('inventory_quantity')->isEqualTo('1/3000000000000'));
            self::assertSame([$source->item_id], $stock->updated);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
