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
 * - observations: text
 * - transport_mode_type_id: varchar(255)
 * - transfer_reason_type_id: varchar(2)
 * - transfer_reason_description: varchar(255)
 * - date_of_shipping: date
 * - transshipment_indicator: boolean
 * - port_code: varchar(255)
 * - unit_type_id: varchar(255)
 * - total_weight: numeric(30,12)
 * - packages_number: integer
 * - container_number: integer
 * - origin: json
 * - delivery: json
 * - dispatcher_id: bigint
 * - driver_id: bigint
 * - license_plates: json
 * - legends: json
 * - optional: json
 * - filename: varchar(255)
 * - qr: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_forms', function (Blueprint $table) {
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
            $table->text('observations')->collation('pro9_ci_ai');
            $table->string('transport_mode_type_id', 255)->collation('pro9_ci_ai');
            $table->string('transfer_reason_type_id', 2)->collation('pro9_ci_ai');
            $table->string('transfer_reason_description', 255)->collation('pro9_ci_ai')->nullable();
            $table->date('date_of_shipping');
            $table->boolean('transshipment_indicator');
            $table->string('port_code', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('unit_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('total_weight', 30, 12);
            $table->integer('packages_number');
            $table->integer('container_number')->nullable();
            $table->json('origin');
            $table->json('delivery');
            $table->bigInteger('dispatcher_id');
            $table->bigInteger('driver_id');
            $table->json('license_plates')->nullable();
            $table->json('legends')->nullable();
            $table->json('optional')->nullable();
            $table->string('filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->text('qr')->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['dispatcher_id'], 'order_forms_dispatcher_id_foreign');
            $table->index(['driver_id'], 'order_forms_driver_id_foreign');
            $table->index(['user_id'], 'order_forms_user_id_foreign');
            $table->index(['establishment_id'], 'order_forms_establishment_id_foreign');
            $table->index(['fiscal_environment'], 'order_forms_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'order_forms_state_type_id_foreign');
            $table->index(['customer_id'], 'order_forms_customer_id_foreign');
            $table->index(['unit_type_id'], 'order_forms_unit_type_id_foreign');
            $table->index(['transport_mode_type_id'], 'order_forms_transport_mode_type_id_foreign');
            $table->index(['transfer_reason_type_id'], 'order_forms_transfer_reason_type_id_foreign');
            $table->index(['date_of_issue'], 'order_forms_date_of_issue_index');
        });
        DB::statement('ALTER TABLE "order_forms" ADD CONSTRAINT "order_forms_packages_number_range" CHECK ("packages_number" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "order_forms" ADD CONSTRAINT "order_forms_container_number_range" CHECK ("container_number" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_forms');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
