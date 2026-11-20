<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - technical_services_id: bigint
 * - item_id: bigint
 * - item: json
 * - quantity: numeric(30,12)
 * - unit_value: numeric(30,12)
 * - affectation_igv_type_id: text
 * - total_base_igv: numeric(30,12)
 * - percentage_igv: numeric(30,12)
 * - total_igv: numeric(30,12)
 * - total_base_other_taxes: numeric(30,12)
 * - percentage_other_taxes: numeric(30,12)
 * - total_other_taxes: numeric(30,12)
 * - total_taxes: numeric(30,12)
 * - price_type_id: text
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
 * - name_product_pdf: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_service_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('technical_services_id')->nullable()->default('0')->comment('Id de technical_services');
            $table->bigInteger('item_id')->nullable()->default('0')->comment('Id de item');
            $table->json('item')->nullable()->comment('Json con el contenido de item');
            $table->decimal('quantity', 30, 12)->nullable()->default('0.0000')->comment('Cantidad de item usado');
            $table->decimal('unit_value', 30, 12)->nullable()->default('0.000000')->comment('unit_value');
            $table->text('affectation_igv_type_id')->collation('pro9_ci_ai')->nullable()->comment('Tipo de afectacion dde igv. cat_affectation_igv_types');
            $table->decimal('total_base_igv', 30, 12)->nullable()->default('0.00')->comment('Monto base del IGV');
            $table->decimal('percentage_igv', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_igv', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_base_other_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('percentage_other_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_other_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_taxes', 30, 12)->nullable()->default('0.00');
            $table->text('price_type_id')->collation('pro9_ci_ai')->nullable();
            $table->decimal('unit_price', 30, 12)->nullable()->default('0.000000');
            $table->decimal('total_value', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_charge', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_discount', 30, 12)->nullable()->default('0.00');
            $table->decimal('total', 30, 12)->nullable()->default('0.00');
            $table->json('attributes')->nullable()->comment('Atributos');
            $table->json('discounts')->nullable()->comment('Descuentos');
            $table->json('charges')->nullable()->comment('Cargos');
            $table->text('additional_information')->collation('pro9_ci_ai')->nullable()->comment('Informacion adicional');
            $table->bigInteger('warehouse_id')->nullable()->default('0')->comment('Id de item');
            $table->text('name_product_pdf')->collation('pro9_ci_ai')->nullable()->comment('Nombre de producto en el pdf');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "technical_service_items" ADD CONSTRAINT "technical_service_items_technical_services_id_range" CHECK ("technical_services_id" >= 0)');
        DB::statement('ALTER TABLE "technical_service_items" ADD CONSTRAINT "technical_service_items_item_id_range" CHECK ("item_id" >= 0)');
        DB::statement('ALTER TABLE "technical_service_items" ADD CONSTRAINT "technical_service_items_warehouse_id_range" CHECK ("warehouse_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_service_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
