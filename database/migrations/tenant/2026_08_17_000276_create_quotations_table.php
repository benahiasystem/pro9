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
 * - prefix: char(3)
 * - date_of_issue: date
 * - time_of_issue: time
 * - date_of_due: varchar(255)
 * - delivery_date: varchar(255)
 * - customer_id: bigint
 * - customer: json
 * - shipping_address: text
 * - currency_type_id: varchar(255)
 * - payment_method_type_id: char(2)
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
 * - total_igv_free: numeric(30,12)
 * - total_base_other_taxes: numeric(30,12)
 * - total_other_taxes: numeric(30,12)
 * - total_taxes: numeric(30,12)
 * - total_value: numeric(30,12)
 * - subtotal: numeric(30,12)
 * - custom_fields_data: json
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
 * - referential_information: varchar(100)
 * - source: varchar(20)
 * - account_number: varchar(255)
 * - phone: varchar(255)
 * - contact: varchar(255)
 * - changed: boolean
 * - sale_opportunity_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 * - description: varchar(1000)
 * - document_type_id: char(2)
 * - series: char(4)
 * - number: integer
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('seller_id')->nullable();
            $table->char('external_id', 36)->collation('sab_ci_ai');
            $table->bigInteger('establishment_id');
            $table->json('establishment');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->char('state_type_id', 2)->collation('sab_ci_ai');
            $table->char('prefix', 3)->collation('sab_ci_ai');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->string('date_of_due', 255)->collation('sab_ci_ai')->nullable();
            $table->string('delivery_date', 255)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('customer_id');
            $table->json('customer');
            $table->text('shipping_address')->collation('sab_ci_ai')->nullable();
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->char('payment_method_type_id', 2)->collation('sab_ci_ai')->nullable();
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
            $table->decimal('total_igv_free', 30, 12)->default('0.00');
            $table->decimal('total_base_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_taxes', 30, 12)->default('0.00');
            $table->decimal('total_value', 30, 12)->default('0.00');
            $table->decimal('subtotal', 30, 12)->default('0.00');
            $table->json('custom_fields_data')->nullable();
            $table->decimal('total', 30, 12);
            $table->json('charges')->nullable();
            $table->json('discounts')->nullable();
            $table->json('prepayments')->nullable();
            $table->json('guides')->nullable();
            $table->json('related')->nullable();
            $table->json('perception')->nullable();
            $table->json('legends')->nullable();
            $table->string('filename', 255)->collation('sab_ci_ai')->nullable();
            $table->text('terms_condition')->collation('sab_ci_ai')->nullable();
            $table->string('referential_information', 100)->collation('sab_ci_ai')->nullable();
            $table->string('source', 20)->collation('sab_ci_ai')->default('admin');
            $table->string('account_number', 255)->collation('sab_ci_ai')->nullable();
            $table->string('phone', 255)->collation('sab_ci_ai')->nullable();
            $table->string('contact', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('changed')->default(false);
            $table->bigInteger('sale_opportunity_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('description', 1000)->collation('sab_ci_ai')->nullable();
            $table->char('document_type_id', 2)->collation('sab_ci_ai')->default('');
            $table->char('series', 4)->collation('sab_ci_ai')->default('');
            $table->integer('number')->default('0');
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['user_id'], 'quotations_user_id_foreign');
            $table->index(['establishment_id'], 'quotations_establishment_id_foreign');
            $table->index(['customer_id'], 'quotations_customer_id_foreign');
            $table->index(['fiscal_environment'], 'quotations_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'quotations_state_type_id_foreign');
            $table->index(['currency_type_id'], 'quotations_currency_type_id_foreign');
            $table->index(['payment_method_type_id'], 'quotations_payment_method_type_id_foreign');
            $table->index(['sale_opportunity_id'], 'quotations_sale_opportunity_id_foreign');
            $table->index(['source'], 'quotations_source_index');
        });
        DB::statement('ALTER TABLE "quotations" ADD CONSTRAINT "quotations_seller_id_range" CHECK ("seller_id" >= 0)');
        DB::statement('ALTER TABLE "quotations" ADD CONSTRAINT "quotations_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
        // ######## INICIO MONEDA DOCUMENTAL DE VENTAS ########
        DB::statement("ALTER TABLE quotations ADD CONSTRAINT quotations_currency_ves_check CHECK (currency_type_id IS NOT NULL AND currency_type_id = 'VES')");
        // ######## FIN MONEDA DOCUMENTAL DE VENTAS ########
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
