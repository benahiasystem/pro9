<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - cat_item_mold_cavities_id: bigint
 * - active: smallint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_mold_cavities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id');
            $table->bigInteger('cat_item_mold_cavities_id');
            $table->smallInteger('active')->nullable()->default('1')->comment('Define si se encuentra activo');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "item_mold_cavities" ADD CONSTRAINT "item_mold_cavities_item_id_range" CHECK ("item_id" >= 0)');
        DB::statement('ALTER TABLE "item_mold_cavities" ADD CONSTRAINT "item_mold_cavities_cat_item_mold_cavities_id_range" CHECK ("cat_item_mold_cavities_id" >= 0)');
        DB::statement('ALTER TABLE "item_mold_cavities" ADD CONSTRAINT "item_mold_cavities_active_range" CHECK ("active" BETWEEN -128 AND 127)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_mold_cavities');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
