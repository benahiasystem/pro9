<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Configurar menú" como módulo independiente en el tenant.
 * Por defecto se asigna a admins para no quitar la función existente.
 */
return new class extends Migration
{
    public function up()
    {
        // Limpiar el intento anterior (era module_level bajo configuration).
        $oldLevel = DB::connection('tenant')->table('module_levels')
            ->where('value', 'configuration_menu')
            ->first();

        if ($oldLevel) {
            DB::connection('tenant')->table('module_level_user')
                ->where('module_level_id', $oldLevel->id)
                ->delete();

            DB::connection('tenant')->table('module_levels')
                ->where('id', $oldLevel->id)
                ->delete();
        }

        $module = DB::connection('tenant')->table('modules')
            ->where('value', 'configuration_menu')
            ->first();

        if (!$module) {
            $moduleId = DB::connection('tenant')->table('modules')->insertGetId([
                'value' => 'configuration_menu',
                'description' => 'Configurar menú',
                'order_menu' => 24,
            ]);
        } else {
            $moduleId = $module->id;
        }

        $userIds = DB::connection('tenant')->table('users')
            ->where('type', 'admin')
            ->pluck('id');

        foreach ($userIds as $userId) {
            $exists = DB::connection('tenant')->table('module_user')
                ->where('user_id', $userId)
                ->where('module_id', $moduleId)
                ->exists();

            if (!$exists) {
                DB::connection('tenant')->table('module_user')->insert([
                    'user_id' => $userId,
                    'module_id' => $moduleId,
                ]);
            }
        }
    }

    public function down()
    {
        $module = DB::connection('tenant')->table('modules')
            ->where('value', 'configuration_menu')
            ->first();

        if ($module) {
            DB::connection('tenant')->table('module_user')
                ->where('module_id', $module->id)
                ->delete();

            DB::connection('tenant')->table('modules')
                ->where('id', $module->id)
                ->delete();
        }
    }
};
