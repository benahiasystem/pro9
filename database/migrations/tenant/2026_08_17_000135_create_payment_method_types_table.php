<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: char(2)
 * - description: varchar(255)
 * - hka_code: char(2)
 * - collection_kind: varchar(16)
 * - currency_type_id: varchar(255)
 * - iva_rate_type_id: varchar(4)
 * - cash_currency_type_id: char(3)
 * - bank_account_id: bigint
 * - commission_percentage: numeric(30,12)
 * - minimum_commission: numeric(30,12)
 * - number_days: integer
 * - is_credit: boolean
 * - is_cash: boolean
 * - is_active: boolean
 * - available_for_change: boolean
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_types', function (Blueprint $table) {
            $table->char('id', 2)->collation('pro9_ci_ai');
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->char('hka_code', 2)->collation('pro9_ci_ai')->nullable();
            $table->string('collection_kind', 16)->collation('pro9_ci_ai')->default('unclassified');
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai')->default('VES');
            $table->string('iva_rate_type_id', 4)->collation('pro9_ci_ai')->nullable();
            $table->char('cash_currency_type_id', 3)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('bank_account_id')->nullable();
            $table->decimal('commission_percentage', 30, 12)->nullable();
            $table->decimal('minimum_commission', 30, 12)->nullable();
            $table->integer('number_days')->nullable();
            $table->boolean('is_credit')->default(false)->comment('Define si es tipo credito');
            $table->boolean('is_cash')->default(false)->comment('Define si es es efectivo');
            $table->boolean('is_active')->default(true);
            $table->boolean('available_for_change')->default(false);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['id'], 'payment_method_types_id_index');
            $table->index(['bank_account_id'], 'payment_method_bank_account_fk');
            $table->index(['hka_code'], 'payment_method_types_hka_code_index');
            $table->index(['is_credit'], 'payment_method_types_is_credit_index');
            $table->unique(['id'], 'payment_method_types_id_pg_unique');
        });
        DB::statement('ALTER TABLE "payment_method_types" ADD CONSTRAINT "payment_method_types_number_days_range" CHECK ("number_days" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
