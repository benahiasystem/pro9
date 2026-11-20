<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - external_id: char(36)
 * - establishment_id: bigint
 * - establishment: json
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(2)
 * - prefix: varchar(255)
 * - date_of_issue: date
 * - time_of_issue: time
 * - customer_id: bigint
 * - customer: json
 * - currency_type_id: varchar(255)
 * - exchange_rate_sale: numeric(18,8)
 * - total_exportation: numeric(30,12)
 * - total_free: numeric(30,12)
 * - total_taxed: numeric(30,12)
 * - total_unaffected: numeric(30,12)
 * - total_exonerated: numeric(30,12)
 * - total_igv: numeric(30,12)
 * - total_taxes: numeric(30,12)
 * - total_value: numeric(30,12)
 * - total: numeric(30,12)
 * - filename: varchar(255)
 * - detail: text
 * - observation: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_opportunities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('pro9_ci_ai');
            $table->bigInteger('establishment_id');
            $table->json('establishment');
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai');
            $table->char('state_type_id', 2)->collation('pro9_ci_ai');
            $table->string('prefix', 255)->collation('pro9_ci_ai');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->bigInteger('customer_id');
            $table->json('customer');
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('exchange_rate_sale', 18, 8);
            $table->decimal('total_exportation', 30, 12)->default('0.00');
            $table->decimal('total_free', 30, 12)->default('0.00');
            $table->decimal('total_taxed', 30, 12)->default('0.00');
            $table->decimal('total_unaffected', 30, 12)->default('0.00');
            $table->decimal('total_exonerated', 30, 12)->default('0.00');
            $table->decimal('total_igv', 30, 12)->default('0.00');
            $table->decimal('total_taxes', 30, 12)->default('0.00');
            $table->decimal('total_value', 30, 12)->default('0.00');
            $table->decimal('total', 30, 12);
            $table->string('filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->text('detail')->collation('pro9_ci_ai')->nullable();
            $table->text('observation')->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['user_id'], 'sale_opportunities_user_id_foreign');
            $table->index(['establishment_id'], 'sale_opportunities_establishment_id_foreign');
            $table->index(['customer_id'], 'sale_opportunities_customer_id_foreign');
            $table->index(['fiscal_environment'], 'sale_opportunities_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'sale_opportunities_state_type_id_foreign');
            $table->index(['currency_type_id'], 'sale_opportunities_currency_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_opportunities');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
