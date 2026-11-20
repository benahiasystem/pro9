<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - fiscal_environment: varchar(16)
 * - establishment_id: bigint
 * - establishment: json
 * - customer_id: bigint
 * - customer: json
 * - currency_type_id: text
 * - payment_condition_id: text
 * - payment_method_type_id: text
 * - seller_id: bigint
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
 * - is_editable: smallint
 * - cellphone: varchar(255)
 * - date_of_issue: date
 * - time_of_issue: time
 * - description: text
 * - state: text
 * - reason: text
 * - serial_number: varchar(255)
 * - filename: varchar(255)
 * - cost: numeric(30,12)
 * - prepayment: numeric(30,12)
 * - activities: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - brand: varchar(255)
 * - equipment: varchar(255)
 * - important_note: text
 * - repair: boolean
 * - warranty: boolean
 * - maintenance: boolean
 * - diagnosis: boolean
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_services', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->bigInteger('establishment_id')->nullable()->default('0');
            $table->json('establishment')->nullable();
            $table->bigInteger('customer_id');
            $table->json('customer');
            $table->text('currency_type_id')->collation('sab_ci_ai')->nullable();
            $table->text('payment_condition_id')->collation('sab_ci_ai')->nullable();
            $table->text('payment_method_type_id')->collation('sab_ci_ai')->nullable();
            $table->bigInteger('seller_id')->nullable()->default('0');
            $table->decimal('exchange_rate_sale', 18, 8)->nullable()->default('0.000');
            $table->decimal('total_prepayment', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_charge', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_discount', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_exportation', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_free', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_taxed', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_unaffected', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_exonerated', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_igv', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_igv_free', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_base_other_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_other_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_value', 30, 12)->nullable()->default('0.00');
            $table->decimal('subtotal', 30, 12)->nullable()->default('0.00');
            $table->decimal('total', 30, 12)->nullable()->default('0.00');
            $table->smallInteger('is_editable')->nullable()->default('0');
            $table->string('cellphone', 255)->collation('sab_ci_ai');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->text('description')->collation('sab_ci_ai');
            $table->text('state')->collation('sab_ci_ai');
            $table->text('reason')->collation('sab_ci_ai');
            $table->string('serial_number', 255)->collation('sab_ci_ai');
            $table->string('filename', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('cost', 30, 12)->default('0.00');
            $table->decimal('prepayment', 30, 12)->default('0.00');
            $table->text('activities')->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('brand', 255)->collation('sab_ci_ai')->nullable();
            $table->string('equipment', 255)->collation('sab_ci_ai')->nullable();
            $table->text('important_note')->collation('sab_ci_ai')->nullable();
            $table->boolean('repair')->default(false);
            $table->boolean('warranty')->default(false);
            $table->boolean('maintenance')->default(false);
            $table->boolean('diagnosis')->default(false);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['user_id'], 'technical_services_user_id_foreign');
            $table->index(['fiscal_environment'], 'technical_services_fiscal_environment_foreign');
            $table->index(['customer_id'], 'technical_services_customer_id_foreign');
            $table->index(['date_of_issue'], 'technical_services_date_of_issue_index');
            $table->index(['serial_number'], 'technical_services_serial_number_index');
        });
        DB::statement('ALTER TABLE "technical_services" ADD CONSTRAINT "technical_services_establishment_id_range" CHECK ("establishment_id" >= 0)');
        DB::statement('ALTER TABLE "technical_services" ADD CONSTRAINT "technical_services_seller_id_range" CHECK ("seller_id" >= 0)');
        DB::statement('ALTER TABLE "technical_services" ADD CONSTRAINT "technical_services_is_editable_range" CHECK ("is_editable" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_services');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
