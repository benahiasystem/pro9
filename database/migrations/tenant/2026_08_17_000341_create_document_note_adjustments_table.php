<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - kind: varchar(255)
 * - retention_id: bigint
 * - tax_id: bigint
 * - guarantee_fund_id: bigint
 * - amount: numeric(30,12)
 * - status: varchar(32)
 * - diagnostic: varchar(255)
 * - reason: varchar(255)
 * - request: json
 * - reversed_at: timestamp
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - affected_document_id: bigint
 * - operation_key: char(36)
 * - request_hash: char(64)
 * - actor_id: bigint
 * - context: json
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_note_adjustments', function (Blueprint $table) {
            $table->string('kind', 255)->collation('pro9_ci_ai');
            $table->bigInteger('retention_id')->nullable();
            $table->bigInteger('tax_id')->nullable();
            $table->bigInteger('guarantee_fund_id')->nullable();
            $table->decimal('amount', 30, 12);
            $table->string('status', 32)->collation('pro9_ci_ai');
            $table->string('diagnostic', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('reason', 255)->collation('pro9_ci_ai');
            $table->json('request');
            $table->timestamp('reversed_at')->nullable();
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->bigInteger('affected_document_id');
            $table->char('operation_key', 36)->collation('pro9_ci_ai');
            $table->char('request_hash', 64)->collation('C');
            $table->bigInteger('actor_id');
            $table->json('context');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['retention_id'], 'document_note_adjustments_retention_index');
            $table->index(['tax_id'], 'document_note_adjustments_tax_index');
            $table->index(['guarantee_fund_id'], 'document_note_adjustments_fund_index');
            $table->unique(['operation_key'], 'document_note_adjustments_operation_unique');
            $table->index(['document_id'], 'document_note_adjustments_document_index');
            $table->index(['affected_document_id'], 'document_note_adjustments_affected_index');
            $table->index(['actor_id'], 'document_note_adjustments_actor_index');
        });
        DB::statement('ALTER TABLE "document_note_adjustments" ADD CONSTRAINT "document_note_adjustments_kind_check" CHECK ("kind" IN (\'IVA\',\'ISLR\',\'IGTF\',\'fund\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_note_adjustments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
