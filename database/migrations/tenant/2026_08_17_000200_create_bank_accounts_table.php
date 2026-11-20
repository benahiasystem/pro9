<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - bank_id: bigint
 * - description: varchar(255)
 * - number: varchar(255)
 * - currency_type_id: varchar(255)
 * - status: smallint
 * - initial_balance: numeric(30,12)
 * - show_in_documents: boolean
 * - establishment_id: bigint
 * - accounting_number: varchar(255)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('bank_id');
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->string('number', 255)->collation('pro9_ci_ai');
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->smallInteger('status')->default('1');
            $table->decimal('initial_balance', 30, 12)->default('0.00');
            $table->boolean('show_in_documents')->default(true);
            $table->bigInteger('establishment_id')->nullable();
            $table->string('accounting_number', 255)->collation('pro9_ci_ai')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['bank_id'], 'bank_accounts_bank_id_foreign');
            $table->index(['currency_type_id'], 'bank_accounts_currency_type_id_foreign');
            $table->index(['establishment_id'], 'bank_accounts_establishment_id_foreign');
        });
        DB::statement('ALTER TABLE "bank_accounts" ADD CONSTRAINT "bank_accounts_status_range" CHECK ("status" BETWEEN -128 AND 127)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
