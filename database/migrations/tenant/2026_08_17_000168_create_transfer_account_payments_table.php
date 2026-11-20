<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - origin_id: bigint
 * - origin_type: text
 * - destiny_id: bigint
 * - destiny_type: text
 * - amount: numeric(30,12)
 * - currency_type_id: varchar(255)
 * - date_of_movement: timestamp
 * - user_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_account_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('origin_id')->nullable()->default('0')->comment('id Cuenta de origen');
            $table->text('origin_type')->collation('sab_ci_ai')->nullable()->comment('Modelo de origen');
            $table->bigInteger('destiny_id')->nullable()->default('0')->comment('id Cuenta de destino');
            $table->text('destiny_type')->collation('sab_ci_ai')->nullable()->comment('Modelo de destino');
            $table->decimal('amount', 30, 12)->nullable()->default('0.00')->comment('Monto a transferir');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai')->nullable()->comment('Moneda del destino del traslado');
            $table->timestamp('date_of_movement')->nullable()->comment('Fecha de movimiento');
            $table->bigInteger('user_id')->nullable()->default('0')->comment('Usuario que realiza el movimiento');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "transfer_account_payments" ADD CONSTRAINT "transfer_account_payments_origin_id_range" CHECK ("origin_id" >= 0)');
        DB::statement('ALTER TABLE "transfer_account_payments" ADD CONSTRAINT "transfer_account_payments_destiny_id_range" CHECK ("destiny_id" >= 0)');
        DB::statement('ALTER TABLE "transfer_account_payments" ADD CONSTRAINT "transfer_account_payments_user_id_range" CHECK ("user_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_account_payments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
