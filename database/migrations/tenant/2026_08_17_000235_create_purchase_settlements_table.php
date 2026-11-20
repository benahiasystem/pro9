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
 * - ubl_version: varchar(255)
 * - operation_type_id: varchar(255)
 * - document_type_id: varchar(255)
 * - series: varchar(20)
 * - number: integer
 * - date_of_issue: date
 * - time_of_issue: time
 * - supplier_id: bigint
 * - supplier: json
 * - operation_data: json
 * - currency_type_id: varchar(255)
 * - payment_method_type_id: char(2)
 * - exchange_rate_sale: numeric(18,8)
 * - total_prepayment: numeric(30,12)
 * - total_taxed: numeric(30,12)
 * - total_unaffected: numeric(30,12)
 * - total_exonerated: numeric(30,12)
 * - total_igv: numeric(30,12)
 * - total_taxes: numeric(30,12)
 * - total_value: numeric(30,12)
 * - subtotal: numeric(30,12)
 * - total: numeric(30,12)
 * - legends: json
 * - prepayments: json
 * - related: json
 * - observations: text
 * - filename: varchar(255)
 * - has_pdf: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_settlements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('sab_ci_ai');
            $table->bigInteger('establishment_id');
            $table->json('establishment');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->char('state_type_id', 2)->collation('sab_ci_ai');
            $table->string('ubl_version', 255)->collation('sab_ci_ai');
            $table->string('operation_type_id', 255)->collation('sab_ci_ai');
            $table->string('document_type_id', 255)->collation('sab_ci_ai');
            $table->string('series', 20)->collation('sab_ci_ai');
            $table->integer('number');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->bigInteger('supplier_id');
            $table->json('supplier');
            $table->json('operation_data');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->char('payment_method_type_id', 2)->collation('sab_ci_ai')->nullable();
            $table->decimal('exchange_rate_sale', 18, 8);
            $table->decimal('total_prepayment', 30, 12)->default('0.00');
            $table->decimal('total_taxed', 30, 12)->default('0.00');
            $table->decimal('total_unaffected', 30, 12)->default('0.00');
            $table->decimal('total_exonerated', 30, 12)->default('0.00');
            $table->decimal('total_igv', 30, 12)->default('0.00');
            $table->decimal('total_taxes', 30, 12)->default('0.00');
            $table->decimal('total_value', 30, 12)->default('0.00');
            $table->decimal('subtotal', 30, 12)->default('0.00');
            $table->decimal('total', 30, 12);
            $table->json('legends')->nullable();
            $table->json('prepayments')->nullable();
            $table->json('related')->nullable();
            $table->text('observations')->collation('sab_ci_ai')->nullable();
            $table->string('filename', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('has_pdf')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['filename'], 'purchase_settlements_filename_unique');
            $table->index(['user_id'], 'purchase_settlements_user_id_foreign');
            $table->index(['establishment_id'], 'purchase_settlements_establishment_id_foreign');
            $table->index(['supplier_id'], 'purchase_settlements_supplier_id_foreign');
            $table->index(['fiscal_environment'], 'purchase_settlements_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'purchase_settlements_state_type_id_foreign');
            $table->index(['document_type_id'], 'purchase_settlements_document_type_id_foreign');
            $table->index(['currency_type_id'], 'purchase_settlements_currency_type_id_foreign');
            $table->index(['operation_type_id'], 'purchase_settlements_operation_type_id_foreign');
            $table->index(['series'], 'purchase_settlements_series_index');
            $table->index(['number'], 'purchase_settlements_number_index');
            $table->index(['date_of_issue'], 'purchase_settlements_date_of_issue_index');
            $table->index(['payment_method_type_id'], 'purchase_settlements_payment_method_type_id_foreign');
        });
        DB::statement('ALTER TABLE "purchase_settlements" ADD CONSTRAINT "purchase_settlements_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_settlements');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
