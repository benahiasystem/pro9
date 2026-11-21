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
 * - prefix: char(2)
 * - series: varchar(255)
 * - number: integer
 * - date_of_issue: date
 * - time_of_issue: time
 * - customer_id: bigint
 * - customer: json
 * - currency_type_id: varchar(255)
 * - payment_method_type_id: char(2)
 * - exchange_rate_sale: numeric(18,8)
 * - point_system: boolean
 * - point_system_data: json
 * - created_from_pos: boolean
 * - apply_concurrency: boolean
 * - enabled_concurrency: boolean
 * - automatic_date_of_issue: date
 * - quantity_period: integer
 * - type_period: varchar(255)
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
 * - dispatch_ticket_pdf: boolean
 * - charges: json
 * - discounts: json
 * - prepayments: json
 * - guides: json
 * - related: json
 * - perception: json
 * - legends: json
 * - additional_information: text
 * - filename: varchar(255)
 * - unique_filename: varchar(255)
 * - quotation_id: bigint
 * - order_note_id: bigint
 * - technical_service_id: bigint
 * - order_id: bigint
 * - total_canceled: boolean
 * - changed: boolean
 * - paid: boolean
 * - license_plate: varchar(255)
 * - plate_number: varchar(255)
 * - reference_data: varchar(500)
 * - agent_id: bigint
 * - observation: text
 * - purchase_order: varchar(50)
 * - document_id: bigint
 * - user_rel_suscription_plan_id: bigint
 * - due_date: date
 * - created_at: timestamp
 * - updated_at: timestamp
 * - seller_id: bigint
 * - grade: text
 * - section: text
 * - terms_condition: text
 * - hotel_data_persons: json
 * - source_module: varchar(255)
 * - hotel_rent_id: bigint
 * - payment_condition_id: varchar(255)
 * - consigned_id: bigint
 * - consigned_address: varchar(255)
 * - consigned_ubigeo: varchar(255)
 * - custom_fields_data: json
 * - document_type_id: char(2)
 * - user_rel_subscription_plan_id: bigint
 * - voided_description: varchar(255)
 * - calculation_snapshot: json
 * - total_payable: numeric(30,12)
 * - settlement_adjustment: numeric(30,12)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('sab_ci_ai');
            $table->bigInteger('establishment_id');
            $table->json('establishment');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->char('state_type_id', 2)->collation('sab_ci_ai');
            $table->char('prefix', 2)->collation('sab_ci_ai');
            $table->string('series', 255)->collation('sab_ci_ai')->nullable();
            $table->integer('number')->nullable();
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->bigInteger('customer_id');
            $table->json('customer');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->char('payment_method_type_id', 2)->collation('sab_ci_ai')->nullable();
            $table->decimal('exchange_rate_sale', 18, 8);
            $table->boolean('point_system')->default(false)->comment('indica si se uso en sistema por puntos');
            $table->json('point_system_data')->nullable()->comment('datos de sistema por puntos');
            $table->boolean('created_from_pos')->default(false)->comment('indica si se registro desde pos');
            $table->boolean('apply_concurrency')->default(false);
            $table->boolean('enabled_concurrency')->default(false);
            $table->date('automatic_date_of_issue')->nullable();
            $table->integer('quantity_period')->nullable();
            $table->string('type_period', 255)->collation('sab_ci_ai')->nullable();
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
            $table->boolean('dispatch_ticket_pdf')->default(false);
            $table->json('charges')->nullable();
            $table->json('discounts')->nullable();
            $table->json('prepayments')->nullable();
            $table->json('guides')->nullable();
            $table->json('related')->nullable();
            $table->json('perception')->nullable();
            $table->json('legends')->nullable();
            $table->text('additional_information')->collation('sab_ci_ai')->nullable();
            $table->string('filename', 255)->collation('sab_ci_ai')->nullable();
            $table->string('unique_filename', 255)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('quotation_id')->nullable();
            $table->bigInteger('order_note_id')->nullable();
            $table->bigInteger('technical_service_id')->nullable();
            $table->bigInteger('order_id')->nullable();
            $table->boolean('total_canceled')->default(false);
            $table->boolean('changed')->default(false);
            $table->boolean('paid')->default(false);
            $table->string('license_plate', 255)->collation('sab_ci_ai')->nullable();
            $table->string('plate_number', 255)->collation('sab_ci_ai')->nullable();
            $table->string('reference_data', 500)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('agent_id')->nullable();
            $table->text('observation')->collation('sab_ci_ai')->nullable();
            $table->string('purchase_order', 50)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('document_id')->nullable();
            $table->bigInteger('user_rel_suscription_plan_id')->nullable()->default('0')->comment('Relacion con suscripciones');
            $table->date('due_date')->nullable()->comment('Fecha de vencimiento');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->bigInteger('seller_id')->nullable()->default('0');
            $table->text('grade')->collation('sab_ci_ai')->nullable()->comment('Grado designado - utilizado en matricula');
            $table->text('section')->collation('sab_ci_ai')->nullable()->comment('Seccion designado - utilizado en matricula');
            $table->text('terms_condition')->collation('sab_ci_ai')->nullable();
            $table->json('hotel_data_persons')->nullable()->comment('datos de personas - utilizado en hotel');
            $table->string('source_module', 255)->collation('sab_ci_ai')->nullable()->comment('origen del documento - hotel');
            $table->bigInteger('hotel_rent_id')->nullable()->comment('referencia donde se genera el documento - hotel');
            $table->string('payment_condition_id', 255)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('consigned_id')->nullable();
            $table->string('consigned_address', 255)->collation('sab_ci_ai')->nullable();
            $table->string('consigned_ubigeo', 255)->collation('sab_ci_ai')->nullable();
            $table->json('custom_fields_data')->nullable();
            $table->char('document_type_id', 2)->collation('sab_ci_ai')->default('');
            $table->bigInteger('user_rel_subscription_plan_id')->nullable()->default('0')->comment('Relacion con suscripciones');
            $table->string('voided_description', 255)->collation('sab_ci_ai')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->decimal('total_payable', 30, 12)->nullable();
            $table->decimal('settlement_adjustment', 30, 12)->default('0');
            $table->unique(['establishment_id', 'fiscal_environment', 'series', 'number'], 'sale_notes_branch_number_unique');
            $table->unique(['unique_filename'], 'sale_notes_unique_filename_unique');
            $table->index(['user_id'], 'sale_notes_user_id_foreign');
            $table->index(['establishment_id'], 'sale_notes_establishment_id_foreign');
            $table->index(['customer_id'], 'sale_notes_customer_id_foreign');
            $table->index(['fiscal_environment'], 'sale_notes_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'sale_notes_state_type_id_foreign');
            $table->index(['currency_type_id'], 'sale_notes_currency_type_id_foreign');
            $table->index(['quotation_id'], 'sale_notes_quotation_id_foreign');
            $table->index(['apply_concurrency'], 'sale_notes_apply_concurrency_index');
            $table->index(['type_period'], 'sale_notes_type_period_index');
            $table->index(['quantity_period'], 'sale_notes_quantity_period_index');
            $table->index(['automatic_date_of_issue'], 'sale_notes_automatic_date_of_issue_index');
            $table->index(['enabled_concurrency'], 'sale_notes_enabled_concurrency_index');
            $table->index(['order_note_id'], 'sale_notes_order_note_id_foreign');
            $table->index(['payment_method_type_id'], 'sale_notes_payment_method_type_id_foreign');
            $table->index(['order_id'], 'sale_notes_order_id_foreign');
            $table->index(['technical_service_id'], 'sale_notes_technical_service_id_foreign');
            $table->index(['agent_id'], 'sale_notes_agent_id_foreign');
            $table->index(['changed'], 'sale_notes_changed_index');
            $table->index(['hotel_rent_id'], 'sale_notes_hotel_rent_id_foreign');
            $table->index(['payment_condition_id'], 'sale_notes_payment_condition_id_foreign');
        });
        DB::statement('ALTER TABLE "sale_notes" ADD CONSTRAINT "sale_notes_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "sale_notes" ADD CONSTRAINT "sale_notes_quantity_period_range" CHECK ("quantity_period" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "sale_notes" ADD CONSTRAINT "sale_notes_document_id_range" CHECK ("document_id" >= 0)');
        DB::statement('ALTER TABLE "sale_notes" ADD CONSTRAINT "sale_notes_user_rel_suscription_plan_id_range" CHECK ("user_rel_suscription_plan_id" >= 0)');
        DB::statement('ALTER TABLE "sale_notes" ADD CONSTRAINT "sale_notes_seller_id_range" CHECK ("seller_id" >= 0)');
        DB::statement('ALTER TABLE "sale_notes" ADD CONSTRAINT "sale_notes_consigned_id_range" CHECK ("consigned_id" >= 0)');
        DB::statement('ALTER TABLE "sale_notes" ADD CONSTRAINT "sale_notes_user_rel_subscription_plan_id_range" CHECK ("user_rel_subscription_plan_id" >= 0)');
        // ######## INICIO MONEDA DOCUMENTAL DE VENTAS ########
        DB::statement("ALTER TABLE sale_notes ADD CONSTRAINT sale_notes_currency_ves_check CHECK (currency_type_id IS NOT NULL AND currency_type_id = 'VES')");
        // ######## FIN MONEDA DOCUMENTAL DE VENTAS ########
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_notes');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
