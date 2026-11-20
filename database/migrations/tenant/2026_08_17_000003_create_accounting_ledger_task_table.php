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
 * - last_rum: timestamp
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_ledger_task', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('month')->nullable()->default('0')->comment('Numero de mes');
            $table->bigInteger('year')->nullable()->default('0')->comment('Numero de mes');
            $table->timestamp('last_rum')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "accounting_ledger_task" ADD CONSTRAINT "accounting_ledger_task_month_range" CHECK ("month" BETWEEN 0 AND 65535)');
        DB::statement('ALTER TABLE "accounting_ledger_task" ADD CONSTRAINT "accounting_ledger_task_year_range" CHECK ("year" BETWEEN 0 AND 16777215)');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_ledger_task');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
