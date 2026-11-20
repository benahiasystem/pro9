<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - creation_key: char(36)
 * - creation_hash: char(64)
 * - hka_data: json
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - external_id: char(36)
 * - establishment_id: bigint
 * - establishment: json
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(2)
 * - ubl_version: varchar(255)
 * - document_type_id: varchar(255)
 * - reference_sale_note_id: bigint
 * - reference_document_id: bigint
 * - reference_quotation_id: bigint
 * - reference_order_form_id: bigint
 * - reference_order_note_id: bigint
 * - control_number: varchar(32)
 * - series: varchar(32)
 * - number: numeric(20,0)
 * - date_of_issue: date
 * - time_of_issue: time
 * - date_delivery_to_transport: date
 * - customer_id: bigint
 * - buyer_id: bigint
 * - buyer: json
 * - customer: json
 * - observations: text
 * - transport_mode_type_id: varchar(255)
 * - transfer_reason_type_id: varchar(2)
 * - transfer_reason_description: varchar(255)
 * - discount_stock: boolean
 * - date_of_shipping: date
 * - transshipment_indicator: boolean
 * - port_code: varchar(255)
 * - unit_type_id: varchar(255)
 * - total_weight: numeric(30,12)
 * - packages_number: integer
 * - container_number: integer
 * - related: json
 * - origin: json
 * - delivery: json
 * - has_transport_driver_01: boolean
 * - dispatcher_id: bigint
 * - dispatcher: json
 * - driver_id: bigint
 * - driver: json
 * - secondary_drivers: json
 * - order_form_external: varchar(255)
 * - license_plate: varchar(255)
 * - sender_id: bigint
 * - sender_data: json
 * - sender_address_id: bigint
 * - sender_address_data: json
 * - receiver_id: bigint
 * - receiver_data: json
 * - receiver_address_id: bigint
 * - receiver_address_data: json
 * - transport_id: bigint
 * - transport_data: json
 * - secondary_transports: json
 * - legends: json
 * - payer: json
 * - optional: json
 * - filename: varchar(255)
 * - hash: varchar(255)
 * - has_pdf: boolean
 * - document_id: bigint
 * - qr_url: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - data_affected_document: text
 * - terms_condition: text
 * - additional_data: json
 * - origin_address_id: bigint
 * - delivery_address_id: bigint
 * - reference_documents: json
 * - custom_fields_data: json
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatches', function (Blueprint $table) {
            $table->char('creation_key', 36)->collation('pro9_ci_ai')->nullable();
            $table->char('creation_hash', 64)->collation('C')->nullable();
            $table->json('hka_data')->nullable();
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('pro9_ci_ai');
            $table->bigInteger('establishment_id');
            $table->json('establishment');
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai');
            $table->char('state_type_id', 2)->collation('pro9_ci_ai');
            $table->string('ubl_version', 255)->collation('pro9_ci_ai');
            $table->string('document_type_id', 255)->collation('pro9_ci_ai');
            $table->bigInteger('reference_sale_note_id')->nullable();
            $table->bigInteger('reference_document_id')->nullable();
            $table->bigInteger('reference_quotation_id')->nullable();
            $table->bigInteger('reference_order_form_id')->nullable();
            $table->bigInteger('reference_order_note_id')->nullable();
            $table->string('control_number', 32)->collation('pro9_ci_ai')->nullable();
            $table->string('series', 32)->collation('pro9_ci_ai');
            $table->decimal('number', 20, 0);
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->date('date_delivery_to_transport')->nullable()->comment('para orden de entrega solo con transporte publico');
            $table->bigInteger('customer_id')->nullable();
            $table->bigInteger('buyer_id')->nullable();
            $table->json('buyer')->nullable();
            $table->json('customer')->nullable();
            $table->text('observations')->collation('pro9_ci_ai')->nullable();
            $table->string('transport_mode_type_id', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('transfer_reason_type_id', 2)->collation('pro9_ci_ai')->nullable();
            $table->string('transfer_reason_description', 255)->collation('pro9_ci_ai')->nullable();
            $table->boolean('discount_stock')->default(false);
            $table->date('date_of_shipping');
            $table->boolean('transshipment_indicator');
            $table->string('port_code', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('unit_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('total_weight', 30, 12);
            $table->integer('packages_number')->nullable();
            $table->integer('container_number')->nullable();
            $table->json('related')->nullable()->comment('Numero de DAM');
            $table->json('origin')->nullable();
            $table->json('delivery')->nullable();
            $table->boolean('has_transport_driver_01')->nullable()->default(false);
            $table->bigInteger('dispatcher_id')->nullable();
            $table->json('dispatcher')->nullable();
            $table->bigInteger('driver_id')->nullable();
            $table->json('driver')->nullable();
            $table->json('secondary_drivers')->nullable();
            $table->string('order_form_external', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('license_plate', 255)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('sender_id')->nullable();
            $table->json('sender_data')->nullable();
            $table->bigInteger('sender_address_id')->nullable();
            $table->json('sender_address_data')->nullable();
            $table->bigInteger('receiver_id')->nullable();
            $table->json('receiver_data')->nullable();
            $table->bigInteger('receiver_address_id')->nullable();
            $table->json('receiver_address_data')->nullable();
            $table->bigInteger('transport_id')->nullable();
            $table->json('transport_data')->nullable();
            $table->json('secondary_transports')->nullable();
            $table->json('legends')->nullable();
            $table->json('payer')->nullable();
            $table->json('optional')->nullable();
            $table->string('filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('hash', 255)->collation('C')->nullable();
            $table->boolean('has_pdf')->default(false);
            $table->bigInteger('document_id')->nullable();
            $table->string('qr_url', 255)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->text('data_affected_document')->collation('pro9_ci_ai')->nullable();
            $table->text('terms_condition')->collation('pro9_ci_ai')->nullable();
            $table->json('additional_data')->nullable();
            $table->bigInteger('origin_address_id')->nullable();
            $table->bigInteger('delivery_address_id')->nullable();
            $table->json('reference_documents')->nullable();
            $table->json('custom_fields_data')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['creation_key'], 'dispatches_creation_unique');
            $table->unique(['establishment_id', 'fiscal_environment', 'document_type_id', 'series', 'number'], 'dispatches_environment_type_series_number_unique');
            $table->index(['user_id'], 'dispatches_user_id_foreign');
            $table->index(['establishment_id'], 'dispatches_establishment_id_foreign');
            $table->index(['fiscal_environment'], 'dispatches_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'dispatches_state_type_id_foreign');
            $table->index(['document_type_id'], 'dispatches_document_type_id_foreign');
            $table->index(['customer_id'], 'dispatches_customer_id_foreign');
            $table->index(['unit_type_id'], 'dispatches_unit_type_id_foreign');
            $table->index(['transport_mode_type_id'], 'dispatches_transport_mode_type_id_foreign');
            $table->index(['transfer_reason_type_id'], 'dispatches_transfer_reason_type_id_foreign');
            $table->index(['document_id'], 'dispatches_document_id_foreign');
            $table->index(['dispatcher_id'], 'dispatches_dispatcher_id_foreign');
            $table->index(['driver_id'], 'dispatches_driver_id_foreign');
            $table->index(['transport_id'], 'dispatches_transport_id_foreign');
            $table->index(['sender_id'], 'dispatches_sender_id_foreign');
            $table->index(['sender_address_id'], 'dispatches_sender_address_id_foreign');
            $table->index(['receiver_id'], 'dispatches_receiver_id_foreign');
            $table->index(['receiver_address_id'], 'dispatches_receiver_address_id_foreign');
            $table->index(['buyer_id'], 'dispatches_buyer_id_foreign');
        });
        DB::statement('ALTER TABLE "dispatches" ADD CONSTRAINT "dispatches_reference_sale_note_id_range" CHECK ("reference_sale_note_id" >= 0)');
        DB::statement('ALTER TABLE "dispatches" ADD CONSTRAINT "dispatches_reference_document_id_range" CHECK ("reference_document_id" >= 0)');
        DB::statement('ALTER TABLE "dispatches" ADD CONSTRAINT "dispatches_reference_quotation_id_range" CHECK ("reference_quotation_id" >= 0)');
        DB::statement('ALTER TABLE "dispatches" ADD CONSTRAINT "dispatches_reference_order_form_id_range" CHECK ("reference_order_form_id" >= 0)');
        DB::statement('ALTER TABLE "dispatches" ADD CONSTRAINT "dispatches_reference_order_note_id_range" CHECK ("reference_order_note_id" >= 0)');
        DB::statement('ALTER TABLE "dispatches" ADD CONSTRAINT "dispatches_number_range" CHECK ("number" BETWEEN 0 AND 18446744073709551615)');
        DB::statement('ALTER TABLE "dispatches" ADD CONSTRAINT "dispatches_packages_number_range" CHECK ("packages_number" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "dispatches" ADD CONSTRAINT "dispatches_container_number_range" CHECK ("container_number" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "dispatches" ADD CONSTRAINT "dispatches_origin_address_id_range" CHECK ("origin_address_id" >= 0)');
        DB::statement('ALTER TABLE "dispatches" ADD CONSTRAINT "dispatches_delivery_address_id_range" CHECK ("delivery_address_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatches');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
