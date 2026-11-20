<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - code_account: varchar(255)
 * - name: text
 * - disabled: smallint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_accounting_ledger_code_account', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code_account', 255)->collation('sab_ci_ai')->comment('Codigo de plan de cuenta');
            $table->text('name')->collation('sab_ci_ai')->comment('Nombre de cuenta');
            $table->smallInteger('disabled')->nullable()->default('0')->comment('Permite realizar modificaciones');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "cat_accounting_ledger_code_account" ADD CONSTRAINT "cat_accounting_ledger_code_account_disabled_range" CHECK ("disabled" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_accounting_ledger_code_account');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
