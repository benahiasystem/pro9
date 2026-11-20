<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - mill_id: bigint
 * - height_to_mill: numeric(30,12)
 * - total_height: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - item: json
 * - quantity: numeric(30,12)
 * - item_extra_data: json
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mill_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id')->nullable()->default('0');
            $table->bigInteger('mill_id')->nullable()->default('0');
            $table->decimal('height_to_mill', 30, 12)->nullable()->default('0.000')->comment('Peso de entrada');
            $table->decimal('total_height', 30, 12)->nullable()->default('0.000')->comment('Peso dle insumo ');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('item');
            $table->decimal('quantity', 30, 12)->nullable()->default('0.000')->comment('Peso dle insumo ');
            $table->json('item_extra_data')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "mill_items" ADD CONSTRAINT "mill_items_item_id_range" CHECK ("item_id" >= 0)');
        DB::statement('ALTER TABLE "mill_items" ADD CONSTRAINT "mill_items_mill_id_range" CHECK ("mill_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mill_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
