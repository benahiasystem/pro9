<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\DocumentController;
use App\Services\ExchangeRates\TenantExchangeRateService;
use Modules\FullSuscription\Models\Tenant\UserRelSuscriptionPlan;
use Modules\FullSuscription\Services\SuscriptionDocumentService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\PostgresTestEnvironment;

/** @group postgres-integration */
final class SubscriptionPrecisionPostgresTest extends TestCase
{
    /** @dataProvider storedItemSources */
    public function test_stored_decimals_reach_the_invoice_snapshot(bool $fromPlan, string $rate, string $advance, string $expectedPrice, string $expectedLine, string $expectedAdvance, string $expectedTotal, ?string $adjustment = null, ?string $expectedDiscount = null, ?string $expectedCharge = null): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            PostgresTestEnvironment::assertServer($db);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $app->instance(TenantExchangeRateService::class, new class($rate) extends TenantExchangeRateService {
                public function __construct(private string $rate) {}
                public function exchange($date): array { return ['sale' => $this->rate]; }
            });
            $db->statement('CREATE TABLE plans (id INT PRIMARY KEY, name VARCHAR(100), limit_documents INT, include_sale_notes_limit_documents BOOLEAN)');
            $db->statement('CREATE TABLE clients (id INT PRIMARY KEY, hostname_id INT, name VARCHAR(100), plan_id INT, whatsapp_messages_limit_override INT NULL)');
            $db->table('plans')->insert(['id' => 1, 'name' => 'Prueba', 'limit_documents' => 0, 'include_sale_notes_limit_documents' => 0]);
            $db->table('clients')->insert(['id' => 1, 'hostname_id' => 1, 'name' => 'Prueba', 'plan_id' => 1]);
            $hostname = new \App\Tenancy\Models\Hostname();
            $hostname->id = 1;
            $tenancy = $this->getMockBuilder(\App\Tenancy\Environment::class)->disableOriginalConstructor()->onlyMethods(['hostname'])->getMock();
            $tenancy->method('hostname')->willReturn($hostname);
            $app->instance(\App\Tenancy\Environment::class, $tenancy);
            $root = sys_get_temp_dir().'/'.$db->getDatabaseName();
            config(['view.compiled' => $root.'/subscription-precision-views', 'view.paths' => [base_path('resources/views')],
                'filesystems.disks.public' => ['driver' => 'local', 'root' => $root.'/subscription-precision-public']]);
            if (!is_dir(config('view.compiled'))) mkdir(config('view.compiled'), 0777, true);
            $app->register(\Illuminate\View\ViewServiceProvider::class);
            $app->instance(DocumentController::class, new DocumentController());
            $row = $input['items'][0];
            if ($adjustment !== null) {
                $db->table('cat_charge_discount_types')->insert(['id' => '91', 'active' => true, 'base' => true, 'level' => 'item', 'type' => 'charge', 'description' => 'Cargo de prueba']);
                $row['affectation_igv_type_id'] = '20';
                $row['discounts'] = $adjustment === 'tiny' ? [] : [[
                    'discount_type_id' => '00', 'description' => 'Descuento', 'is_amount' => $adjustment === 'fixed',
                    'percentage' => $adjustment === 'fixed' ? '2' : '10', 'amount' => '999', 'amount_exact' => ['n' => '999', 'd' => '1'], 'factor' => '9',
                ]];
                $row['charges'] = [['charge_type_id' => '91', 'description' => 'Cargo', 'percentage' => $adjustment === 'tiny' ? '0.000000000001' : '5', 'factor' => '9', 'amount' => '999']];
            }
            $row['quantity'] = '__exact_quantity__';
            $row['unit_price'] = '__exact_price__';
            $json = str_replace(['"__exact_quantity__"', '"__exact_price__"'], ['0.000000000001', '199999999999999.99'], json_encode([$row], JSON_THROW_ON_ERROR));
            $plan = $db->table('suscription_plans')->insertGetId(['name' => 'Precisión', 'description' => 'Prueba temporal', 'total' => '300']);
            if ($fromPlan) {
                $planRow = array_intersect_key($input['items'][0], array_flip([
                    'item_id', 'quantity', 'unit_value', 'unit_price', 'price_type_id', 'affectation_igv_type_id',
                    'total_base_igv', 'percentage_igv', 'total_igv', 'total_taxes', 'total_value', 'total', 'warehouse_id',
                ]));
                if ($adjustment !== null) {
                    $planRow['affectation_igv_type_id'] = '20';
                    $planRow['discounts'] = json_encode($row['discounts'], JSON_THROW_ON_ERROR);
                    $planRow['charges'] = json_encode($row['charges'], JSON_THROW_ON_ERROR);
                }
                $db->table('item_rel_suscription_plans')->insert(array_replace($planRow, [
                    'suscription_plan_id' => $plan, 'quantity' => '0.000000000001', 'unit_price' => '199999999999999.99',
                ]));
                $json = '[]';
            }
            $id = $db->table('user_rel_suscription_plans')->insertGetId([
                'suscription_plan_id' => $plan, 'user_id' => $input['user_id'], 'items' => $json,
                'customer_id' => $input['customer_id'], 'customer' => json_encode($input['customer']),
                'parent_customer' => '{}', 'currency_type_id' => 'USD', 'total' => '600', 'total_prepayment' => $advance,
            ]);
            $producer = new class { use SuscriptionDocumentService; };
            $result = $producer->document(UserRelSuscriptionPlan::findOrFail($id));
            self::assertTrue($result['success'], $result['message'] ?? 'No se guardó la factura.');
            $document = \App\Models\Tenant\Document::findOrFail($result['document_id']);
            $line = $document->items->first();
            self::assertSame('VES', $document->currency_type_id);
            self::assertTrue($line->exactValue('quantity')->isEqualTo('0.000000000001'));
            self::assertTrue($line->exactValue('unit_price')->isEqualTo($expectedPrice));
            self::assertTrue($line->exactValue('total')->isEqualTo($expectedLine));
            self::assertTrue($document->exactValue('total_prepayment')->isEqualTo($expectedAdvance));
            self::assertTrue($document->exactValue('total')->isEqualTo($expectedTotal));
            if ($adjustment !== null) {
                self::assertTrue($line->exactValue('total_discount')->isEqualTo($expectedDiscount));
                self::assertTrue($line->exactValue('total_charge')->isEqualTo($expectedCharge));
            }
            self::assertSame($json, $db->table('user_rel_suscription_plans')->where('id', $id)->value('items'));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public static function storedItemSources(): array
    {
        return [
            'JSON anticipo de alta precisión' => [false, '3', '100.123456789123', '599999999999999.97', '599.99999999999997', '300.370370367369', '299.62962963263097'],
            'plan anticipo de alta precisión' => [true, '3', '100.123456789123', '599999999999999.97', '599.99999999999997', '300.370370367369', '299.62962963263097'],
            'JSON anticipo subprecisión' => [false, '1.5', '0.000000000001', '299999999999999.985', '299.999999999999985', '0.0000000000015', '299.999999999998485'],
            'plan anticipo subprecisión' => [true, '1.5', '0.000000000001', '299999999999999.985', '299.999999999999985', '0.0000000000015', '299.999999999998485'],
            'JSON descuento porcentual y cargo' => [false, '3', '0', '599999999999999.97', '569.9999999999999715', '0', '569.9999999999999715', 'percentage', '59.999999999999997', '29.9999999999999985'],
            'plan descuento porcentual y cargo' => [true, '3', '0', '599999999999999.97', '569.9999999999999715', '0', '569.9999999999999715', 'percentage', '59.999999999999997', '29.9999999999999985'],
            'JSON descuento fijo y cargo' => [false, '3', '0', '599999999999999.97', '623.9999999999999685', '0', '623.9999999999999685', 'fixed', '6', '29.9999999999999985'],
            'plan descuento fijo y cargo' => [true, '3', '0', '599999999999999.97', '623.9999999999999685', '0', '623.9999999999999685', 'fixed', '6', '29.9999999999999985'],
            'JSON cargo de porcentaje mínimo' => [false, '3', '0', '599999999999999.97', '600.0000000000059699999999999997', '0', '600.0000000000059699999999999997', 'tiny', '0', '0.0000000000059999999999999997'],
            'plan cargo de porcentaje mínimo' => [true, '3', '0', '599999999999999.97', '600.0000000000059699999999999997', '0', '600.0000000000059699999999999997', 'tiny', '0', '0.0000000000059999999999999997'],
        ];
    }
}
