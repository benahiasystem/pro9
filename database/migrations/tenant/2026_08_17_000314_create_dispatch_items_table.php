<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - dispatch_id: bigint
 * - item_id: bigint
 * - item: json
 * - quantity: numeric(30,12)
 * - name_product_pdf: text
 * - additional_data: json
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('dispatch_id');
            $table->bigInteger('item_id');
            $table->json('item');
            $table->decimal('quantity', 30, 12);
            $table->text('name_product_pdf')->collation('sab_ci_ai')->nullable();
            $table->json('additional_data')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['dispatch_id'], 'dispatch_items_dispatch_id_foreign');
            $table->index(['item_id'], 'dispatch_items_item_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
