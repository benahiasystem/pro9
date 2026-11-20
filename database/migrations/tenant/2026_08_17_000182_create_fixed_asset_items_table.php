<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - description: varchar(255)
 * - item_type_id: char(2)
 * - internal_id: varchar(255)
 * - unit_type_id: varchar(255)
 * - currency_type_id: varchar(255)
 * - purchase_unit_price: numeric(30,12)
 * - purchase_affectation_igv_type_id: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->string('description', 255)->collation('pro9_ci_ai')->nullable();
            $table->char('item_type_id', 2)->collation('pro9_ci_ai');
            $table->string('internal_id', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('unit_type_id', 255)->collation('pro9_ci_ai');
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('purchase_unit_price', 30, 12);
            $table->string('purchase_affectation_igv_type_id', 255)->collation('pro9_ci_ai');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['item_type_id'], 'fixed_asset_items_item_type_id_foreign');
            $table->index(['unit_type_id'], 'fixed_asset_items_unit_type_id_foreign');
            $table->index(['currency_type_id'], 'fixed_asset_items_currency_type_id_foreign');
            $table->index(['purchase_affectation_igv_type_id'], 'fixed_asset_items_purchase_affectation_igv_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
