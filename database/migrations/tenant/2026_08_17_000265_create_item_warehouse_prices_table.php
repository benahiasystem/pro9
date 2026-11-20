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
 * - price: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_warehouse_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id');
            $table->bigInteger('warehouse_id');
            $table->decimal('price', 30, 12)->default('0.000000');
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['item_id'], 'item_warehouse_prices_item_id_foreign');
            $table->index(['warehouse_id'], 'item_warehouse_prices_warehouse_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_warehouse_prices');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
