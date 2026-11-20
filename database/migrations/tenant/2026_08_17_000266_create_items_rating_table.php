<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - item_id: bigint
 * - value: smallint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_rating', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('item_id');
            $table->smallInteger('value');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'items_rating_user_id_foreign');
            $table->index(['item_id'], 'items_rating_item_id_foreign');
        });
        DB::statement('ALTER TABLE "items_rating" ADD CONSTRAINT "items_rating_value_range" CHECK ("value" BETWEEN -128 AND 127)');
    }

    public function down(): void
    {
        Schema::dropIfExists('items_rating');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
