<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(100)
 * - url: varchar(500)
 * - secret: varchar(100)
 * - events: json
 * - is_active: boolean
 * - consecutive_failures: integer
 * - disabled_at: timestamp
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->collation('sab_ci_ai');
            $table->string('url', 500)->collation('sab_ci_ai');
            $table->string('secret', 100)->collation('C');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->integer('consecutive_failures')->default('0');
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['is_active'], 'webhook_subscriptions_is_active_index');
        });
        DB::statement('ALTER TABLE "webhook_subscriptions" ADD CONSTRAINT "webhook_subscriptions_consecutive_failures_range" CHECK ("consecutive_failures" BETWEEN 0 AND 65535)');
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
