<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class TenantAddFinanceToAppModules extends Migration
{
    public function up()
    {
        // Modulo Finanzas de la app movil: movimientos de ingresos/egresos,
        // registro de ingresos y gastos diversos
        if (!DB::table('app_modules')->where('value', 'finance')->exists()) {
            DB::table('app_modules')->insert([
                'value' => 'finance',
                'description' => 'Finanzas',
                'order_menu' => 17,
            ]);
        }
    }

    public function down()
    {
        DB::table('app_modules')->where('value', 'finance')->delete();
    }
}
