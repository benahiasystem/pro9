<?php

namespace Tests\Unit;

use App\Models\Tenant\Item;
use Carbon\Carbon;
use Modules\Inventory\Models\Inventory;
use Modules\Inventory\Models\InventoryKardex;
use Modules\Inventory\Models\InventoryTransfer;
use Tests\TestCase;

class KardexTransferIssueDateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);
    }

    // ########## INICIO CORRECCIÓN FECHA EMISIÓN TRASLADO KARDEX ##########
    /** @test */
    public function it_reports_the_related_transfer_issue_date_not_another_transfer_date(): void
    {
        $movement = new Inventory([
            'type' => 2,
            'description' => 'Traslado',
            'date_of_issue' => '2026-08-15',
        ]);
        $movement->setRelation('inventories_transfer', new InventoryTransfer([
            'series' => 'AT01',
            'number' => '42',
        ]));

        $transfer = $movement->inventories_transfer;
        $transfer->created_at = Carbon::parse('2026-08-31 14:00:00');

        $kardex = new class extends InventoryKardex {
            public function getItemWarehousePriceModel()
            {
                return null;
            }

            public function getWarehouseModel()
            {
                return null;
            }
        };
        $kardex->fill([
            'quantity' => -3,
            'warehouse_id' => 1,
            'item_id' => 1,
            'inventory_kardexable_type' => Inventory::class,
        ]);
        $kardex->created_at = Carbon::parse('2026-08-31 14:00:00');
        $kardex->setRelation('item', new Item(['description' => 'Producto de prueba']));
        $kardex->setRelation('inventory_kardexable', $movement);

        $balance = 0;
        $reportRow = $kardex->getKardexReportCollection($balance);

        self::assertSame('AT01-42', $reportRow['number']);
        self::assertSame('2026-08-31', $reportRow['date_of_issue']);
        self::assertNotSame($movement->date_of_issue->format('Y-m-d'), $reportRow['date_of_issue']);
    }
    // ########## FIN CORRECCIÓN FECHA EMISIÓN TRASLADO KARDEX ##########
}
