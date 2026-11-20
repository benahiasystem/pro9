<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - subtotal_pen: varchar(255)
 * - total_pen: varchar(255)
 * - igv_pen: varchar(255)
 * - subtotal_usd: varchar(255)
 * - total_usd: varchar(255)
 * - igv_usd: varchar(255)
 * - exonerated: integer
 * - unaffected: integer
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('subtotal_pen', 255)->collation('pro9_ci_ai');
            $table->string('total_pen', 255)->collation('pro9_ci_ai');
            $table->string('igv_pen', 255)->collation('pro9_ci_ai');
            $table->string('subtotal_usd', 255)->collation('pro9_ci_ai');
            $table->string('total_usd', 255)->collation('pro9_ci_ai');
            $table->string('igv_usd', 255)->collation('pro9_ci_ai');
            $table->integer('exonerated')->nullable();
            $table->integer('unaffected')->nullable();
        });
        DB::statement('ALTER TABLE "company_accounts" ADD CONSTRAINT "company_accounts_exonerated_range" CHECK ("exonerated" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "company_accounts" ADD CONSTRAINT "company_accounts_unaffected_range" CHECK ("unaffected" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_accounts');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
