<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - document_payment_id: bigint
 * - adjustment_id: bigint
 * - component: varchar(255)
 * - date_of_payment: date
 * - payment_method_type_id: char(2)
 * - currency_type_id: varchar(255)
 * - original_amount: numeric(30,12)
 * - amount: numeric(30,12)
 * - exchange_rate: numeric(18,8)
 * - exchange_rate_source: varchar(50)
 * - reference: varchar(255)
 * - reversed_at: timestamp
 * - reversed_by: bigint
 * - reversal_key: char(36)
 * - reversal_reason: varchar(255)
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
        Schema::create('document_note_refunds', function (Blueprint $table) {
            $table->bigInteger('document_payment_id');
            $table->bigInteger('adjustment_id')->nullable();
            $table->string('component', 255)->collation('sab_ci_ai')->default('principal');
            $table->date('date_of_payment');
            $table->char('payment_method_type_id', 2)->collation('sab_ci_ai');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->decimal('original_amount', 30, 12);
            $table->decimal('amount', 30, 12);
            $table->decimal('exchange_rate', 18, 8);
            $table->string('exchange_rate_source', 50)->collation('sab_ci_ai');
            $table->string('reference', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->bigInteger('reversed_by')->nullable();
            $table->char('reversal_key', 36)->collation('sab_ci_ai')->nullable();
            $table->string('reversal_reason', 255)->collation('sab_ci_ai')->nullable();
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->bigInteger('affected_document_id');
            $table->char('operation_key', 36)->collation('sab_ci_ai');
            $table->char('request_hash', 64)->collation('C');
            $table->bigInteger('actor_id');
            $table->json('context');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['reversal_key'], 'document_note_refunds_reversal_unique');
            $table->index(['document_payment_id'], 'document_note_refunds_payment_index');
            $table->index(['adjustment_id'], 'document_note_refunds_adjustment_index');
            $table->index(['reversed_by'], 'document_note_refunds_reversed_by_index');
            $table->index(['payment_method_type_id'], 'document_note_refunds_method_index');
            $table->index(['currency_type_id'], 'document_note_refunds_currency_index');
            $table->unique(['operation_key'], 'document_note_refunds_operation_unique');
            $table->index(['document_id'], 'document_note_refunds_document_index');
            $table->index(['affected_document_id'], 'document_note_refunds_affected_index');
            $table->index(['actor_id'], 'document_note_refunds_actor_index');
        });
        DB::statement('ALTER TABLE "document_note_refunds" ADD CONSTRAINT "document_note_refunds_component_check" CHECK ("component" IN (\'principal\',\'IGTF\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_note_refunds');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
