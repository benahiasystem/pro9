<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - bank_loan_type_id: bigint
 * - establishment_id: bigint
 * - bank_id: bigint
 * - bank_account_id: bigint
 * - currency_type_id: varchar(255)
 * - external_id: char(36)
 * - number: integer
 * - date_of_issue: date
 * - time_of_issue: time
 * - bank: json
 * - exchange_rate_sale: numeric(18,8)
 * - total: numeric(30,12)
 * - total_interest: numeric(30,12)
 * - total_ingress: numeric(30,12)
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_loans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('bank_loan_type_id');
            $table->bigInteger('establishment_id');
            $table->bigInteger('bank_id');
            $table->bigInteger('bank_account_id');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->char('external_id', 36)->collation('sab_ci_ai');
            $table->integer('number');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->json('bank');
            $table->decimal('exchange_rate_sale', 18, 8)->nullable()->default('0.00');
            $table->decimal('total', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_interest', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_ingress', 30, 12)->nullable()->default('0.00');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai')->nullable();
            $table->char('state_type_id', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "bank_loans" ADD CONSTRAINT "bank_loans_user_id_range" CHECK ("user_id" >= 0)');
        DB::statement('ALTER TABLE "bank_loans" ADD CONSTRAINT "bank_loans_bank_loan_type_id_range" CHECK ("bank_loan_type_id" >= 0)');
        DB::statement('ALTER TABLE "bank_loans" ADD CONSTRAINT "bank_loans_establishment_id_range" CHECK ("establishment_id" >= 0)');
        DB::statement('ALTER TABLE "bank_loans" ADD CONSTRAINT "bank_loans_bank_id_range" CHECK ("bank_id" >= 0)');
        DB::statement('ALTER TABLE "bank_loans" ADD CONSTRAINT "bank_loans_bank_account_id_range" CHECK ("bank_account_id" >= 0)');
        DB::statement('ALTER TABLE "bank_loans" ADD CONSTRAINT "bank_loans_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_loans');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
