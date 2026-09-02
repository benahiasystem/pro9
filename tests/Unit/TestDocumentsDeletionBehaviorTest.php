<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\OptionController;
use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentPayment;
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
            'global_payments', 'document_fee', 'document_hotels', 'document_transports',
            'invoices', 'notes', 'summary_documents', 'kardex', 'cash_documents',
            'payment_method_types', 'card_brands', 'inventory_kardex', 'payment_files', 'payment_links',
            'payment_link_payments', 'users', 'soap_types',
        ] as $table) {
            $schema->create($table, function ($table): void {
                $table->increments('id');
                $table->unsignedInteger('document_id')->nullable();
                $table->unsignedInteger('affected_document_id')->nullable();
                $table->unsignedInteger('document_payment_id')->nullable();
                $table->string('payment_type')->nullable();
                $table->unsignedInteger('payment_id')->nullable();
                $table->string('payment_method_type_id')->nullable();
                $table->unsignedInteger('card_brand_id')->nullable();
                $table->string('inventory_kardexable_type')->nullable();
                $table->unsignedInteger('inventory_kardexable_id')->nullable();
            });
        }
    }

    /** @test */
    public function it_removes_document_dependencies_before_removing_the_test_document(): void
    {
        $connection = DB::connection('tenant');
        $connection->table('documents')->insert(['id' => 101]);
        $connection->table('document_items')->insert(['document_id' => 101]);
        $connection->table('inventory_kardex')->insert([
            'inventory_kardexable_type' => Document::class,
            'inventory_kardexable_id' => 101,
        ]);
        $connection->table('document_payments')->insert(['id' => 201, 'document_id' => 101]);
        $connection->table('cash_document_payments')->insert(['document_payment_id' => 201]);
        $connection->table('global_payments')->insert([
            'payment_type' => DocumentPayment::class,
            'payment_id' => 201,
        ]);

        foreach (['document_fee', 'document_hotels', 'document_transports', 'invoices', 'summary_documents', 'kardex', 'cash_documents'] as $table) {
            $connection->table($table)->insert(['document_id' => 101]);
        }
        $connection->table('notes')->insert(['document_id' => 101]);
        $connection->table('notes')->insert(['affected_document_id' => 101]);

        $document = new Document();
        $document->setRawAttributes(['id' => 101], true);

        $method = new \ReflectionMethod(OptionController::class, 'deleteDocumentRelations');
        $method->setAccessible(true);
        $method->invoke(new OptionController(), collect([$document]));

        foreach ([
            'document_items', 'document_payments', 'cash_document_payments', 'global_payments',
            'document_fee', 'document_hotels', 'document_transports', 'invoices', 'notes',
            'summary_documents', 'kardex', 'cash_documents', 'documents',
            'inventory_kardex',
        ] as $table) {
            self::assertSame(0, $connection->table($table)->count(), $table);
        }
    }
    // ######## FIN PC-17 PRUEBA COMPORTAMENTAL DE ELIMINACIÓN ########
}
