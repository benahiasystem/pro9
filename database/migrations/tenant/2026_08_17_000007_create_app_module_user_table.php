<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - app_module_id: bigint
 * - user_id: bigint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_module_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('app_module_id');
            $table->bigInteger('user_id');
        });
        DB::statement('ALTER TABLE "app_module_user" ADD CONSTRAINT "app_module_user_app_module_id_range" CHECK ("app_module_id" >= 0)');
        DB::statement('ALTER TABLE "app_module_user" ADD CONSTRAINT "app_module_user_user_id_range" CHECK ("user_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('app_module_user');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
