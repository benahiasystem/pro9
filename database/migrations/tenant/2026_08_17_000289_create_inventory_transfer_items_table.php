<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - inventory_transfer_id: bigint
 * - item_lots_group_id: bigint
 * - item_lot_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('inventory_transfer_id');
            $table->bigInteger('item_lots_group_id')->nullable();
            $table->bigInteger('item_lot_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['inventory_transfer_id'], 'inventory_transfer_items_inventory_transfer_id_foreign');
            $table->index(['item_lots_group_id'], 'inventory_transfer_items_item_lots_group_id_foreign');
            $table->index(['item_lot_id'], 'inventory_transfer_items_item_lot_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
