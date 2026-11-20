<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - hotel_category_id: bigint
 * - hotel_floor_id: bigint
 * - name: varchar(25)
 * - description: varchar(250)
 * - status: varchar(255)
 * - active: boolean
 * - establishment_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_rooms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id')->nullable();
            $table->bigInteger('hotel_category_id');
            $table->bigInteger('hotel_floor_id');
            $table->string('name', 25)->collation('sab_ci_ai');
            $table->string('description', 250)->collation('sab_ci_ai')->nullable();
            $table->string('status', 255)->collation('sab_ci_ai')->default('DISPONIBLE');
            $table->boolean('active')->default(true);
            $table->bigInteger('establishment_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['hotel_category_id'], 'hotel_rooms_hotel_category_id_foreign');
            $table->index(['hotel_floor_id'], 'hotel_rooms_hotel_floor_id_foreign');
            $table->index(['item_id'], 'hotel_rooms_item_id_foreign');
            $table->index(['establishment_id'], 'hotel_rooms_establishment_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_rooms');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
