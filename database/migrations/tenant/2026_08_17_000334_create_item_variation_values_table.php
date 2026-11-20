<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - product_variable_id: bigint
 * - product_variable_value_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_variation_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id');
            $table->bigInteger('product_variable_id');
            $table->bigInteger('product_variable_value_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['item_id', 'product_variable_id'], 'ivv_item_variable_unique');
            $table->index(['product_variable_id'], 'ivv_variable_fk');
            $table->index(['product_variable_value_id'], 'ivv_value_fk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variation_values');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
