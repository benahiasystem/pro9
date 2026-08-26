<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class TenantAddInventoryToAppModules extends Migration
{
    public function up()
    {
        // Modulo de inventario de la app movil: ver stock por almacen, trasladar y ajustar
        if (!DB::table('app_modules')->where('value', 'inventory')->exists()) {
            DB::table('app_modules')->insert([
                'value' => 'inventory',
                'description' => 'Inventario',
                'order_menu' => 16,
            ]);
        }
    }

    public function down()
    {
        DB::table('app_modules')->where('value', 'inventory')->delete();
    }
}
