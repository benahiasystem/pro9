<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - payment_link_id: bigint
 * - record_id: bigint
 * - record_type: varchar(255)
 * - payment_id: bigint
 * - payment_type: varchar(255)
 * - total: numeric(30,12)
 * - status: varchar(20)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_link_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('payment_link_id');
            $table->bigInteger('record_id')->nullable();
            $table->string('record_type', 255)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('payment_id')->nullable();
            $table->string('payment_type', 255)->collation('pro9_ci_ai')->nullable();
            $table->decimal('total', 30, 12)->nullable();
            $table->string('status', 20)->collation('pro9_ci_ai')->default('pending');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['record_id', 'record_type'], 'payment_link_payments_record_index');
            $table->index(['payment_id', 'payment_type'], 'payment_link_payments_payment_index');
            $table->index(['payment_link_id', 'status'], 'payment_link_payments_status_index');
        });
        DB::statement('ALTER TABLE "payment_link_payments" ADD CONSTRAINT "payment_link_payments_record_id_range" CHECK ("record_id" >= 0)');
        DB::statement('ALTER TABLE "payment_link_payments" ADD CONSTRAINT "payment_link_payments_payment_id_range" CHECK ("payment_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_link_payments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
