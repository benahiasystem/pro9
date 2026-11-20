<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - module_level_id: bigint
 * - user_id: bigint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_level_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('module_level_id');
            $table->bigInteger('user_id');
        });
        DB::statement('ALTER TABLE "module_level_user" ADD CONSTRAINT "module_level_user_module_level_id_range" CHECK ("module_level_id" >= 0)');
        DB::statement('ALTER TABLE "module_level_user" ADD CONSTRAINT "module_level_user_user_id_range" CHECK ("user_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('module_level_user');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
