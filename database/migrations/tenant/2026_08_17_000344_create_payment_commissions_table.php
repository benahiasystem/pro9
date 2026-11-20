<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - source_global_payment_id: bigint
 * - currency_type_id: varchar(255)
 * - date_of_payment: date
 * - amount: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_commissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('source_global_payment_id');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->date('date_of_payment');
            $table->decimal('amount', 30, 12);
            $table->json('calculation_snapshot')->nullable();
            $table->unique(['source_global_payment_id'], 'payment_commissions_source_unique');
            $table->index(['currency_type_id'], 'payment_commissions_currency_fk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_commissions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
