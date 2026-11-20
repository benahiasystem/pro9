<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - date_of_issue: date
 * - item_id: bigint
 * - inventory_kardexable_id: bigint
 * - inventory_kardexable_type: varchar(255)
 * - warehouse_id: bigint
 * - quantity: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_kardex', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date_of_issue');
            $table->bigInteger('item_id');
            $table->bigInteger('inventory_kardexable_id');
            $table->string('inventory_kardexable_type', 255)->collation('pro9_ci_ai');
            $table->bigInteger('warehouse_id');
            $table->decimal('quantity', 30, 12);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['warehouse_id'], 'inventory_kardex_warehouse_id_foreign');
            $table->index(['item_id', 'warehouse_id', 'date_of_issue'], 'inventory_kardex_item_wh_date_index');
            $table->index(['inventory_kardexable_type', 'inventory_kardexable_id'], 'inventory_kardex_kardexable_index');
        });
        DB::statement('ALTER TABLE "inventory_kardex" ADD CONSTRAINT "inventory_kardex_inventory_kardexable_id_range" CHECK ("inventory_kardexable_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_kardex');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
