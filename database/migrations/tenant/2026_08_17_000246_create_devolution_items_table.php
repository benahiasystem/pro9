<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - devolution_id: bigint
 * - item_id: bigint
 * - item: json
 * - quantity: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolution_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('devolution_id');
            $table->bigInteger('item_id');
            $table->json('item');
            $table->decimal('quantity', 30, 12);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['devolution_id'], 'devolution_items_devolution_id_foreign');
            $table->index(['item_id'], 'devolution_items_item_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolution_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
