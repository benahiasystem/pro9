<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - cash_id: bigint
 * - currency_type_id: varchar(255)
 * - beginning_balance: numeric(30,12)
 * - income: numeric(30,12)
 * - egress: numeric(30,12)
 * - final_balance: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_currency_balances', function (Blueprint $table) {
            $table->bigInteger('cash_id');
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('beginning_balance', 30, 12)->default('0');
            $table->decimal('income', 30, 12)->default('0');
            $table->decimal('egress', 30, 12)->default('0');
            $table->decimal('final_balance', 30, 12)->default('0');
            $table->json('calculation_snapshot');
            $table->primary(['cash_id', 'currency_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_currency_balances');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
