<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - item: json
 * - suscription_plan_id: bigint
 * - quantity: numeric(30,12)
 * - unit_value: numeric(30,12)
 * - affectation_igv_type_id: char(255)
 * - total_base_igv: numeric(30,12)
 * - percentage_igv: numeric(30,12)
 * - total_igv: numeric(30,12)
 * - total_base_other_taxes: numeric(30,12)
 * - percentage_other_taxes: numeric(30,12)
 * - total_other_taxes: numeric(30,12)
 * - total_taxes: numeric(30,12)
 * - price_type_id: char(255)
 * - unit_price: numeric(30,12)
 * - total_value: numeric(30,12)
 * - total_charge: numeric(30,12)
 * - total_discount: numeric(30,12)
 * - total: numeric(30,12)
 * - attributes: json
 * - discounts: json
 * - charges: json
 * - additional_information: text
 * - warehouse_id: bigint
 * - apply_in_period: varchar(255)
 * - name_product_pdf: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_rel_suscription_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id')->nullable()->default('0')->comment('Relacion con items');
            $table->json('item')->nullable();
            $table->bigInteger('suscription_plan_id')->nullable()->default('0')->comment('Relacion con planes de suscripcion');
            $table->decimal('quantity', 30, 12)->nullable()->default('0.00');
            $table->decimal('unit_value', 30, 12)->nullable()->default('0.00');
            $table->char('affectation_igv_type_id', 255)->collation('pro9_ci_ai')->nullable();
            $table->decimal('total_base_igv', 30, 12)->nullable()->default('0.00');
            $table->decimal('percentage_igv', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_igv', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_base_other_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('percentage_other_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_other_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_taxes', 30, 12)->nullable()->default('0.00');
            $table->char('price_type_id', 255)->collation('pro9_ci_ai')->nullable();
            $table->decimal('unit_price', 30, 12)->nullable()->default('0.000000');
            $table->decimal('total_value', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_charge', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_discount', 30, 12)->nullable()->default('0.00');
            $table->decimal('total', 30, 12)->nullable()->default('0.00');
            $table->json('attributes')->nullable();
            $table->json('discounts')->nullable();
            $table->json('charges')->nullable();
            $table->text('additional_information')->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('warehouse_id')->nullable()->default('0');
            $table->string('apply_in_period', 255)->collation('pro9_ci_ai')->default('all');
            $table->text('name_product_pdf')->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "item_rel_suscription_plans" ADD CONSTRAINT "item_rel_suscription_plans_item_id_range" CHECK ("item_id" >= 0)');
        DB::statement('ALTER TABLE "item_rel_suscription_plans" ADD CONSTRAINT "item_rel_suscription_plans_suscription_plan_id_range" CHECK ("suscription_plan_id" >= 0)');
        DB::statement('ALTER TABLE "item_rel_suscription_plans" ADD CONSTRAINT "item_rel_suscription_plans_warehouse_id_range" CHECK ("warehouse_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_rel_suscription_plans');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
