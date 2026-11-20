<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - company_id: bigint
 * - actor_type: varchar(16)
 * - actor_id: bigint
 * - changed_values: json
 * - changed_fields: text
 * - fiscal_emission_mode: varchar(32)
 * - fiscal_environment: varchar(16)
 * - created_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_configuration_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('company_id');
            $table->string('actor_type', 16)->collation('sab_ci_ai');
            $table->bigInteger('actor_id');
            $table->json('changed_values')->nullable();
            $table->text('changed_fields')->collation('sab_ci_ai');
            $table->string('fiscal_emission_mode', 32)->collation('sab_ci_ai');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id'], 'fiscal_configuration_audits_company_id_foreign');
        });
        DB::statement('ALTER TABLE "fiscal_configuration_audits" ADD CONSTRAINT "fiscal_configuration_audits_actor_id_range" CHECK ("actor_id" >= 0)');
        \App\Database\PostgresBootstrap::installTouchTrigger(DB::connection(), 'fiscal_configuration_audits', 'created_at');
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_configuration_audits');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
