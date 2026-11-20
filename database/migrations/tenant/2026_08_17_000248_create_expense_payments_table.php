<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - expense_id: bigint
 * - date_of_payment: date
 * - expense_method_type_id: bigint
 * - reference: varchar(255)
 * - payment: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('expense_id');
            $table->date('date_of_payment');
            $table->bigInteger('expense_method_type_id');
            $table->string('reference', 255)->collation('pro9_ci_ai')->nullable();
            $table->decimal('payment', 30, 12);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['expense_id'], 'expense_payments_expense_id_foreign');
            $table->index(['expense_method_type_id'], 'expense_payments_expense_method_type_id_foreign');
            $table->index(['date_of_payment'], 'expense_payments_date_of_payment_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
