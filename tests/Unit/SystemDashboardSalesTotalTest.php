<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Dashboard\Helpers\DashboardData;
use Tests\TestCase;

class SystemDashboardSalesTotalTest extends TestCase
{
    private ?array $originalTenantConnection = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalTenantConnection = config('database.connections.tenant');
        config()->set('database.connections.tenant', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('tenant');
        // No payment, fee or fiscal relation tables: sales must depend only on documents.
        Schema::connection('tenant')->create('documents', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('state_type_id');
            $table->string('document_type_id');
            $table->string('currency_type_id');
            $table->decimal('total', 12, 2);
            $table->decimal('exchange_rate_sale', 12, 4);
            $table->date('date_of_issue');
        });
    }

    protected function tearDown(): void
    {
        DB::purge('tenant');
        config()->set('database.connections.tenant', $this->originalTenantConnection);
        DB::purge('tenant');
        parent::tearDown();
    }

    public function test_sales_total_uses_document_amounts_without_loading_payments(): void
    {
        $rows = [
            ['01', '01', 'VES', 100, 1, '2026-10-01'],
            ['03', '08', 'VES', 20, 1, '2026-10-01'],
            ['05', '07', 'VES', 10, 1, '2026-10-01'],
            ['07', '01', 'USD', 10, 40, '2026-10-02'],
            ['13', '08', 'USD', 2, 40, '2026-10-02'],
            ['01', '07', 'USD', 1, 40, '2026-10-02'],
            ['09', '01', 'VES', 900, 1, '2026-10-01'],
            ['11', '07', 'VES', 800, 1, '2026-10-01'],
            ['01', '01', 'VES', 700, 1, '2026-09-30'],
        ];
        foreach ($rows as [$state, $type, $currency, $total, $rate, $date]) {
            DB::connection('tenant')->table('documents')->insert([
                'state_type_id' => $state, 'document_type_id' => $type,
                'currency_type_id' => $currency, 'total' => $total,
                'exchange_rate_sale' => $rate, 'date_of_issue' => $date,
            ]);
        }
        DB::connection('tenant')->enableQueryLog();
        $helper = new DashboardData();
        self::assertSame('550.00', $helper->document_totals_globals('2026-10-01', '2026-10-31'));
        self::assertCount(1, DB::connection('tenant')->getQueryLog());
        self::assertSame('1250.00', $helper->document_totals_globals());
        self::assertSame('0.00', $helper->document_totals_globals('2027-01-01', '2027-01-31'));
    }
}
