<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - guide_id: bigint
 * - item_id: bigint
 * - item_name: varchar(255)
 * - quantity: numeric(30,12)
 * - unit_cost: numeric(30,12)
 * - total: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('guide_id');
            $table->bigInteger('item_id');
            $table->string('item_name', 255)->collation('sab_ci_ai');
            $table->decimal('quantity', 30, 12);
            $table->decimal('unit_cost', 30, 12);
            $table->decimal('total', 30, 12);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['guide_id'], 'guide_items_guide_id_foreign');
            $table->index(['item_id'], 'guide_items_item_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
