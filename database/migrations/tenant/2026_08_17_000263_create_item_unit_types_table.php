<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - description: varchar(255)
 * - item_id: bigint
 * - unit_type_id: varchar(255)
 * - quantity_unit: numeric(30,12)
 * - price1: numeric(30,12)
 * - price2: numeric(30,12)
 * - price3: numeric(30,12)
 * - price_default: smallint (signed tinyint range, default 2)
 * - barcode: varchar(150)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_unit_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->bigInteger('item_id');
            $table->string('unit_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('quantity_unit', 30, 12);
            $table->decimal('price1', 30, 12)->nullable();
            $table->decimal('price2', 30, 12)->nullable();
            $table->decimal('price3', 30, 12)->nullable();
            $table->smallInteger('price_default')->default(2);
            $table->string('barcode', 150)->collation('pro9_ci_ai')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['unit_type_id'], 'item_unit_types_unit_type_id_foreign');
            $table->index(['item_id'], 'item_unit_types_item_id_foreign');
            $table->index(['barcode'], 'item_unit_types_barcode_index');
        });
        DB::statement('ALTER TABLE "item_unit_types" ADD CONSTRAINT "item_unit_types_price_default_range" CHECK ("price_default" BETWEEN -128 AND 127)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_unit_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
