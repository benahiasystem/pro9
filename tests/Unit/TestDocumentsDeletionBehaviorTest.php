<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\OptionController;
use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentPayment;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\SaleNotePayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TestDocumentsDeletionBehaviorTest extends TestCase
{
    // ######## INICIO PC-17 PRUEBA COMPORTAMENTAL DE ELIMINACIÓN ########
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        // El caso cubre la limpieza referencial, no listeners ajenos a esta operación.
        Model::unsetEventDispatcher();

        $schema = Schema::connection('tenant');
        foreach ([
            'documents', 'document_items', 'document_payments', 'cash_document_payments',
            'document_taxes', 'document_currency_totals', 'document_received_retentions',
            'document_guarantee_funds', 'document_fiscal_data', 'document_emissions', 'document_note_refunds', 'document_note_adjustments',
            'global_payments', 'payment_commissions', 'document_fee', 'document_hotels', 'document_transports',
            'invoices', 'notes', 'kardex', 'cash_documents',
            'payment_method_types', 'card_brands', 'inventory_kardex', 'payment_files', 'payment_links',
            'payment_link_payments', 'users', 'fiscal_environments',
            'sale_notes', 'sale_note_items', 'sale_note_payments', 'sale_note_fees',
            'hotel_rent_orders', 'hotel_rent_items', 'hotel_rent_item_payments', 'dispatch_sale_notes',
        ] as $table) {
            $schema->create($table, function ($table): void {
                $table->increments('id');
                $table->unsignedInteger('document_id')->nullable();
                $table->unsignedInteger('affected_document_id')->nullable();
                $table->unsignedInteger('document_payment_id')->nullable();
                $table->unsignedInteger('receipt_parent_id')->nullable();
                $table->unsignedInteger('affected_document_item_id')->nullable();
                $table->dateTime('reversed_at')->nullable();
                $table->string('payment_type')->nullable();
                $table->unsignedInteger('payment_id')->nullable();
                $table->unsignedInteger('source_global_payment_id')->nullable();
                $table->string('payment_method_type_id')->nullable();
                $table->unsignedInteger('card_brand_id')->nullable();
                $table->string('inventory_kardexable_type')->nullable();
                $table->unsignedInteger('inventory_kardexable_id')->nullable();
                $table->unsignedInteger('sale_note_id')->nullable();
                $table->unsignedInteger('sale_note_payment_id')->nullable();
                $table->unsignedInteger('inventory_kardex_id')->nullable();
                $table->unsignedInteger('hotel_rent_order_id')->nullable();
                $table->unsignedInteger('hotel_rent_item_id')->nullable();
            });
        }
    }

    /** @test */
    public function it_removes_sale_note_items_before_their_inventory_kardex(): void
    {
        $connection = DB::connection('tenant');
        $connection->table('sale_notes')->insert(['id' => 301]);
        $connection->table('inventory_kardex')->insert([
            'id' => 401,
            'inventory_kardexable_type' => SaleNote::class,
            'inventory_kardexable_id' => 301,
        ]);
        $connection->table('sale_note_items')->insert([
            'sale_note_id' => 301,
            'inventory_kardex_id' => 401,
        ]);
        $connection->table('sale_note_payments')->insert(['id' => 501, 'sale_note_id' => 301]);
        $connection->table('cash_document_payments')->insert(['sale_note_payment_id' => 501]);
        $origin=$connection->table('global_payments')->insertGetId([
            'payment_type' => SaleNotePayment::class,
            'payment_id' => 501,
        ]);
        $commission=$connection->table('payment_commissions')->insertGetId(['source_global_payment_id'=>$origin]);
        $connection->table('global_payments')->insert(['payment_type'=>\Modules\Finance\Models\PaymentCommission::class,'payment_id'=>$commission]);
        $connection->table('hotel_rent_orders')->insert(['id'=>701,'sale_note_id'=>301]);
        $connection->table('hotel_rent_items')->insert(['id'=>702,'hotel_rent_order_id'=>701]);
        $connection->table('hotel_rent_item_payments')->insert(['id'=>703,'hotel_rent_item_id'=>702]);
        $hotelOrigin=$connection->table('global_payments')->insertGetId(['payment_type'=>\Modules\Hotel\Models\HotelRentItemPayment::class,'payment_id'=>703]);
        $hotelCommission=$connection->table('payment_commissions')->insertGetId(['source_global_payment_id'=>$hotelOrigin]);
        $connection->table('global_payments')->insert(['payment_type'=>\Modules\Finance\Models\PaymentCommission::class,'payment_id'=>$hotelCommission]);
        $connection->table('kardex')->insert(['sale_note_id' => 301]);
        $connection->table('cash_documents')->insert(['sale_note_id' => 301]);

        $saleNote = new SaleNote();
        $saleNote->setRawAttributes(['id' => 301], true);

        $method = new \ReflectionMethod(OptionController::class, 'deleteSaleNoteRelations');
        $method->setAccessible(true);
        $method->invoke(new OptionController(), collect([$saleNote]));

        foreach ([
            'sale_note_items', 'inventory_kardex', 'sale_note_payments',
            'cash_document_payments', 'global_payments', 'payment_commissions', 'kardex',
            'cash_documents', 'sale_notes', 'hotel_rent_item_payments',
        ] as $table) {
            self::assertSame(0, $connection->table($table)->count(), $table);
        }
    }

    /** @test */
    public function it_removes_document_dependencies_before_removing_the_test_document(): void
    {
        $connection = DB::connection('tenant');
        $connection->table('documents')->insert(['id' => 101]);
        foreach (['document_taxes','document_currency_totals','document_received_retentions','document_guarantee_funds','document_fiscal_data','document_emissions'] as $table) $connection->table($table)->insert(['document_id'=>101]);
        $connection->table('document_items')->insert(['document_id' => 101]);
        $connection->table('inventory_kardex')->insert([
            'inventory_kardexable_type' => Document::class,
            'inventory_kardexable_id' => 101,
        ]);
        $connection->table('document_payments')->insert(['id' => 201, 'document_id' => 101]);
        $connection->table('cash_document_payments')->insert(['document_payment_id' => 201]);
        $origin=$connection->table('global_payments')->insertGetId([
            'payment_type' => DocumentPayment::class,
            'payment_id' => 201,
        ]);
        $commission=$connection->table('payment_commissions')->insertGetId(['source_global_payment_id'=>$origin]);
        $connection->table('global_payments')->insert(['payment_type'=>\Modules\Finance\Models\PaymentCommission::class,'payment_id'=>$commission]);

        foreach (['document_fee', 'document_hotels', 'document_transports', 'invoices', 'kardex', 'cash_documents'] as $table) {
            $connection->table($table)->insert(['document_id' => 101]);
        }
        $connection->table('notes')->insert(['document_id' => 101]);
        $connection->table('notes')->insert(['affected_document_id' => 101]);

        $document = new Document();
        $document->setRawAttributes(['id' => 101], true);

        $method = new \ReflectionMethod(OptionController::class, 'deleteDocumentRelations');
        $method->setAccessible(true);
        $method->invoke(new OptionController(), collect([$document]));
        self::assertFalse(Schema::connection('tenant')->hasTable('summary_documents'));

        foreach ([
            'document_items', 'document_payments', 'cash_document_payments', 'global_payments', 'payment_commissions',
            'document_fee', 'document_hotels', 'document_transports', 'invoices', 'notes',
            'kardex', 'cash_documents', 'documents',
            'inventory_kardex', 'document_taxes','document_currency_totals','document_received_retentions',
            'document_guarantee_funds','document_fiscal_data','document_emissions',
        ] as $table) {
            self::assertSame(0, $connection->table($table)->count(), $table);
        }
    }
    // ######## FIN PC-17 PRUEBA COMPORTAMENTAL DE ELIMINACIÓN ########
}
