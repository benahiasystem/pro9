<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - customer_id: bigint
 * - customer: text
 * - notes: varchar(250)
 * - towels: integer
 * - hotel_room_id: bigint
 * - hotel_rate_id: bigint
 * - duration: integer
 * - quantity_persons: integer
 * - data_persons: json
 * - input_date: date
 * - input_time: varchar(8)
 * - payment_status: varchar(10)
 * - output_date: date
 * - output_time: varchar(8)
 * - arrears: numeric(30,12)
 * - calculation_snapshot: json
 * - status: varchar(10)
 * - establishment_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_rents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('customer_id');
            $table->text('customer')->collation('pro9_ci_ai');
            $table->string('notes', 250)->collation('pro9_ci_ai')->nullable();
            $table->integer('towels')->default('1');
            $table->bigInteger('hotel_room_id');
            $table->bigInteger('hotel_rate_id')->nullable();
            $table->integer('duration')->default('1');
            $table->integer('quantity_persons')->default('1');
            $table->json('data_persons')->nullable();
            $table->date('input_date')->nullable();
            $table->string('input_time', 8)->collation('pro9_ci_ai')->nullable();
            $table->string('payment_status', 10)->collation('pro9_ci_ai');
            $table->date('output_date');
            $table->string('output_time', 8)->collation('pro9_ci_ai');
            $table->decimal('arrears', 30, 12)->default('0.000000000000');
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->string('status', 10)->collation('pro9_ci_ai')->default('INICIADO');
            $table->bigInteger('establishment_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['hotel_rate_id'], 'hotel_rents_hotel_rate_id_foreign');
            $table->index(['establishment_id'], 'hotel_rents_establishment_id_foreign');
        });
        DB::statement('ALTER TABLE "hotel_rents" ADD CONSTRAINT "hotel_rents_customer_id_range" CHECK ("customer_id" >= 0)');
        DB::statement('ALTER TABLE "hotel_rents" ADD CONSTRAINT "hotel_rents_towels_range" CHECK ("towels" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "hotel_rents" ADD CONSTRAINT "hotel_rents_hotel_room_id_range" CHECK ("hotel_room_id" >= 0)');
        DB::statement('ALTER TABLE "hotel_rents" ADD CONSTRAINT "hotel_rents_duration_range" CHECK ("duration" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "hotel_rents" ADD CONSTRAINT "hotel_rents_quantity_persons_range" CHECK ("quantity_persons" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_rents');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
