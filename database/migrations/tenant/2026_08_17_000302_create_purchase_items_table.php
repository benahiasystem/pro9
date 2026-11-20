<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - purchase_id: bigint
 * - item_id: bigint
 * - item: json
 * - item_lot_group_id: bigint
 * - lot_code: varchar(255)
 * - quantity: numeric(30,12)
 * - unit_value: numeric(30,12)
 * - date_of_due: date
 * - affectation_igv_type_id: varchar(255)
 * - total_base_igv: numeric(30,12)
 * - percentage_igv: numeric(30,12)
 * - total_igv: numeric(30,12)
 * - total_base_other_taxes: numeric(30,12)
 * - percentage_other_taxes: numeric(30,12)
 * - total_other_taxes: numeric(30,12)
 * - total_taxes: numeric(30,12)
 * - price_type_id: varchar(255)
 * - unit_price: numeric(30,12)
 * - total_value: numeric(30,12)
 * - total_charge: numeric(30,12)
 * - total_discount: numeric(30,12)
 * - total: numeric(30,12)
 * - attributes: json
 * - discounts: json
 * - charges: json
 * - warehouse_id: bigint
 * - name_product_pdf: text
 * - update_purchase_price: boolean
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('purchase_id');
            $table->bigInteger('item_id');
            $table->json('item');
            $table->bigInteger('item_lot_group_id')->nullable();
            $table->string('lot_code', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('quantity', 30, 12);
            $table->decimal('unit_value', 30, 12);
            $table->date('date_of_due')->nullable();
            $table->string('affectation_igv_type_id', 255)->collation('sab_ci_ai');
            $table->decimal('total_base_igv', 30, 12);
            $table->decimal('percentage_igv', 30, 12);
            $table->decimal('total_igv', 30, 12);
            $table->decimal('total_base_other_taxes', 30, 12)->default('0.00');
            $table->decimal('percentage_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_taxes', 30, 12);
            $table->string('price_type_id', 255)->collation('sab_ci_ai');
            $table->decimal('unit_price', 30, 12);
            $table->decimal('total_value', 30, 12);
            $table->decimal('total_charge', 30, 12)->default('0.00');
            $table->decimal('total_discount', 30, 12)->default('0.00');
            $table->decimal('total', 30, 12);
            $table->json('attributes')->nullable();
            $table->json('discounts')->nullable();
            $table->json('charges')->nullable();
            $table->bigInteger('warehouse_id')->nullable();
            $table->text('name_product_pdf')->collation('sab_ci_ai')->nullable();
            $table->boolean('update_purchase_price')->default(false);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['purchase_id'], 'purchase_items_purchase_id_foreign');
            $table->index(['item_id'], 'purchase_items_item_id_foreign');
            $table->index(['affectation_igv_type_id'], 'purchase_items_affectation_igv_type_id_foreign');
            $table->index(['price_type_id'], 'purchase_items_price_type_id_foreign');
            $table->index(['warehouse_id'], 'purchase_items_warehouse_id_foreign');
            $table->index(['item_lot_group_id'], 'purchase_items_item_lot_group_id_index');
        });
        DB::statement('ALTER TABLE "purchase_items" ADD CONSTRAINT "purchase_items_item_lot_group_id_range" CHECK ("item_lot_group_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
