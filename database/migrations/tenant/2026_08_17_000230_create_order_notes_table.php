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
 * - prefix: char(3)
 * - date_of_issue: date
 * - time_of_issue: time
 * - date_of_due: date
 * - delivery_date: date
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
 * - total: numeric(30,12)
 * - charges: json
 * - discounts: json
 * - prepayments: json
 * - guides: json
 * - related: json
 * - perception: json
 * - legends: json
 * - additional_data: json
 * - filename: varchar(255)
 * - observation: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - quotation_id: bigint
 * - custom_fields_data: json
 * - document_type_id: char(2)
 * - series: char(4)
 * - number: integer
 * - order_zone_id: bigint
 * - order_person_id: bigint
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('pro9_ci_ai');
            $table->bigInteger('establishment_id');
            $table->json('establishment');
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai');
            $table->char('state_type_id', 2)->collation('pro9_ci_ai');
            $table->char('prefix', 3)->collation('pro9_ci_ai');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->date('date_of_due')->nullable();
            $table->date('delivery_date')->nullable();
            $table->bigInteger('customer_id');
            $table->json('customer');
            $table->text('shipping_address')->collation('pro9_ci_ai')->nullable();
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->char('payment_method_type_id', 2)->collation('pro9_ci_ai')->nullable();
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
            $table->decimal('total', 30, 12);
            $table->json('charges')->nullable();
            $table->json('discounts')->nullable();
            $table->json('prepayments')->nullable();
            $table->json('guides')->nullable();
            $table->json('related')->nullable();
            $table->json('perception')->nullable();
            $table->json('legends')->nullable();
            $table->json('additional_data')->nullable();
            $table->string('filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->text('observation')->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->bigInteger('quotation_id')->nullable()->default('0')->comment('Relacion con cotizaciones');
            $table->json('custom_fields_data')->nullable();
            $table->char('document_type_id', 2)->collation('pro9_ci_ai')->default('');
            $table->char('series', 4)->collation('pro9_ci_ai')->default('');
            $table->integer('number')->default('0');
            $table->bigInteger('order_zone_id')->nullable();
            $table->bigInteger('order_person_id')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['user_id'], 'order_notes_user_id_foreign');
            $table->index(['establishment_id'], 'order_notes_establishment_id_foreign');
            $table->index(['customer_id'], 'order_notes_customer_id_foreign');
            $table->index(['fiscal_environment'], 'order_notes_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'order_notes_state_type_id_foreign');
            $table->index(['currency_type_id'], 'order_notes_currency_type_id_foreign');
            $table->index(['payment_method_type_id'], 'order_notes_payment_method_type_id_foreign');
        });
        DB::statement('ALTER TABLE "order_notes" ADD CONSTRAINT "order_notes_quotation_id_range" CHECK ("quotation_id" >= 0)');
        DB::statement('ALTER TABLE "order_notes" ADD CONSTRAINT "order_notes_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notes');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
