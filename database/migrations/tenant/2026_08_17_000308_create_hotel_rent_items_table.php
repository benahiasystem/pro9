<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - type: varchar(3)
 * - hotel_rent_id: bigint
 * - item_id: bigint
 * - item: text
 * - payment_status: varchar(255)
 * - hotel_rent_order_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_rent_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type', 3)->collation('sab_ci_ai');
            $table->bigInteger('hotel_rent_id');
            $table->bigInteger('item_id');
            $table->text('item')->collation('sab_ci_ai')->nullable();
            $table->string('payment_status', 255)->collation('sab_ci_ai');
            $table->bigInteger('hotel_rent_order_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['hotel_rent_id'], 'hotel_rent_items_hotel_rent_id_foreign');
            $table->index(['item_id'], 'hotel_rent_items_item_id_foreign');
            $table->index(['hotel_rent_order_id'], 'hotel_rent_items_hotel_rent_order_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_rent_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
