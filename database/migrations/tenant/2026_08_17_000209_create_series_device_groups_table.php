<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - establishment_id: bigint
 * - name: varchar(255)
 * - module_value: varchar(255)
 * - bound_device_name: varchar(255)
 * - bound_user_id: bigint
 * - bound_at: timestamp
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_device_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('establishment_id')->comment('El grupo vive en un establecimiento');
            $table->string('name', 255)->collation('pro9_ci_ai')->comment('Nombre del grupo: ej. Caja 1');
            $table->string('module_value', 255)->collation('pro9_ci_ai')->nullable()->comment('Modulo asociado (uno solo). Catalogo extensible');
            $table->string('bound_device_name', 255)->collation('pro9_ci_ai')->nullable()->comment('Nombre del equipo vinculado (clave de vinculo)');
            $table->bigInteger('bound_user_id')->nullable()->comment('Usuario que vinculo el grupo');
            $table->timestamp('bound_at')->nullable()->comment('Fecha de vinculo al equipo');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['establishment_id'], 'series_device_groups_establishment_id_foreign');
            $table->index(['bound_device_name'], 'series_device_groups_bound_device_name_index');
        });
        DB::statement('ALTER TABLE "series_device_groups" ADD CONSTRAINT "series_device_groups_bound_user_id_range" CHECK ("bound_user_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('series_device_groups');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
