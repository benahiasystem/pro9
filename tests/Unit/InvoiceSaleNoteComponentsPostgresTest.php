<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Tenant\SaleNoteController;
use App\Models\Tenant\Item;
use App\Models\Tenant\SaleNote;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\SaleNoteConversionLines;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Inventory\Models\Warehouse;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\PostgresTestEnvironment;

/** @group postgres-integration */
final class InvoiceSaleNoteComponentsPostgresTest extends TestCase
{
    /** @dataProvider componentTypes */
    public function test_invoice_reuses_the_sale_note_component_movement(string $type, string $deducted, string $returned, bool $secondary = false, ?string $scenario = null): void
    {
        $large = $scenario === 'historical';
        $newPrecise = $scenario === 'precise';
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            PostgresTestEnvironment::assertServer($db);
            (new ReflectionMethod($fixture, 'bootGlobalInventory'))->invoke($fixture, $app);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $url = $this->createMock(UrlGenerator::class);
            $url->method('to')->willReturn('http://fiscal-schema.example.test');
            $app->instance(UrlGenerator::class, $url);

            $line = $input['items'][0];
            $base = Item::findOrFail($line['item_id']);
            $primaryWarehouseId = $line['warehouse_id'];
            $initialStock = ($large || $newPrecise) ? '9007199254745000' : '10';
            if ($large || $newPrecise) {
                $base->setExactValues(['stock' => D::of($initialStock)])->save();
                ItemWarehouse::where('item_id', $base->id)->where('warehouse_id', $primaryWarehouseId)->sole()
                    ->setExactValues(['stock' => D::of($initialStock)])->save();
            }
            $component = $base->replicate()->forceFill([
                'description' => 'Componente de nota temporal',
                'internal_id' => 'NOTA-COMPONENTE-PG',
                'stock' => $initialStock,
            ]);
            $component->save();
            $stock = ItemWarehouse::create([
                'item_id' => $component->id,
                'warehouse_id' => $line['warehouse_id'],
                'stock' => $initialStock,
            ]);
            $secondaryStock = null;
            if ($secondary) {
                $warehouse = Warehouse::create(['description' => 'Secundario temporal', 'establishment_id' => $input['establishment_id']]);
                $line['warehouse_id'] = $warehouse->id;
                $secondaryStock = ItemWarehouse::create(['item_id' => $component->id, 'warehouse_id' => $warehouse->id, 'stock' => '20']);
            }

            $line['quantity'] = '3';
            if ($newPrecise) {
                $line['unit_price'] = '1';
                if ($type === 'modifier') $line['quantity'] = $deducted;
            }
            $selection = ['type' => 'item', 'item_id' => $component->id];
            $metadata = $type === 'modifier'
                ? ['modifiersApplied' => [['items' => ($large || $newPrecise) ? [$selection] : [$selection, $selection]]]]
                : ['esFusionado' => true, 'productosFusionados' => [['id' => $base->id], ['id' => $component->id, 'quantity' => $deducted]]];
            $notes = $this->getMockBuilder(SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
            $failPdf = false;
            $notes->method('createPdf')->willReturnCallback(static function () use (&$failPdf) {
                if ($failPdf) throw new \RuntimeException('Fallo de prueba al finalizar la edición.');
            });
            $noteInput = array_replace($input, [
                'id' => null,
                'series' => 'NV01',
                'document_type_id' => '80',
                'prefix' => 'NV',
                'payment_condition_id' => '02',
                'payments' => [],
                'items' => [array_replace($line, ['item' => array_replace($line['item'], $metadata)])],
            ]);
            $created = $notes->storeWithData($noteInput);
            self::assertTrue($created['success'], $created['message'] ?? '');
            $note = SaleNote::findOrFail($created['data']['id']);
            if ($scenario === 'edit' || $scenario === 'void') {
                self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo(D::of($initialStock)->minus($deducted)));
                if ($scenario === 'void') {
                    self::assertTrue($notes->anulate($note->id)['success']);
                    self::assertTrue($notes->anulate($note->id)['success']);
                    self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo($initialStock));
                    $baseStock = ItemWarehouse::where('item_id', $base->id)->where('warehouse_id', $primaryWarehouseId)->sole();
                    self::assertTrue($baseStock->exactValue('stock')->isEqualTo($initialStock));
                    if ($secondaryStock) self::assertTrue($secondaryStock->fresh()->exactValue('stock')->isEqualTo('20'));
                    return;
                }
                $edit = array_replace($noteInput, ['id' => $note->id, 'number' => $note->number, 'external_id' => $note->external_id]);
                $originalId = $note->items()->sole()->id;
                $movementIds = $note->inventory_kardex()->orderBy('id')->pluck('id')->all();
                if ($type === 'merged') {
                    $invalid = $edit;
                    $invalid['items'][0]['item']['productosFusionados'][1]['quantity'] = '0';
                    $rejected = $notes->storeWithData($invalid);
                    self::assertFalse($rejected['success']);
                    self::assertStringContainsString('debe ser positiva', $rejected['message']);
                }
                $failPdf = true;
                $failed = $notes->storeWithData($edit);
                $failPdf = false;
                self::assertFalse($failed['success']);
                self::assertSame('Fallo de prueba al finalizar la edición.', $failed['message']);
                self::assertSame($originalId, $note->items()->sole()->id);
                self::assertSame($movementIds, $note->inventory_kardex()->orderBy('id')->pluck('id')->all());
                self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo(D::of($initialStock)->minus($deducted)));
                $result = $notes->storeWithData($edit);
                self::assertTrue($result['success'], $result['message'] ?? '');
                self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo(D::of($initialStock)->minus($deducted)));
            }
            $original = $note->items()->sole();
            if ($large) {
                // Prepare a historical row whose additional movement was narrowed to a binary float.
                $line['quantity'] = '9007199254740993';
                $quantity = D::of($line['quantity']);
                $rounded = D::of(-9007199254740992);
                $original->setExactValues(['quantity' => $quantity]);
                $snapshot = $original->calculation_snapshot;
                unset($snapshot['inputs']['sale_note_component_movements']);
                $db->table('sale_note_items')->where('id', $original->id)->update([
                    'quantity' => D::represent($quantity),
                    'calculation_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                ]);
                $original->refresh();
                $note->inventory_kardex()->where('item_id', $base->id)->sole()->setExactValues(['quantity' => $quantity->negated()])->save();
                $note->inventory_kardex()->where('item_id', $component->id)->sole()->setExactValues(['quantity' => $rounded])->save();
                \App\Models\Tenant\Kardex::where('sale_note_id', $note->id)->where('item_id', $base->id)->sole()
                    ->setExactValues(['quantity' => $quantity])->save();
                $stock->setExactValues(['stock' => D::of($initialStock)->plus($rounded)])->save();
            }
            $movements = $note->inventory_kardex()->where('item_id', $component->id)->orderBy('id')->get();
            $movement = $movements->first();
            self::assertSame((string) $line['warehouse_id'], (string) $original->warehouse_id);
            self::assertSame([(string) $primaryWarehouseId], $movements->pluck('warehouse_id')->map(static fn ($id) => (string) $id)->unique()->values()->all());
            $movementTotal = D::sum($movements->map(fn ($record) => $record->exactValue('quantity')));
            $remaining = D::of($initialStock)->plus($movementTotal);
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo($remaining));
            $globalStock = $component->fresh()->exactValue('stock');
            $line['source_sale_note_items'] = [['sale_note_id' => $note->id, 'sale_note_item_id' => $original->id]];
            $invoiceInput = array_replace($input, [
                'sale_note_id' => $note->id,
                'payment_condition_id' => '02',
                'payments' => [],
                'items' => [$line],
            ]);
            if ($large) {
                self::assertTrue($original->exactValue('quantity')->isEqualTo($line['quantity']));
                self::assertFalse($movementTotal->negated()->isEqualTo($original->exactValue('quantity')));
                try {
                    (new Facturalo())->forWebDocuments()->save($invoiceInput);
                    self::fail('La conversión debe rechazar un movimiento histórico que perdió precisión.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('inventory', $exception->errors());
                }
                self::assertSame(0, $db->table('documents')->count());
                self::assertNull($note->fresh()->document_id);
                self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo($remaining));
                return;
            }
            self::assertSame((string) D::of($deducted)->negated(), (string) $movementTotal);
            if ($secondary) {
                $anchor = $original->inventory_kardex_id;
                self::assertSame((string) $movement->id, (string) $anchor);
                $original->forceFill(['inventory_kardex_id' => null])->saveQuietly();
                try {
                    (new Facturalo())->forWebDocuments()->save($invoiceInput);
                    self::fail('El movimiento alternativo debe estar respaldado por una referencia de la partida.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('inventory', $exception->errors());
                }
                self::assertNull($note->fresh()->document_id);
                $original->forceFill(['inventory_kardex_id' => $anchor])->saveQuietly();
            }

            $originalMovement = $movement->exactValue('quantity');
            $movement->setExactValues(['quantity' => $originalMovement->minus(1)])->save();
            try {
                (new Facturalo())->forWebDocuments()->save($invoiceInput);
                self::fail('La conversión debe verificar los movimientos registrados por la nota.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('inventory', $exception->errors());
            }
            self::assertSame(0, $db->table('documents')->count());
            self::assertNull($note->fresh()->document_id);
            $movement->setExactValues(['quantity' => $originalMovement])->save();

            $invoice = (new Facturalo())->forWebDocuments()->save($invoiceInput)->getDocument();
            self::assertSame('VES', $invoice->currency_type_id);
            self::assertSame($invoice->id, $note->fresh()->document_id);
            self::assertSame(0, $invoice->inventory_kardex()->where('item_id', $component->id)->count());
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo($remaining));
            self::assertTrue($component->fresh()->exactValue('stock')->isEqualTo($globalStock));

            $source = $invoice->items()->sole();
            self::assertSame((string) $primaryWarehouseId, (string) $source->warehouse_id);
            $creditInput = (new ReflectionMethod($fixture, 'noteInput'))->invoke($fixture, $input, $invoice, '07', '07', [
                ['affected_document_item_id' => $source->id, 'quantity' => '1'],
            ]);
            $credit = (new Facturalo())->forWebDocuments()->save($creditInput)->getDocument();
            self::assertTrue($credit->inventory_kardex()->where('item_id', $component->id)->sole()->exactValue('quantity')->isEqualTo($returned));
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo($remaining->plus($returned)));
            if ($secondaryStock) {
                self::assertTrue($secondaryStock->fresh()->exactValue('stock')->isEqualTo('20'));
                self::assertSame((string) $line['warehouse_id'], (string) $original->fresh()->warehouse_id);
            }
            self::assertTrue($component->fresh()->exactValue('stock')->isEqualTo($globalStock));
            try {
                $credit->fresh()->forceFill(['state_type_id' => '11'])->save();
                self::fail('Una NC de forma libre conserva los efectos comerciales registrados.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('note', $exception->errors());
            }
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo($remaining->plus($returned)));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public static function componentTypes(): array
    {
        return [
            'modificador' => ['modifier', '6', '2'],
            'producto fusionado' => ['merged', '2', '2/3'],
            'almacén secundario solicitado' => ['merged', '2', '2/3', true],
            'cantidad histórica superior a 2^53' => ['modifier', '', '', false, 'historical'],
            'modificador fraccionario superior a 2^53' => ['modifier', '9007199254740993.000000000001', '1', false, 'precise'],
            'fusionado fraccionario superior a 2^53' => ['merged', '9007199254740993.000000000001', '9007199254740993000000000001/3000000000000', false, 'precise'],
            'edición con modificador' => ['modifier', '6', '2', false, 'edit'],
            'edición con fusionado' => ['merged', '2', '2/3', false, 'edit'],
            'anulación repetida con fusionado en almacén secundario solicitado' => ['merged', '2', '2/3', true, 'void'],
        ];
    }

    public function test_preview_only_groups_lines_with_the_same_component_quantity_per_unit(): void
    {
        $line = array_fill_keys([
            'unit_price', 'unit_value', 'percentage_igv', 'total_value', 'total_base_igv', 'total_igv',
            'total_taxes', 'total_discount', 'total_charge', 'total_other_taxes', 'total',
        ], '0');
        $line['item_id'] = 1;
        $line['item'] = ['esFusionado' => true, 'productosFusionados' => [['id' => 2, 'quantity' => '2']]];
        $one = $line + ['id' => 10, 'quantity' => '1'];
        $three = $line + ['id' => 11, 'quantity' => '3'];
        self::assertSame(['1', '3'], array_column(SaleNoteConversionLines::group([$one, $three]), 'quantity'));
        $sameRatio = array_replace($three, ['quantity' => '1']);
        $grouped = SaleNoteConversionLines::group([$one, $sameRatio]);
        self::assertSame(['2'], array_column($grouped, 'quantity'));
        self::assertSame([10, 11], array_column($grouped[0]['source_sale_note_items'], 'sale_note_item_id'));
    }
}
