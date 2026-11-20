<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - income_type_id: bigint
 * - income_reason_id: bigint
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(2)
 * - establishment_id: bigint
 * - customer: varchar(255)
 * - currency_type_id: varchar(255)
 * - external_id: char(36)
 * - number: integer
 * - date_of_issue: date
 * - time_of_issue: time
 * - exchange_rate_sale: numeric(18,8)
 * - total: numeric(30,12)
 * - filename: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('income_type_id');
            $table->bigInteger('income_reason_id');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->char('state_type_id', 2)->collation('sab_ci_ai');
            $table->bigInteger('establishment_id');
            $table->string('customer', 255)->collation('sab_ci_ai');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->char('external_id', 36)->collation('sab_ci_ai');
            $table->integer('number');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->decimal('exchange_rate_sale', 18, 8);
            $table->decimal('total', 30, 12);
            $table->string('filename', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['filename'], 'income_filename_unique');
            $table->index(['user_id'], 'income_user_id_foreign');
            $table->index(['establishment_id'], 'income_establishment_id_foreign');
            $table->index(['income_type_id'], 'income_income_type_id_foreign');
            $table->index(['income_reason_id'], 'income_income_reason_id_foreign');
            $table->index(['state_type_id'], 'income_state_type_id_foreign');
            $table->index(['fiscal_environment'], 'income_fiscal_environment_foreign');
            $table->index(['currency_type_id'], 'income_currency_type_id_foreign');
            $table->index(['number'], 'income_number_index');
            $table->index(['date_of_issue'], 'income_date_of_issue_index');
            $table->index(['external_id'], 'income_external_id_index');
        });
        DB::statement('ALTER TABLE "income" ADD CONSTRAINT "income_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('income');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
