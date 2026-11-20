<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - session_id: numeric(20,0)
 * - direction: varchar(255)
 * - phone: varchar(30)
 * - body: text
 * - message_id: varchar(255)
 * - from_me: boolean
 * - raw_payload: json
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('session_id', 20, 0)->nullable();
            $table->string('direction', 255)->collation('sab_ci_ai');
            $table->string('phone', 30)->collation('sab_ci_ai');
            $table->text('body')->collation('sab_ci_ai')->nullable();
            $table->string('message_id', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('from_me')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['session_id'], 'bot_messages_session_id_index');
            $table->index(['phone'], 'bot_messages_phone_index');
            $table->index(['message_id'], 'bot_messages_message_id_index');
        });
        DB::statement('ALTER TABLE "bot_messages" ADD CONSTRAINT "bot_messages_session_id_range" CHECK ("session_id" BETWEEN 0 AND 18446744073709551615)');
        DB::statement('ALTER TABLE "bot_messages" ADD CONSTRAINT "bot_messages_direction_check" CHECK ("direction" IN (\'in\',\'out\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_messages');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
