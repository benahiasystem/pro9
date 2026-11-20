<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - month: integer
 * - year: bigint
 * - date_of_report: date
 * - code_account: text
 * - name: text
 * - last_month_total: numeric(30,12)
 * - credits: numeric(30,12)
 * - debs: numeric(30,12)
 * - final_total: numeric(30,12)
 * - serialize_data: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_ledger', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('month')->nullable()->default('0')->comment('Numero de mes');
            $table->bigInteger('year')->nullable()->default('0')->comment('Numero de mes');
            $table->date('date_of_report')->nullable();
            $table->text('code_account')->collation('sab_ci_ai')->comment('Codigo de plan de cuenta');
            $table->text('name')->collation('sab_ci_ai')->comment('Nombre de cuenta');
            $table->decimal('last_month_total', 30, 12)->default('0.00')->comment('Debe ser el valor del total del mes pasado');
            $table->decimal('credits', 30, 12)->default('0.00')->comment('Créditos en el mes');
            $table->decimal('debs', 30, 12)->default('0.00')->comment('Debitos en el mes');
            $table->decimal('final_total', 30, 12)->default('0.00')->comment('Saldo Final de mes');
            $table->text('serialize_data')->collation('sab_ci_ai')->comment('datos serialziados en bruto.');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "accounting_ledger" ADD CONSTRAINT "accounting_ledger_month_range" CHECK ("month" BETWEEN 0 AND 65535)');
        DB::statement('ALTER TABLE "accounting_ledger" ADD CONSTRAINT "accounting_ledger_year_range" CHECK ("year" BETWEEN 0 AND 16777215)');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_ledger');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
