<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - modifier_group_id: bigint
 * - default_open: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_modifier_group', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id');
            $table->bigInteger('modifier_group_id');
            $table->boolean('default_open')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['item_id'], 'item_modifier_group_item_id_foreign');
            $table->index(['modifier_group_id'], 'item_modifier_group_modifier_group_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_modifier_group');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
