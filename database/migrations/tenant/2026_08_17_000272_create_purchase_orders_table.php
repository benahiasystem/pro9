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
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(2)
 * - prefix: varchar(255)
 * - date_of_issue: date
 * - date_of_due: date
 * - time_of_issue: time
 * - supplier_id: bigint
 * - supplier: json
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
 * - filename: varchar(255)
 * - upload_filename: varchar(255)
 * - purchase_quotation_id: bigint
 * - sale_opportunity_id: bigint
 * - payment_method_type_id: char(2)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - document_type_id: char(2)
 * - series: char(4)
 * - number: integer
 * - observation: text
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('pro9_ci_ai');
            $table->bigInteger('establishment_id');
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai');
            $table->char('state_type_id', 2)->collation('pro9_ci_ai');
            $table->string('prefix', 255)->collation('pro9_ci_ai')->default('OC');
            $table->date('date_of_issue');
            $table->date('date_of_due')->nullable();
            $table->time('time_of_issue');
            $table->bigInteger('supplier_id');
            $table->json('supplier');
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
            $table->string('filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('upload_filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('purchase_quotation_id')->nullable();
            $table->bigInteger('sale_opportunity_id')->nullable();
            $table->char('payment_method_type_id', 2)->collation('pro9_ci_ai');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->char('document_type_id', 2)->collation('pro9_ci_ai')->default('');
            $table->char('series', 4)->collation('pro9_ci_ai')->default('');
            $table->integer('number')->default('0');
            $table->text('observation')->collation('pro9_ci_ai')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['purchase_quotation_id'], 'purchase_orders_purchase_quotation_id_foreign');
            $table->index(['user_id'], 'purchase_orders_user_id_foreign');
            $table->index(['establishment_id'], 'purchase_orders_establishment_id_foreign');
            $table->index(['supplier_id'], 'purchase_orders_supplier_id_foreign');
            $table->index(['fiscal_environment'], 'purchase_orders_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'purchase_orders_state_type_id_foreign');
            $table->index(['currency_type_id'], 'purchase_orders_currency_type_id_foreign');
            $table->index(['payment_method_type_id'], 'purchase_orders_payment_method_type_id_foreign');
            $table->index(['sale_opportunity_id'], 'purchase_orders_sale_opportunity_id_foreign');
        });
        DB::statement('ALTER TABLE "purchase_orders" ADD CONSTRAINT "purchase_orders_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
