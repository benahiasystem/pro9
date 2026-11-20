<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - cat_item_size_id: bigint
 * - active: smallint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_size', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id');
            $table->bigInteger('cat_item_size_id');
            $table->smallInteger('active')->nullable()->default('1')->comment('Define si se encuentra activo');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "item_size" ADD CONSTRAINT "item_size_item_id_range" CHECK ("item_id" >= 0)');
        DB::statement('ALTER TABLE "item_size" ADD CONSTRAINT "item_size_cat_item_size_id_range" CHECK ("cat_item_size_id" >= 0)');
        DB::statement('ALTER TABLE "item_size" ADD CONSTRAINT "item_size_active_range" CHECK ("active" BETWEEN -128 AND 127)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_size');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
