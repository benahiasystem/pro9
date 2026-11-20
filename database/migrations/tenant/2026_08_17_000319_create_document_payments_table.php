<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - fiscal_context: json
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - date_of_payment: date
 * - payment_method_type_id: char(2)
 * - reference: varchar(255)
 * - payment_received: boolean
 * - change: numeric(30,12)
 * - payment: numeric(30,12)
 * - currency_type_id: varchar(255)
 * - exchange_rate: numeric(18,8)
 * - exchange_rate_source: varchar(255)
 * - exchange_rate_date: date
 * - original_amount: numeric(30,12)
 * - receipt_parent_id: bigint
 * - tax_amount: numeric(30,12)
 * - payment_method_snapshot: json
 * - igtf_status: varchar(16)
 * - exemption_reason: varchar(255)
 * - operation_key: char(36)
 * - reversed_at: timestamp
 * - reversal_reason: varchar(255)
 * - reversed_by: bigint
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_payments', function (Blueprint $table) {
            $table->json('fiscal_context')->nullable();
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->date('date_of_payment');
            $table->char('payment_method_type_id', 2)->collation('pro9_ci_ai');
            $table->string('reference', 255)->collation('pro9_ci_ai')->nullable();
            $table->boolean('payment_received')->nullable();
            $table->decimal('change', 30, 12)->nullable();
            $table->decimal('payment', 30, 12);
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('exchange_rate', 18, 8);
            $table->string('exchange_rate_source', 255)->collation('pro9_ci_ai')->nullable();
            $table->date('exchange_rate_date')->nullable();
            $table->decimal('original_amount', 30, 12);
            $table->bigInteger('receipt_parent_id')->nullable();
            $table->decimal('tax_amount', 30, 12)->default('0.00');
            $table->json('payment_method_snapshot');
            $table->string('igtf_status', 16)->collation('pro9_ci_ai')->default('not_applicable');
            $table->string('exemption_reason', 255)->collation('pro9_ci_ai')->nullable();
            $table->char('operation_key', 36)->collation('pro9_ci_ai');
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 255)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('reversed_by')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['operation_key'], 'document_payments_operation_unique');
            $table->unique(['receipt_parent_id'], 'document_payments_receipt_parent_unique');
            $table->index(['document_id'], 'document_payments_document_id_foreign');
            $table->index(['payment_method_type_id'], 'document_payments_payment_method_type_id_foreign');
            $table->index(['date_of_payment'], 'document_payments_date_of_payment_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_payments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
