<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - quantity: numeric(30,12)
 * - date_of_movement: timestamp
 * - countable: smallint
 * - establishment_id: bigint
 * - contract_item_id: bigint
 * - devolution_item_id: bigint
 * - dispatch_item_id: bigint
 * - document_item_id: bigint
 * - expense_item_id: bigint
 * - fixed_asset_item_id: bigint
 * - fixed_asset_purchase_item_id: bigint
 * - order_form_item_id: bigint
 * - order_note_item_id: bigint
 * - purchase_item_id: bigint
 * - purchase_order_item_id: bigint
 * - purchase_quotation_item_id: bigint
 * - quotation_item_id: bigint
 * - sale_note_item_id: bigint
 * - sale_opportunity_item_id: bigint
 * - technical_service_item_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_movement', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id')->nullable()->default('0')->comment('Relacion con item');
            $table->decimal('quantity', 30, 12)->nullable()->default('0.0000')->comment('Cantidad de venta del item');
            $table->timestamp('date_of_movement')->nullable();
            $table->smallInteger('countable')->nullable()->default('0')->comment('Define si se toma en cuenta para el conteo de inventario');
            $table->bigInteger('establishment_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('contract_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('devolution_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('dispatch_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('document_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('expense_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('fixed_asset_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('fixed_asset_purchase_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('order_form_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('order_note_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('purchase_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('purchase_order_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('purchase_quotation_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('quotation_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('sale_note_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('sale_opportunity_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->bigInteger('technical_service_item_id')->nullable()->default('0')->comment('Relacion con la tabla');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['item_id'], 'item_movement_item_id_index');
            $table->index(['quantity'], 'item_movement_quantity_index');
            $table->index(['date_of_movement'], 'item_movement_date_of_movement_index');
            $table->index(['countable'], 'item_movement_countable_index');
            $table->index(['establishment_id'], 'item_movement_establishment_id_index');
            $table->index(['contract_item_id'], 'item_movement_contract_item_id_index');
            $table->index(['devolution_item_id'], 'item_movement_devolution_item_id_index');
            $table->index(['dispatch_item_id'], 'item_movement_dispatch_item_id_index');
            $table->index(['document_item_id'], 'item_movement_document_item_id_index');
            $table->index(['expense_item_id'], 'item_movement_expense_item_id_index');
            $table->index(['fixed_asset_item_id'], 'item_movement_fixed_asset_item_id_index');
            $table->index(['fixed_asset_purchase_item_id'], 'item_movement_fixed_asset_purchase_item_id_index');
            $table->index(['order_form_item_id'], 'item_movement_order_form_item_id_index');
            $table->index(['order_note_item_id'], 'item_movement_order_note_item_id_index');
            $table->index(['purchase_item_id'], 'item_movement_purchase_item_id_index');
            $table->index(['purchase_order_item_id'], 'item_movement_purchase_order_item_id_index');
            $table->index(['purchase_quotation_item_id'], 'item_movement_purchase_quotation_item_id_index');
            $table->index(['quotation_item_id'], 'item_movement_quotation_item_id_index');
            $table->index(['sale_note_item_id'], 'item_movement_sale_note_item_id_index');
            $table->index(['sale_opportunity_item_id'], 'item_movement_sale_opportunity_item_id_index');
            $table->index(['technical_service_item_id'], 'item_movement_technical_service_item_id_index');
        });
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_item_id_range" CHECK ("item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_countable_range" CHECK ("countable" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_establishment_id_range" CHECK ("establishment_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_contract_item_id_range" CHECK ("contract_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_devolution_item_id_range" CHECK ("devolution_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_dispatch_item_id_range" CHECK ("dispatch_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_document_item_id_range" CHECK ("document_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_expense_item_id_range" CHECK ("expense_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_fixed_asset_item_id_range" CHECK ("fixed_asset_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_fixed_asset_purchase_item_id_range" CHECK ("fixed_asset_purchase_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_order_form_item_id_range" CHECK ("order_form_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_order_note_item_id_range" CHECK ("order_note_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_purchase_item_id_range" CHECK ("purchase_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_purchase_order_item_id_range" CHECK ("purchase_order_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_purchase_quotation_item_id_range" CHECK ("purchase_quotation_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_quotation_item_id_range" CHECK ("quotation_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_sale_note_item_id_range" CHECK ("sale_note_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_sale_opportunity_item_id_range" CHECK ("sale_opportunity_item_id" >= 0)');
        DB::statement('ALTER TABLE "item_movement" ADD CONSTRAINT "item_movement_technical_service_item_id_range" CHECK ("technical_service_item_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_movement');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
