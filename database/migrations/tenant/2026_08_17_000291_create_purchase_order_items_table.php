<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - purchase_order_id: bigint
 * - item_id: bigint
 * - item: json
 * - quantity: integer
 * - unit_value: numeric(30,12)
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
 * - quantity_factor: numeric(30,12)
 * - name_product_pdf: text
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('purchase_order_id');
            $table->bigInteger('item_id');
            $table->json('item');
            $table->integer('quantity');
            $table->decimal('unit_value', 30, 12);
            $table->string('affectation_igv_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('total_base_igv', 30, 12);
            $table->decimal('percentage_igv', 30, 12);
            $table->decimal('total_igv', 30, 12);
            $table->decimal('total_base_other_taxes', 30, 12)->default('0.00');
            $table->decimal('percentage_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_taxes', 30, 12);
            $table->string('price_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('unit_price', 30, 12);
            $table->decimal('total_value', 30, 12);
            $table->decimal('total_charge', 30, 12)->default('0.00');
            $table->decimal('total_discount', 30, 12)->default('0.00');
            $table->decimal('total', 30, 12);
            $table->json('attributes')->nullable();
            $table->json('discounts')->nullable();
            $table->json('charges')->nullable();
            $table->decimal('quantity_factor', 30, 12)->default('1.0000');
            $table->text('name_product_pdf')->collation('pro9_ci_ai')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['purchase_order_id'], 'purchase_order_items_purchase_order_id_foreign');
            $table->index(['item_id'], 'purchase_order_items_item_id_foreign');
            $table->index(['affectation_igv_type_id'], 'purchase_order_items_affectation_igv_type_id_foreign');
            $table->index(['price_type_id'], 'purchase_order_items_price_type_id_foreign');
        });
        DB::statement('ALTER TABLE "purchase_order_items" ADD CONSTRAINT "purchase_order_items_quantity_range" CHECK ("quantity" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
