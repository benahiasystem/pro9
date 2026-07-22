<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Símbolo de moneda con el que el front pinta los precios.
     *
     * Un solo ajuste para todo el marketplace (una comunidad, una moneda). Se
     * deja configurable en vez de hardcodear «S/» para que el módulo sirva a
     * cualquier reseller; el default cubre el caso peruano habitual.
     */
    public function up()
    {
        DB::connection('system')->table('marketplace_settings')->updateOrInsert(
            ['key' => 'currency_symbol'],
            ['value' => 'S/', 'type' => 'string']
        );
    }

    public function down()
    {
        DB::connection('system')->table('marketplace_settings')
            ->where('key', 'currency_symbol')
            ->delete();
    }
};
