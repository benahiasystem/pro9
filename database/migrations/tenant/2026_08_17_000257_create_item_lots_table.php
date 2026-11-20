<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - series: varchar(255)
 * - date: date
 * - item_id: bigint
 * - warehouse_id: bigint
 * - item_loteable_type: varchar(255)
 * - item_loteable_id: bigint
 * - has_sale: boolean
 * - state: varchar(20)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_lots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('series', 255)->collation('pro9_ci_ai');
            $table->date('date');
            $table->bigInteger('item_id');
            $table->bigInteger('warehouse_id')->nullable();
            $table->string('item_loteable_type', 255)->collation('pro9_ci_ai');
            $table->bigInteger('item_loteable_id');
            $table->boolean('has_sale')->default(false);
            $table->string('state', 20)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['item_id'], 'item_lots_item_id_foreign');
            $table->index(['warehouse_id'], 'item_lots_warehouse_id_foreign');
            $table->index(['series'], 'item_lots_series_index');
            $table->index(['date'], 'item_lots_date_index');
            $table->index(['has_sale'], 'item_lots_has_sale_index');
            $table->index(['item_loteable_type'], 'item_lots_item_loteable_type_index');
            $table->index(['item_loteable_id'], 'item_lots_item_loteable_id_index');
        });
        DB::statement('ALTER TABLE "item_lots" ADD CONSTRAINT "item_lots_item_loteable_id_range" CHECK ("item_loteable_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_lots');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
