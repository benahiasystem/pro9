<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Activa USD (Dólares Americanos) en el listado de monedas por defecto.
 */
return new class extends Migration
{
    public function up()
    {
        DB::connection('tenant')->table('cat_currency_types')
            ->where('id', 'USD')
            ->update(['active' => true]);
    }

    public function down()
    {
        DB::connection('tenant')->table('cat_currency_types')
            ->where('id', 'USD')
            ->update(['active' => false]);
    }
};
