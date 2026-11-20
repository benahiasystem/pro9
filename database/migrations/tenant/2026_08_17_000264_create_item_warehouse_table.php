<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - warehouse_id: bigint
 * - stock: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_warehouse', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id');
            $table->bigInteger('warehouse_id');
            $table->decimal('stock', 30, 12);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['item_id', 'warehouse_id'], 'item_warehouse_item_id_warehouse_id_unique');
            $table->index(['warehouse_id'], 'item_warehouse_warehouse_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_warehouse');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
