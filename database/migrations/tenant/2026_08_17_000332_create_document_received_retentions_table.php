<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - fiscal_context: json
 * - application_status: varchar(24)
 * - operation_key: char(36)
 * - input_hash: char(64)
 * - input_snapshot: json
 * - taxable_base: numeric(30,12)
 * - diagnostic: varchar(1000)
 * - reversal_key: char(36)
 * - reversal_reason: varchar(255)
 * - reversed_at: timestamp
 * - reversed_by: bigint
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - tax_kind: varchar(4)
 * - voucher_number: varchar(100)
 * - voucher_date: date
 * - agent_id: bigint
 * - agent: json
 * - agent_number: varchar(32)
 * - concept_id: char(3)
 * - base: numeric(30,12)
 * - percentage: numeric(30,12)
 * - subtrahend: numeric(30,12)
 * - amount: numeric(30,12)
 * - currency_type_id: varchar(255)
 * - exchange_rate: numeric(18,8)
 * - applied_amount: numeric(30,12)
 * - attachment: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - active_iva_document_id: bigint
 * - active_islr_document_id: bigint
 * - active_voucher_number: varchar(100)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_received_retentions', function (Blueprint $table) {
            $table->json('fiscal_context')->nullable();
            $table->string('application_status', 24)->collation('sab_ci_ai')->default('applied');
            $table->char('operation_key', 36)->collation('sab_ci_ai')->nullable();
            $table->char('input_hash', 64)->collation('C')->nullable();
            $table->json('input_snapshot')->nullable();
            $table->decimal('taxable_base', 30, 12)->default('0');
            $table->string('diagnostic', 1000)->collation('sab_ci_ai')->nullable();
            $table->char('reversal_key', 36)->collation('sab_ci_ai')->nullable();
            $table->string('reversal_reason', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->bigInteger('reversed_by')->nullable();
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->string('tax_kind', 4)->collation('sab_ci_ai');
            $table->string('voucher_number', 100)->collation('sab_ci_ai');
            $table->date('voucher_date');
            $table->bigInteger('agent_id');
            $table->json('agent');
            $table->string('agent_number', 32)->collation('sab_ci_ai');
            $table->char('concept_id', 3)->collation('sab_ci_ai')->nullable();
            $table->decimal('base', 30, 12)->default('0');
            $table->decimal('percentage', 30, 12)->default('0');
            $table->decimal('subtrahend', 30, 12)->default('0');
            $table->decimal('amount', 30, 12)->default('0');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->decimal('exchange_rate', 18, 8);
            $table->decimal('applied_amount', 30, 12)->default('0');
            $table->string('attachment', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->bigInteger('active_iva_document_id')->nullable()->storedAs('CASE WHEN tax_kind = \'IVA\' AND (application_status = \'pending_validation\' OR application_status = \'applied\') THEN document_id ELSE NULL END');
            $table->bigInteger('active_islr_document_id')->nullable()->storedAs('CASE WHEN tax_kind = \'ISLR\' AND (application_status = \'pending_validation\' OR application_status = \'applied\') THEN document_id ELSE NULL END');
            $table->string('active_voucher_number', 100)->collation('sab_ci_ai')->nullable()->storedAs('CASE WHEN (application_status = \'pending_validation\' OR application_status = \'applied\') THEN voucher_number ELSE NULL END');
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['active_iva_document_id'], 'received_iva_active_unique');
            $table->unique(['active_islr_document_id'], 'received_islr_active_unique');
            $table->unique(['operation_key'], 'received_retention_operation_unique');
            $table->unique(['reversal_key'], 'received_retention_reversal_unique');
            $table->unique(['document_id', 'tax_kind', 'agent_number', 'active_voucher_number'], 'received_retention_voucher_unique');
        });
        DB::statement('ALTER TABLE "document_received_retentions" ADD CONSTRAINT "document_received_retentions_active_iva_document_id_range" CHECK ("active_iva_document_id" >= 0)');
        DB::statement('ALTER TABLE "document_received_retentions" ADD CONSTRAINT "document_received_retentions_active_islr_document_id_range" CHECK ("active_islr_document_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_received_retentions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
