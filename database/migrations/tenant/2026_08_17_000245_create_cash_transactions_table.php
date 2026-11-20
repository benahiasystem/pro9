<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - cash_id: bigint
 * - payment_method_type_id: char(2)
 * - currency_type_id: char(3)
 * - date: date
 * - description: varchar(255)
 * - payment: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('cash_id');
            $table->char('payment_method_type_id', 2)->collation('pro9_ci_ai');
            $table->char('currency_type_id', 3)->collation('pro9_ci_ai')->nullable();
            $table->date('date');
            $table->string('description', 255)->collation('pro9_ci_ai')->nullable();
            $table->decimal('payment', 30, 12);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['cash_id'], 'cash_transactions_cash_id_foreign');
            $table->index(['payment_method_type_id'], 'cash_transactions_payment_method_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
