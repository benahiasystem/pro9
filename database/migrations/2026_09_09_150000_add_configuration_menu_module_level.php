<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Configurar menú" como módulo independiente (no hijo de Configuración),
 * para poder activarlo sin habilitar el módulo Configuración.
 */
return new class extends Migration
{
    public function up()
    {
        // Si quedó el intento anterior como module_level, limpiarlo.
        DB::connection('system')->table('module_levels')
            ->where('value', 'configuration_menu')
            ->delete();

        $exists = DB::connection('system')->table('modules')
            ->where('value', 'configuration_menu')
            ->exists();

        if (!$exists) {
            DB::connection('system')->table('modules')->insert([
                'value' => 'configuration_menu',
                'description' => 'Configurar menú',
                'sort' => 24,
            ]);
        }
    }

    public function down()
    {
        DB::connection('system')->table('modules')
            ->where('value', 'configuration_menu')
            ->delete();
    }
};
