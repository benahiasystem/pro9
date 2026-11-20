<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - income_id: bigint
 * - date_of_payment: date
 * - payment_method_type_id: char(2)
 * - reference: varchar(255)
 * - change: numeric(30,12)
 * - payment: numeric(30,12)
 * - currency_type_id: varchar(255)
 * - original_amount: numeric(30,12)
 * - exchange_rate: numeric(18,8)
 * - exchange_rate_source: varchar(255)
 * - exchange_rate_date: date
 * - payment_method_snapshot: json
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('income_id');
            $table->date('date_of_payment');
            $table->char('payment_method_type_id', 2)->collation('sab_ci_ai');
            $table->string('reference', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('change', 30, 12)->nullable();
            $table->decimal('payment', 30, 12);
            $table->string('currency_type_id', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('original_amount', 30, 12)->nullable();
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->string('exchange_rate_source', 255)->collation('sab_ci_ai')->nullable();
            $table->date('exchange_rate_date')->nullable();
            $table->json('payment_method_snapshot')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['income_id'], 'income_payments_income_id_foreign');
            $table->index(['payment_method_type_id'], 'income_payments_payment_method_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_payments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
