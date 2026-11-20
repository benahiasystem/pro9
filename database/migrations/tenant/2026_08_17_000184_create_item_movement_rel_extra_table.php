<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - item_movement_id: bigint
 * - item_color_id: bigint
 * - item_status_id: bigint
 * - item_unit_business_id: bigint
 * - item_mold_cavities_id: bigint
 * - item_package_measurements_id: bigint
 * - item_units_per_package_id: bigint
 * - item_mold_properties_id: bigint
 * - item_product_family_id: bigint
 * - item_size_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_movement_rel_extra', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id')->nullable()->default('0');
            $table->bigInteger('item_movement_id')->nullable()->default('0');
            $table->bigInteger('item_color_id')->nullable()->default('0');
            $table->bigInteger('item_status_id')->nullable()->default('0');
            $table->bigInteger('item_unit_business_id')->nullable()->default('0');
            $table->bigInteger('item_mold_cavities_id')->nullable()->default('0');
            $table->bigInteger('item_package_measurements_id')->nullable()->default('0');
            $table->bigInteger('item_units_per_package_id')->nullable()->default('0');
            $table->bigInteger('item_mold_properties_id')->nullable()->default('0');
            $table->bigInteger('item_product_family_id')->nullable()->default('0');
            $table->bigInteger('item_size_id')->nullable()->default('0');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['item_movement_id'], 'item_movement_rel_extra_item_movement_id_foreign');
            $table->index(['item_status_id'], 'index_item_status_id');
            $table->index(['item_unit_business_id'], 'index_item_unit_business_id');
            $table->index(['item_mold_cavities_id'], 'index_item_mold_cavities_id');
            $table->index(['item_package_measurements_id'], 'index_item_package_measurements_id');
            $table->index(['item_units_per_package_id'], 'index_item_units_per_package_id');
            $table->index(['item_mold_properties_id'], 'index_item_mold_properties_id');
            $table->index(['item_product_family_id'], 'index_item_product_family_id');
            $table->index(['item_size_id'], 'index_item_size_id');
        });
        DB::statement('ALTER TABLE "item_movement_rel_extra" ADD CONSTRAINT "item_movement_rel_extra_item_id_range" CHECK ("item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement_rel_extra" ADD CONSTRAINT "item_movement_rel_extra_item_color_id_range" CHECK ("item_color_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement_rel_extra" ADD CONSTRAINT "item_movement_rel_extra_item_status_id_range" CHECK ("item_status_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement_rel_extra" ADD CONSTRAINT "item_movement_rel_extra_item_unit_business_id_range" CHECK ("item_unit_business_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement_rel_extra" ADD CONSTRAINT "item_movement_rel_extra_item_mold_cavities_id_range" CHECK ("item_mold_cavities_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement_rel_extra" ADD CONSTRAINT "item_movement_rel_extra_item_package_measurements_id_range" CHECK ("item_package_measurements_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement_rel_extra" ADD CONSTRAINT "item_movement_rel_extra_item_units_per_package_id_range" CHECK ("item_units_per_package_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement_rel_extra" ADD CONSTRAINT "item_movement_rel_extra_item_mold_properties_id_range" CHECK ("item_mold_properties_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement_rel_extra" ADD CONSTRAINT "item_movement_rel_extra_item_product_family_id_range" CHECK ("item_product_family_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement_rel_extra" ADD CONSTRAINT "item_movement_rel_extra_item_size_id_range" CHECK ("item_size_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_movement_rel_extra');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
