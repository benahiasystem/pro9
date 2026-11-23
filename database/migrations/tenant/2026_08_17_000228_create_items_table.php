<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(1000)
 * - preparation_area_id: bigint
 * - second_name: varchar(600)
 * - description: varchar(600)
 * - text_filter: text
 * - model: varchar(100)
 * - factory_code: varchar(255)
 * - barcode: varchar(150)
 * - technical_specifications: varchar(300)
 * - item_type_id: char(2)
 * - internal_id: varchar(30)
 * - item_code: varchar(255)
 * - date_of_due: date
 * - account_id: bigint
 * - item_code_gs1: varchar(255)
 * - unit_type_id: varchar(255)
 * - currency_type_id: varchar(255)
 * - sale_unit_price: numeric(30,12)
 * - purchase_has_igv: boolean
 * - has_igv: boolean
 * - purchase_unit_price: numeric(30,12)
 * - restrict_sale_cpe: boolean
 * - exchange_points: boolean
 * - quantity_of_points: numeric(30,12)
 * - commission_amount: numeric(30,12)
 * - line: varchar(255)
 * - commission_type: varchar(255)
 * - suggested_price: numeric(30,12)
 * - sale_affectation_igv_type_id: varchar(255)
 * - purchase_affectation_igv_type_id: varchar(255)
 * - calculate_quantity: boolean
 * - sale_unit_price_set: numeric(30,12)
 * - is_set: boolean
 * - parent_item_id: bigint
 * - favorite: boolean
 * - category_id: bigint
 * - brand_id: bigint
 * - image: varchar(255)
 * - image_medium: varchar(255)
 * - image_small: varchar(255)
 * - stock: numeric(30,12)
 * - stock_min: numeric(30,12)
 * - lot_code: varchar(255)
 * - lots_enabled: boolean
 * - series_enabled: boolean
 * - percentage_of_profit: numeric(30,12)
 * - attributes: json
 * - active: boolean
 * - hidden_search: boolean
 * - web_platform_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 * - warehouse_id: bigint
 * - status: smallint
 * - is_dish: boolean
 * - apply_store: boolean
 * - apply_restaurant: boolean
 * - restaurant_favorite: smallint
 * - cod_digemid: text
 * - sanitary: text
 * - is_for_production: boolean
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 1000)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('preparation_area_id')->nullable();
            $table->string('second_name', 600)->collation('sab_ci_ai')->nullable();
            $table->string('description', 600)->collation('sab_ci_ai')->nullable();
            $table->text('text_filter')->collation('sab_ci_ai')->nullable();
            $table->string('model', 100)->collation('sab_ci_ai')->nullable();
            $table->string('factory_code', 255)->collation('sab_ci_ai')->nullable();
            $table->string('barcode', 150)->collation('sab_ci_ai')->nullable();
            $table->string('technical_specifications', 300)->collation('sab_ci_ai')->nullable();
            $table->char('item_type_id', 2)->collation('sab_ci_ai');
            $table->string('internal_id', 30)->collation('sab_ci_ai')->nullable();
            $table->string('item_code', 255)->collation('sab_ci_ai')->nullable();
            $table->date('date_of_due')->nullable();
            $table->bigInteger('account_id')->nullable();
            $table->string('item_code_gs1', 255)->collation('sab_ci_ai')->nullable();
            $table->string('unit_type_id', 255)->collation('sab_ci_ai');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->decimal('sale_unit_price', 30, 12);
            $table->boolean('purchase_has_igv')->default(true);
            $table->boolean('has_igv')->default(true);
            $table->decimal('purchase_unit_price', 30, 12)->default('0.000000');
            $table->boolean('restrict_sale_cpe')->default(false);
            $table->boolean('exchange_points')->default(false)->comment('sistema por puntos');
            $table->decimal('quantity_of_points', 30, 12)->default('0.00')->comment('sistema por puntos');
            $table->decimal('commission_amount', 30, 12)->nullable();
            $table->string('line', 255)->collation('sab_ci_ai')->nullable();
            $table->string('commission_type', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('suggested_price', 30, 12)->default('0.00');
            $table->string('sale_affectation_igv_type_id', 255)->collation('sab_ci_ai');
            $table->string('purchase_affectation_igv_type_id', 255)->collation('sab_ci_ai');
            $table->boolean('calculate_quantity')->default(false);
            $table->decimal('sale_unit_price_set', 30, 12)->nullable();
            $table->boolean('is_set')->default(false);
            $table->bigInteger('parent_item_id')->nullable();
            $table->boolean('favorite')->default(false);
            $table->bigInteger('category_id')->nullable();
            $table->bigInteger('brand_id')->nullable();
            $table->string('image', 255)->collation('sab_ci_ai')->default('imagen-no-disponible.jpg');
            $table->string('image_medium', 255)->collation('sab_ci_ai')->default('imagen-no-disponible.jpg');
            $table->string('image_small', 255)->collation('sab_ci_ai')->default('imagen-no-disponible.jpg');
            $table->decimal('stock', 30, 12)->default('0.0000');
            $table->decimal('stock_min', 30, 12)->default('0.00');
            $table->string('lot_code', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('lots_enabled')->default(false);
            $table->boolean('series_enabled')->default(false);
            $table->decimal('percentage_of_profit', 30, 12)->default('0.00');
            $table->json('attributes')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('hidden_search')->default(false);
            $table->bigInteger('web_platform_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->bigInteger('warehouse_id')->nullable();
            $table->smallInteger('status')->default('1');
            $table->boolean('is_dish')->default(false);
            $table->boolean('apply_store')->default(false);
            $table->boolean('apply_restaurant')->default(false)->comment('visible en menu restaurante');
            $table->smallInteger('restaurant_favorite')->default('0');
            $table->text('cod_digemid')->collation('sab_ci_ai')->nullable()->comment('Codigo de producto DIGEMID');
            $table->text('sanitary')->collation('sab_ci_ai')->nullable()->comment('Registro sanitario');
            $table->boolean('is_for_production')->default(false)->comment('Define si es compuesto para produccion');
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['item_type_id'], 'items_item_type_id_foreign');
            $table->index(['unit_type_id'], 'items_unit_type_id_foreign');
            $table->index(['currency_type_id'], 'items_currency_type_id_foreign');
            $table->index(['sale_affectation_igv_type_id'], 'items_sale_affectation_igv_type_id_foreign');
            $table->index(['purchase_affectation_igv_type_id'], 'items_purchase_affectation_igv_type_id_foreign');
            $table->index(['warehouse_id'], 'items_warehouse_id_foreign');
            $table->index(['account_id'], 'items_account_id_foreign');
            $table->index(['brand_id'], 'items_brand_id_foreign');
            $table->index(['category_id'], 'items_category_id_foreign');
            $table->index(['second_name'], 'items_second_name_index');
            $table->index(['description'], 'items_description_index');
            $table->index(['internal_id'], 'items_internal_id_index');
            $table->index(['item_code'], 'items_item_code_index');
            $table->index(['web_platform_id'], 'items_web_platform_id_foreign');
            $table->index(['parent_item_id'], 'items_parent_item_id_index');
            $table->index(['favorite'], 'items_favorite_index');
            $table->index(['factory_code'], 'items_factory_code_index');
            $table->index(['barcode'], 'items_barcode_index');
            $table->index(['preparation_area_id'], 'items_preparation_area_id_foreign');
            $table->index(['name'], 'items_name_index');
            $table->fullText(['text_filter'], 'items_text_filter_fulltext')->language('simple');
        });
        DB::statement('ALTER TABLE "items" ADD CONSTRAINT "items_status_range" CHECK ("status" BETWEEN -128 AND 127)');
        DB::statement('ALTER TABLE "items" ADD CONSTRAINT "items_restaurant_favorite_range" CHECK ("restaurant_favorite" BETWEEN -128 AND 127)');
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
