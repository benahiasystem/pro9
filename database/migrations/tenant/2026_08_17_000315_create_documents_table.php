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
 * - issuer: json
 * - exchange_rate_source: varchar(255)
 * - exchange_rate_date: date
 * - fiscal_environment: varchar(16)
 * - fiscal_emission_mode: varchar(32)
 * - state_type_id: char(2)
 * - group_id: char(2)
 * - document_type_id: varchar(255)
 * - control_number: varchar(32)
 * - series: varchar(32)
 * - number: numeric(20,0)
 * - date_of_issue: date
 * - time_of_issue: time
 * - customer_id: bigint
 * - customer: json
 * - currency_type_id: varchar(255)
 * - payment_condition_id: varchar(255)
 * - payment_method_type_id: char(2)
 * - purchase_order: varchar(255)
 * - plate_number: varchar(255)
 * - quotation_id: bigint
 * - sale_note_id: bigint
 * - user_rel_suscription_plan_id: bigint
 * - technical_service_id: bigint
 * - order_note_id: bigint
 * - dispatch_id: bigint
 * - seller_id: bigint
 * - exchange_rate_sale: numeric(18,8)
 * - point_system: boolean
 * - point_system_data: json
 * - automatic_date_of_issue: date
 * - type_period: varchar(255)
 * - quantity_period: integer
 * - enabled_concurrency: smallint
 * - apply_concurrency: smallint
 * - total_prepayment: numeric(30,12)
 * - total_charge: numeric(30,12)
 * - total_discount: numeric(30,12)
 * - total_exportation: numeric(30,12)
 * - total_taxed: numeric(30,12)
 * - total_exonerated: numeric(30,12)
 * - total_igv: numeric(30,12)
 * - total_base_other_taxes: numeric(30,12)
 * - total_other_taxes: numeric(30,12)
 * - total_taxes: numeric(30,12)
 * - total_value: numeric(30,12)
 * - subtotal: numeric(30,12)
 * - total: numeric(30,12)
 * - dispatch_ticket_pdf: boolean
 * - has_prepayment: boolean
 * - affectation_type_prepayment: varchar(255)
 * - was_deducted_prepayment: boolean
 * - pending_amount_prepayment: numeric(30,12)
 * - total_pending_payment: numeric(30,12)
 * - charges: json
 * - discounts: json
 * - prepayments: json
 * - guides: json
 * - related: json
 * - legends: json
 * - additional_information: text
 * - additional_data: json
 * - filename: varchar(255)
 * - unique_filename: varchar(255)
 * - reference_data: varchar(500)
 * - agent_id: bigint
 * - has_pdf: boolean
 * - data_json: json
 * - total_canceled: boolean
 * - sale_notes_relateds: json
 * - terms_condition: text
 * - folio: varchar(50)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - is_editable: boolean
 * - grade: text
 * - section: text
 * - hotel_data_persons: json
 * - source_module: varchar(255)
 * - hotel_rent_id: bigint
 * - itinerant: json
 * - consigned_id: bigint
 * - consigned_address: varchar(255)
 * - consigned_ubigeo: varchar(255)
 * - custom_fields_data: json
 * - collect_api_state_id: bigint
 * - total_payable: numeric(30,12)
 * - settlement_adjustment: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('pro9_ci_ai');
            $table->bigInteger('establishment_id');
            $table->json('establishment');
            $table->json('issuer');
            $table->string('exchange_rate_source', 255)->collation('pro9_ci_ai')->nullable();
            $table->date('exchange_rate_date')->nullable();
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai');
            $table->string('fiscal_emission_mode', 32)->collation('pro9_ci_ai');
            $table->char('state_type_id', 2)->collation('pro9_ci_ai');
            $table->char('group_id', 2)->collation('pro9_ci_ai');
            $table->string('document_type_id', 255)->collation('pro9_ci_ai');
            $table->string('control_number', 32)->collation('pro9_ci_ai')->nullable();
            $table->string('series', 32)->collation('pro9_ci_ai');
            $table->decimal('number', 20, 0);
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->bigInteger('customer_id');
            $table->json('customer');
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->string('payment_condition_id', 255)->collation('pro9_ci_ai')->nullable();
            $table->char('payment_method_type_id', 2)->collation('pro9_ci_ai')->nullable();
            $table->string('purchase_order', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('plate_number', 255)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('quotation_id')->nullable();
            $table->bigInteger('sale_note_id')->nullable();
            $table->bigInteger('user_rel_suscription_plan_id')->nullable()->default('0')->comment('Relacion con suscripciones');
            $table->bigInteger('technical_service_id')->nullable();
            $table->bigInteger('order_note_id')->nullable();
            $table->bigInteger('dispatch_id')->nullable();
            $table->bigInteger('seller_id')->nullable();
            $table->decimal('exchange_rate_sale', 18, 8);
            $table->boolean('point_system')->default(false)->comment('indica si el documento se uso en sistema por puntos');
            $table->json('point_system_data')->nullable()->comment('datos de sistema por puntos');
            $table->date('automatic_date_of_issue')->nullable();
            $table->string('type_period', 255)->collation('pro9_ci_ai')->nullable();
            $table->integer('quantity_period')->nullable();
            $table->smallInteger('enabled_concurrency')->nullable()->default('0');
            $table->smallInteger('apply_concurrency')->nullable()->default('0');
            $table->decimal('total_prepayment', 30, 12)->default('0.00');
            $table->decimal('total_charge', 30, 12)->default('0.00');
            $table->decimal('total_discount', 30, 12)->default('0.00');
            $table->decimal('total_exportation', 30, 12)->default('0.00');
            $table->decimal('total_taxed', 30, 12)->default('0.00');
            $table->decimal('total_exonerated', 30, 12)->default('0.00');
            $table->decimal('total_igv', 30, 12)->default('0.00');
            $table->decimal('total_base_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_taxes', 30, 12)->default('0.00');
            $table->decimal('total_value', 30, 12)->default('0.00');
            $table->decimal('subtotal', 30, 12)->default('0.00');
            $table->decimal('total', 30, 12);
            $table->boolean('dispatch_ticket_pdf')->default(false);
            $table->boolean('has_prepayment')->default(false);
            $table->string('affectation_type_prepayment', 255)->collation('pro9_ci_ai')->nullable();
            $table->boolean('was_deducted_prepayment')->default(false);
            $table->decimal('pending_amount_prepayment', 30, 12)->default('0.00');
            $table->decimal('total_pending_payment', 30, 12)->default('0.00');
            $table->json('charges')->nullable();
            $table->json('discounts')->nullable();
            $table->json('prepayments')->nullable();
            $table->json('guides')->nullable();
            $table->json('related')->nullable();
            $table->json('legends')->nullable();
            $table->text('additional_information')->collation('pro9_ci_ai')->nullable();
            $table->json('additional_data')->nullable();
            $table->string('filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('unique_filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('reference_data', 500)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('agent_id')->nullable();
            $table->boolean('has_pdf')->default(false);
            $table->json('data_json')->nullable();
            $table->boolean('total_canceled')->default(false);
            $table->json('sale_notes_relateds')->nullable()->comment('registros asociados cuando se genera cpe desde multiples notas de venta');
            $table->text('terms_condition')->collation('pro9_ci_ai')->nullable();
            $table->string('folio', 50)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('is_editable')->default(false);
            $table->text('grade')->collation('pro9_ci_ai')->nullable()->comment('Grado designado - utilizado en matricula');
            $table->text('section')->collation('pro9_ci_ai')->nullable()->comment('Seccion designado - utilizado en matricula');
            $table->json('hotel_data_persons')->nullable()->comment('datos de personas - utilizado en hotel');
            $table->string('source_module', 255)->collation('pro9_ci_ai')->nullable()->comment('origen del documento - hotel');
            $table->bigInteger('hotel_rent_id')->nullable()->comment('referencia donde se genera el documento - hotel');
            $table->json('itinerant')->nullable();
            $table->bigInteger('consigned_id')->nullable();
            $table->string('consigned_address', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('consigned_ubigeo', 255)->collation('pro9_ci_ai')->nullable();
            $table->json('custom_fields_data')->nullable();
            $table->bigInteger('collect_api_state_id')->default('99')->comment('estado de api en global factoring');
            $table->decimal('total_payable', 30, 12)->nullable();
            $table->decimal('settlement_adjustment', 30, 12)->default('0');
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['establishment_id', 'fiscal_environment', 'document_type_id', 'series', 'number'], 'documents_environment_type_series_number_unique');
            $table->unique(['unique_filename'], 'documents_unique_filename_unique');
            $table->index(['user_id'], 'documents_user_id_foreign');
            $table->index(['establishment_id'], 'documents_establishment_id_foreign');
            $table->index(['customer_id'], 'documents_customer_id_foreign');
            $table->index(['fiscal_environment'], 'documents_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'documents_state_type_id_foreign');
            $table->index(['group_id'], 'documents_group_id_foreign');
            $table->index(['document_type_id'], 'documents_document_type_id_foreign');
            $table->index(['currency_type_id'], 'documents_currency_type_id_foreign');
            $table->index(['quotation_id'], 'documents_quotation_id_foreign');
            $table->index(['external_id'], 'documents_external_id_index');
            $table->index(['sale_note_id'], 'documents_sale_note_id_foreign');
            $table->index(['series'], 'documents_series_index');
            $table->index(['number'], 'documents_number_index');
            $table->index(['date_of_issue'], 'documents_date_of_issue_index');
            $table->index(['order_note_id'], 'documents_order_note_id_foreign');
            $table->index(['payment_method_type_id'], 'documents_payment_method_type_id_foreign');
            $table->index(['seller_id'], 'documents_seller_id_foreign');
            $table->index(['payment_condition_id'], 'documents_payment_condition_id_foreign');
            $table->index(['dispatch_id'], 'documents_dispatch_id_foreign');
            $table->index(['technical_service_id'], 'documents_technical_service_id_foreign');
            $table->index(['type_period'], 'documents_type_period_index');
            $table->index(['agent_id'], 'documents_agent_id_foreign');
            $table->index(['hotel_rent_id'], 'documents_hotel_rent_id_foreign');
        });
        DB::statement('ALTER TABLE "documents" ADD CONSTRAINT "documents_number_range" CHECK ("number" BETWEEN 0 AND 18446744073709551615)');
        DB::statement('ALTER TABLE "documents" ADD CONSTRAINT "documents_user_rel_suscription_plan_id_range" CHECK ("user_rel_suscription_plan_id" >= 0)');
        DB::statement('ALTER TABLE "documents" ADD CONSTRAINT "documents_quantity_period_range" CHECK ("quantity_period" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "documents" ADD CONSTRAINT "documents_enabled_concurrency_range" CHECK ("enabled_concurrency" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "documents" ADD CONSTRAINT "documents_apply_concurrency_range" CHECK ("apply_concurrency" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "documents" ADD CONSTRAINT "documents_consigned_id_range" CHECK ("consigned_id" >= 0)');
        DB::statement('ALTER TABLE "documents" ADD CONSTRAINT "documents_collect_api_state_id_range" CHECK ("collect_api_state_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
