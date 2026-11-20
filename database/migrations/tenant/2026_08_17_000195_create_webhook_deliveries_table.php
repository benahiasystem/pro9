<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - webhook_subscription_id: bigint
 * - event: varchar(50)
 * - event_id: char(36)
 * - payload: json
 * - status: varchar(10)
 * - attempts: smallint
 * - response_code: smallint
 * - response_body: text
 * - error_message: text
 * - sent_at: timestamp
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('webhook_subscription_id');
            $table->string('event', 50)->collation('pro9_ci_ai');
            $table->char('event_id', 36)->collation('pro9_ci_ai');
            $table->json('payload');
            $table->string('status', 10)->collation('pro9_ci_ai')->default('pending');
            $table->smallInteger('attempts')->default('0');
            $table->smallInteger('response_code')->nullable();
            $table->text('response_body')->collation('pro9_ci_ai')->nullable();
            $table->text('error_message')->collation('pro9_ci_ai')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['webhook_subscription_id', 'status'], 'webhook_deliveries_webhook_subscription_id_status_index');
            $table->index(['created_at'], 'webhook_deliveries_created_at_index');
            $table->index(['event'], 'webhook_deliveries_event_index');
        });
        DB::statement('ALTER TABLE "webhook_deliveries" ADD CONSTRAINT "webhook_deliveries_attempts_range" CHECK ("attempts" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "webhook_deliveries" ADD CONSTRAINT "webhook_deliveries_response_code_range" CHECK ("response_code" BETWEEN -32768 AND 32767)');
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
