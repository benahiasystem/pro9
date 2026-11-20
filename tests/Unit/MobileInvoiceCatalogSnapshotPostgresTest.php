<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Tenant\Api\MobileController;
use App\Models\Tenant\{Item, ItemUnitType, ItemUnitTypePrice, PriceLabel};
use App\Support\Exact\Decimal as D;
use Illuminate\Http\Request;
use Modules\MobileApp\Http\Controllers\Api\ItemController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class MobileInvoiceCatalogSnapshotPostgresTest extends TestCase
{
    public function test_mobile_invoice_catalog_preserves_the_fraction_behind_display_prices(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false, 'decimal_quantity' => 2]);
            $item = Item::findOrFail($input['items'][0]['item_id']);
            $item->setExactValues(['sale_unit_price' => D::of('1/3')])->save();
            $unit = new ItemUnitType(['item_id' => $item->id, 'description' => 'Presentación temporal',
                'unit_type_id' => 'UND', 'price_default' => 1]);
            $unit->setExactValues(['quantity_unit' => D::of('1/3'), 'price1' => '0.25'])->save();
            $first = PriceLabel::firstOrCreate(['position' => 1], ['label' => 'Precio temporal', 'is_active' => true]);
            $second = PriceLabel::firstOrCreate(['position' => 2], ['label' => 'Precio grande', 'is_active' => true]);
            $missing = PriceLabel::firstOrCreate(['position' => 3], ['label' => 'Precio sin configurar', 'is_active' => true]);
            foreach ([[$first, '1/3'], [$second, '9007199254740993.01']] as [$label, $value]) {
                (new ItemUnitTypePrice(['item_unit_type_id' => $unit->id, 'price_label_id' => $label->id, 'is_active' => true]))
                    ->setExactValues(['price' => D::of($value)])->save();
            }
            $request = Request::create('/api/items/records-sale', 'GET', [
                'input' => $item->internal_id, 'warehouse_id' => $input['items'][0]['warehouse_id'], 'limit' => 10,
            ]);
            $controller = new ItemController();
            $result = collect($controller->recordsSale($request)->resolve($request))->firstWhere('id', $item->id);
            self::assertNotNull($result);
            self::assertSame('0.33', $result['sale_unit_price']);
            $fraction = ['n' => '1', 'd' => '3'];
            self::assertSame($fraction, $result['calculation_snapshot']['values']['sale_unit_price'] ?? null);
            $presentation = collect($result['item_unit_types'])->sole('id', $unit->id);
            self::assertSame('0.33', $presentation['quantity_unit']);
            self::assertSame($fraction, $presentation['calculation_snapshot']['values']['quantity_unit']);
            $price = collect($presentation['prices'])->sole('price_label_id', $first->id);
            self::assertSame('0.33', $price['price']);
            self::assertSame($fraction, $price['calculation_snapshot']['values']['price']);
            self::assertSame('9007199254740993.01', collect($presentation['prices'])->sole('price_label_id', $second->id)['price']);
            $unconfigured = collect($presentation['prices'])->sole('price_label_id', $missing->id);
            self::assertSame(0, $unconfigured['price']);
            self::assertArrayNotHasKey('calculation_snapshot', $unconfigured);
            $scroll = collect($controller->byScroll($request)['data']->resolve($request))->sole('id', $item->id);
            self::assertSame($result['calculation_snapshot'], $scroll['calculation_snapshot']);
            self::assertSame($presentation, collect($scroll['item_unit_types'])->sole('id', $unit->id));

            $mobile = new MobileController();
            foreach ([$mobile->searchItems($request), $mobile->tables()] as $response) {
                $product = collect($response['data']['items'])->sole('id', $item->id);
                $option = collect($product['item_unit_types'])->sole('id', $unit->id);
                self::assertSame('0.33', $option['price1']);
                self::assertSame($fraction, $option['calculation_snapshot']['values']['price1']);
                self::assertSame($fraction, $option['calculation_snapshot']['values']['quantity_unit']);
                self::assertSame('0.00', $option['price3']);
                self::assertArrayNotHasKey('price3', $option['calculation_snapshot']['values']);
                self::assertSame($fraction, $product['calculation_snapshot']['values']['sale_unit_price']);
            }
            $legacy = $unit->getCollectionData(2);
            self::assertArrayNotHasKey('calculation_snapshot', $legacy);
            self::assertSame('0.33', $legacy['quantity_unit']);
            self::assertSame('0.33', collect($legacy['prices'])->sole('price_label_id', $first->id)['price']);

            $input['payments'] = [];
            $input['payment_condition_id'] = '02';
            $input['items'][0]['quantity'] = '3';
            $input['items'][0]['unit_price'] = $price['price'];
            $input['items'][0]['affectation_igv_type_id'] = '20';
            $input['items'][0]['item']['presentation'] = ['id' => $presentation['id'], 'quantity_unit' => $presentation['quantity_unit']];
            $input['items'][0]['price_input'] = [
                'amount' => $price['calculation_snapshot']['values']['price'],
                'currency_type_id' => $result['currency_type_id'], 'includes_iva' => true,
            ];
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            self::assertTrue($invoice->items()->sole()->exactValue('unit_price')->isEqualTo('1/3'));
            self::assertTrue($invoice->exactValue('total')->isEqualTo('1'));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
