<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - seller_id: bigint
 * - external_id: char(36)
 * - establishment_id: bigint
 * - establishment: json
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(2)
 * - prefix: varchar(255)
 * - date_of_issue: date
 * - time_of_issue: time
 * - date_of_due: date
 * - delivery_date: date
 * - customer_id: bigint
 * - customer: json
 * - shipping_address: text
 * - payment_method_type_id: char(2)
 * - currency_type_id: varchar(255)
 * - exchange_rate_sale: numeric(18,8)
 * - total_prepayment: numeric(30,12)
 * - total_charge: numeric(30,12)
 * - total_discount: numeric(30,12)
 * - total_exportation: numeric(30,12)
 * - total_free: numeric(30,12)
 * - total_taxed: numeric(30,12)
 * - total_unaffected: numeric(30,12)
 * - total_exonerated: numeric(30,12)
 * - total_igv: numeric(30,12)
 * - total_base_other_taxes: numeric(30,12)
 * - total_other_taxes: numeric(30,12)
 * - total_taxes: numeric(30,12)
 * - total_value: numeric(30,12)
 * - total: numeric(30,12)
 * - charges: json
 * - discounts: json
 * - prepayments: json
 * - guides: json
 * - related: json
 * - perception: json
 * - legends: json
 * - filename: varchar(255)
 * - terms_condition: text
 * - account_number: varchar(255)
 * - description: varchar(255)
 * - changed: varchar(255)
 * - quotation_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('seller_id')->nullable();
            $table->char('external_id', 36)->collation('pro9_ci_ai');
            $table->bigInteger('establishment_id');
            $table->json('establishment');
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai');
            $table->char('state_type_id', 2)->collation('pro9_ci_ai');
            $table->string('prefix', 255)->collation('pro9_ci_ai');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->date('date_of_due')->nullable();
            $table->date('delivery_date')->nullable();
            $table->bigInteger('customer_id');
            $table->json('customer');
            $table->text('shipping_address')->collation('pro9_ci_ai')->nullable();
            $table->char('payment_method_type_id', 2)->collation('pro9_ci_ai')->nullable();
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('exchange_rate_sale', 18, 8);
            $table->decimal('total_prepayment', 30, 12)->default('0.00');
            $table->decimal('total_charge', 30, 12)->default('0.00');
            $table->decimal('total_discount', 30, 12)->default('0.00');
            $table->decimal('total_exportation', 30, 12)->default('0.00');
            $table->decimal('total_free', 30, 12)->default('0.00');
            $table->decimal('total_taxed', 30, 12)->default('0.00');
            $table->decimal('total_unaffected', 30, 12)->default('0.00');
            $table->decimal('total_exonerated', 30, 12)->default('0.00');
            $table->decimal('total_igv', 30, 12)->default('0.00');
            $table->decimal('total_base_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_taxes', 30, 12)->default('0.00');
            $table->decimal('total_value', 30, 12)->default('0.00');
            $table->decimal('total', 30, 12);
            $table->json('charges')->nullable();
            $table->json('discounts')->nullable();
            $table->json('prepayments')->nullable();
            $table->json('guides')->nullable();
            $table->json('related')->nullable();
            $table->json('perception')->nullable();
            $table->json('legends')->nullable();
            $table->string('filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->text('terms_condition')->collation('pro9_ci_ai')->nullable();
            $table->string('account_number', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('description', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('changed', 255)->collation('pro9_ci_ai')->default('0');
            $table->bigInteger('quotation_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['user_id'], 'contracts_user_id_foreign');
            $table->index(['establishment_id'], 'contracts_establishment_id_foreign');
            $table->index(['customer_id'], 'contracts_customer_id_foreign');
            $table->index(['fiscal_environment'], 'contracts_fiscal_environment_foreign');
            $table->index(['currency_type_id'], 'contracts_currency_type_id_foreign');
            $table->index(['payment_method_type_id'], 'contracts_payment_method_type_id_foreign');
            $table->index(['quotation_id'], 'contracts_quotation_id_foreign');
            $table->index(['state_type_id'], 'contracts_state_type_id_foreign');
        });
        DB::statement('ALTER TABLE "contracts" ADD CONSTRAINT "contracts_seller_id_range" CHECK ("seller_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
