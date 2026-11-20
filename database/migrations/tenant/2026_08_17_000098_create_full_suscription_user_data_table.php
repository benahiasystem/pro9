<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - person_id: bigint
 * - discord_user: varchar(255)
 * - slack_channel: varchar(255)
 * - discord_channel: varchar(255)
 * - gitlab_user: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('full_suscription_user_data', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('person_id')->nullable()->default('0');
            $table->string('discord_user', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('slack_channel', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('discord_channel', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('gitlab_user', 255)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "full_suscription_user_data" ADD CONSTRAINT "full_suscription_user_data_person_id_range" CHECK ("person_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('full_suscription_user_data');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
