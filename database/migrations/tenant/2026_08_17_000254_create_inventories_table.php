<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - type: varchar(255)
 * - description: varchar(255)
 * - detail: varchar(255)
 * - item_id: bigint
 * - warehouse_id: bigint
 * - warehouse_destination_id: bigint
 * - inventory_transaction_id: varchar(255)
 * - quantity: numeric(30,12)
 * - lot_code: varchar(255)
 * - inventories_transfer_id: bigint
 * - comments: varchar(255)
 * - date_of_issue: date
 * - guide_id: bigint
 * - system_stock: numeric(30,12)
 * - real_stock: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->string('detail', 255)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('item_id');
            $table->bigInteger('warehouse_id');
            $table->bigInteger('warehouse_destination_id')->nullable();
            $table->string('inventory_transaction_id', 255)->collation('pro9_ci_ai')->nullable();
            $table->decimal('quantity', 30, 12);
            $table->string('lot_code', 255)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('inventories_transfer_id')->nullable();
            $table->string('comments', 255)->collation('pro9_ci_ai')->nullable();
            $table->date('date_of_issue')->nullable();
            $table->bigInteger('guide_id')->nullable();
            $table->decimal('system_stock', 30, 12)->nullable();
            $table->decimal('real_stock', 30, 12)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['item_id'], 'inventories_item_id_foreign');
            $table->index(['warehouse_id'], 'inventories_warehouse_id_foreign');
            $table->index(['inventory_transaction_id'], 'inventories_inventory_transaction_id_foreign');
            $table->index(['inventories_transfer_id'], 'inventories_inventories_transfer_id_foreign');
        });
        DB::statement('ALTER TABLE "inventories" ADD CONSTRAINT "inventories_type_check" CHECK ("type" IN (\'1\',\'2\',\'3\'))');
        DB::statement('ALTER TABLE "inventories" ADD CONSTRAINT "inventories_warehouse_destination_id_range" CHECK ("warehouse_destination_id" >= 0)');
        DB::statement('ALTER TABLE "inventories" ADD CONSTRAINT "inventories_guide_id_range" CHECK ("guide_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
