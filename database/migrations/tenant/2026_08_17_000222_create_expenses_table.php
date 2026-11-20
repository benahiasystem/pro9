<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - fiscal_environment: varchar(16)
 * - expense_type_id: bigint
 * - establishment_id: bigint
 * - supplier_id: bigint
 * - expense_reason_id: bigint
 * - currency_type_id: varchar(255)
 * - external_id: char(36)
 * - state_type_id: char(2)
 * - number: varchar(255)
 * - date_of_issue: date
 * - time_of_issue: time
 * - supplier: json
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
        Schema::create('expenses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('expense_type_id');
            $table->bigInteger('establishment_id');
            $table->bigInteger('supplier_id');
            $table->bigInteger('expense_reason_id')->default('1');
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->char('external_id', 36)->collation('pro9_ci_ai');
            $table->char('state_type_id', 2)->collation('pro9_ci_ai')->nullable();
            $table->string('number', 255)->collation('pro9_ci_ai')->nullable();
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->json('supplier');
            $table->decimal('exchange_rate_sale', 18, 8);
            $table->decimal('total', 30, 12);
            $table->string('filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['filename'], 'expenses_filename_unique');
            $table->index(['user_id'], 'expenses_user_id_foreign');
            $table->index(['establishment_id'], 'expenses_establishment_id_foreign');
            $table->index(['supplier_id'], 'expenses_supplier_id_foreign');
            $table->index(['expense_type_id'], 'expenses_expense_type_id_foreign');
            $table->index(['currency_type_id'], 'expenses_currency_type_id_foreign');
            $table->index(['expense_reason_id'], 'expenses_expense_reason_id_foreign');
            $table->index(['fiscal_environment'], 'expenses_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'expenses_state_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
