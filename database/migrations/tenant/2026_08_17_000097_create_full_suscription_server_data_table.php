<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - person_id: bigint
 * - host: varchar(255)
 * - ip: varchar(45)
 * - user: varchar(255)
 * - password: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('full_suscription_server_data', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('person_id')->nullable()->default('0');
            $table->string('host', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('ip', 45)->collation('pro9_ci_ai')->nullable();
            $table->string('user', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('password', 255)->collation('C')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "full_suscription_server_data" ADD CONSTRAINT "full_suscription_server_data_person_id_range" CHECK ("person_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('full_suscription_server_data');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
