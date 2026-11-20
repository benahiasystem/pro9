<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - supply_id: bigint
 * - quantity: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_item_supplies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id');
            $table->bigInteger('supply_id');
            $table->decimal('quantity', 30, 12)->default('0.0000');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['item_id'], 'restaurant_item_supplies_item_id_foreign');
            $table->index(['supply_id'], 'restaurant_item_supplies_supply_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_item_supplies');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
