<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - purchase_id: bigint
 * - supply_id: bigint
 * - quantity: numeric(30,12)
 * - cost: numeric(30,12)
 * - affectation_igv_type_id: char(2)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_supplies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('purchase_id');
            $table->bigInteger('supply_id');
            $table->decimal('quantity', 30, 12);
            $table->decimal('cost', 30, 12);
            $table->char('affectation_igv_type_id', 2)->collation('sab_ci_ai')->default('10');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['purchase_id'], 'purchase_supplies_purchase_id_foreign');
            $table->index(['supply_id'], 'purchase_supplies_supply_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_supplies');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
