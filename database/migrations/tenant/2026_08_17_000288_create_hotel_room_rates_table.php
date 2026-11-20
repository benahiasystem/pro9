<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - hotel_room_id: bigint
 * - hotel_rate_id: bigint
 * - price: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_room_rates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('hotel_room_id');
            $table->bigInteger('hotel_rate_id');
            $table->decimal('price', 30, 12)->default('0');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['hotel_room_id'], 'hotel_room_rates_hotel_room_id_foreign');
            $table->index(['hotel_rate_id'], 'hotel_room_rates_hotel_rate_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_room_rates');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
