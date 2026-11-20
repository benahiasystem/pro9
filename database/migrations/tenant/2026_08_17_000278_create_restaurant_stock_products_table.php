<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - stock: numeric(30,12)
 * - quantity_reserved: numeric(30,12)
 * - has_supplies: boolean
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_stock_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id');
            $table->decimal('stock', 30, 12)->default('0.0000');
            $table->decimal('quantity_reserved', 30, 12)->default('0.0000');
            $table->boolean('has_supplies')->default(false);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['item_id'], 'restaurant_stock_products_item_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_stock_products');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
