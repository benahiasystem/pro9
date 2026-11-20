<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - purchase_settlement_id: bigint
 * - date_of_payment: date
 * - payment_method_type_id: char(2)
 * - reference: varchar(255)
 * - change: numeric(30,12)
 * - payment: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_settlement_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('purchase_settlement_id');
            $table->date('date_of_payment');
            $table->char('payment_method_type_id', 2)->collation('sab_ci_ai');
            $table->string('reference', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('change', 30, 12)->default('0.00');
            $table->decimal('payment', 30, 12);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['purchase_settlement_id'], 'purchase_settlement_payments_purchase_settlement_id_foreign');
            $table->index(['payment_method_type_id'], 'purchase_settlement_payments_payment_method_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_settlement_payments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
