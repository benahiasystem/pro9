<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - retention_id: bigint
 * - operation_key: char(36)
 * - action: varchar(8)
 * - status: varchar(24)
 * - payload: json
 * - context: json
 * - result: json
 * - attempt_id: char(36)
 * - attempts: bigint
 * - failures: bigint
 * - started_at: timestamp
 * - next_attempt_at: timestamp
 * - diagnostic: varchar(1000)
 * - actor_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_islr_retention_operations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->bigInteger('retention_id');
            $table->char('operation_key', 36)->collation('pro9_ci_ai');
            $table->string('action', 8)->collation('pro9_ci_ai');
            $table->string('status', 24)->collation('pro9_ci_ai')->default('waiting');
            $table->json('payload');
            $table->json('context');
            $table->json('result')->nullable();
            $table->char('attempt_id', 36)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('attempts')->default('0');
            $table->bigInteger('failures')->default('0');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('diagnostic', 1000)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['operation_key'], 'islr_operation_key_unique');
            $table->unique(['retention_id', 'action'], 'islr_retention_action_unique');
            $table->index(['status', 'next_attempt_at'], 'islr_operations_due_index');
        });
        DB::statement('ALTER TABLE "document_islr_retention_operations" ADD CONSTRAINT "document_islr_retention_operations_attempts_range" CHECK ("attempts" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "document_islr_retention_operations" ADD CONSTRAINT "document_islr_retention_operations_failures_range" CHECK ("failures" BETWEEN 0 AND 4294967295)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_islr_retention_operations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
