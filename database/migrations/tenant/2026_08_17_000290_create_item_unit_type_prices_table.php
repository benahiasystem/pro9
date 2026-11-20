<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_unit_type_id: bigint
 * - price_label_id: bigint
 * - price: numeric(30,12)
 * - is_active: boolean
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_unit_type_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_unit_type_id');
            $table->bigInteger('price_label_id');
            $table->decimal('price', 30, 12)->default('0.00');
            $table->boolean('is_active')->default(true);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['item_unit_type_id', 'price_label_id'], 'unique_item_unit_price_label');
            $table->index(['item_unit_type_id'], 'item_unit_type_prices_item_unit_type_id_position_index');
            $table->index(['price_label_id'], 'item_unit_type_prices_price_label_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_unit_type_prices');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
