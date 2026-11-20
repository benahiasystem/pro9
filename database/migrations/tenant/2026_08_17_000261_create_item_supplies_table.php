<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - individual_item_id: bigint
 * - quantity: numeric(30,12)
 * - unit_price: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_supplies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id');
            $table->bigInteger('individual_item_id');
            $table->decimal('quantity', 30, 12)->nullable()->default('0.000');
            $table->decimal('unit_price', 30, 12)->nullable()->default('0.00');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['item_id'], 'item_supplies_item_id_foreign');
            $table->index(['individual_item_id'], 'item_supplies_individual_item_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_supplies');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
