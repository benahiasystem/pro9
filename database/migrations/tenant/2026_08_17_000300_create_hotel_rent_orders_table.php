<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - hotel_rent_id: bigint
 * - order_number: integer
 * - order_status: varchar(255)
 * - sale_note_id: bigint
 * - establishment_id: bigint
 * - operation_key: char(36)
 * - operation_hash: char(64)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_rent_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('hotel_rent_id');
            $table->integer('order_number');
            $table->string('order_status', 255)->collation('pro9_ci_ai');
            $table->bigInteger('sale_note_id')->nullable();
            $table->bigInteger('establishment_id')->nullable();
            $table->char('operation_key', 36)->collation('pro9_ci_ai')->nullable();
            $table->char('operation_hash', 64)->collation('C')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['operation_key'], 'hotel_rent_orders_operation_key_unique');
            $table->index(['hotel_rent_id'], 'hotel_rent_orders_hotel_rent_id_foreign');
            $table->index(['sale_note_id'], 'hotel_rent_orders_sale_note_id_foreign');
            $table->index(['establishment_id'], 'hotel_rent_orders_establishment_id_foreign');
        });
        DB::statement('ALTER TABLE "hotel_rent_orders" ADD CONSTRAINT "hotel_rent_orders_order_number_range" CHECK ("order_number" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_rent_orders');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
