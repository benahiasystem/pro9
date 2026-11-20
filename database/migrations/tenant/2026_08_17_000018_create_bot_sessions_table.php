<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - customer_phone: varchar(30)
 * - state: varchar(255)
 * - context_json: json
 * - pending_document_json: json
 * - last_activity_at: timestamp
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('customer_phone', 30)->collation('sab_ci_ai');
            $table->string('state', 255)->collation('sab_ci_ai')->default('idle');
            $table->json('context_json')->nullable();
            $table->json('pending_document_json')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['customer_phone'], 'bot_sessions_customer_phone_index');
        });
        DB::statement('ALTER TABLE "bot_sessions" ADD CONSTRAINT "bot_sessions_state_check" CHECK ("state" IN (\'idle\',\'active\',\'awaiting_confirm\',\'paused\',\'closed\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_sessions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
