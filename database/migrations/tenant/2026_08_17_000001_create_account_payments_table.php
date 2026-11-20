<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - date_of_payment: date
 * - date_of_payment_real: date
 * - reference_id: bigint
 * - payment_method_type_id: bigint
 * - has_card: boolean
 * - card_brand_id: bigint
 * - reference: varchar(255)
 * - payment: numeric(30,12)
 * - state: boolean
 * - reference_payment: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date_of_payment');
            $table->date('date_of_payment_real')->nullable();
            $table->bigInteger('reference_id')->nullable();
            $table->bigInteger('payment_method_type_id')->nullable();
            $table->boolean('has_card')->default(false);
            $table->bigInteger('card_brand_id')->nullable();
            $table->string('reference', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('payment', 30, 12);
            $table->boolean('state')->default(false);
            $table->string('reference_payment', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "account_payments" ADD CONSTRAINT "account_payments_reference_id_range" CHECK ("reference_id" >= 0)');
        DB::statement('ALTER TABLE "account_payments" ADD CONSTRAINT "account_payments_payment_method_type_id_range" CHECK ("payment_method_type_id" >= 0)');
        DB::statement('ALTER TABLE "account_payments" ADD CONSTRAINT "account_payments_card_brand_id_range" CHECK ("card_brand_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
