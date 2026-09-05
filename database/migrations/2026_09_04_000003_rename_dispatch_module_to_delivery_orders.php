<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modules')) {
            DB::table('modules')
                ->where('value', 'guia')
                ->update(['description' => 'Órdenes de entrega']);
        }

        if (Schema::hasTable('module_levels')) {
            DB::table('module_levels')
                ->where('value', 'dispatches')
                ->update(['description' => 'Orden de entrega']);

            DB::table('module_levels')
                ->where('value', 'dispatch_carrier')
                ->update(['description' => 'Orden de entrega del transportista']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modules')) {
            DB::table('modules')
                ->where('value', 'guia')
                ->update(['description' => 'Despachos']);
        }

        if (Schema::hasTable('module_levels')) {
            DB::table('module_levels')
                ->where('value', 'dispatches')
                ->update(['description' => 'Despacho del remitente']);

            DB::table('module_levels')
                ->where('value', 'dispatch_carrier')
                ->update(['description' => 'Despacho del transportista']);
        }
    }
};
