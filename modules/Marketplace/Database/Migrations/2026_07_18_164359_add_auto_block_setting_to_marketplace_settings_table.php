<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Umbral de bloqueo automático por denuncias.
     *
     * 0 = desactivado. Es la válvula de escape: si el umbral resulta molesto o
     * alguien empieza a abusar de las denuncias, se apaga desde Ajustes sin
     * desplegar nada.
     */
    public function up()
    {
        DB::connection('system')->table('marketplace_settings')->updateOrInsert(
            ['key' => 'auto_block_reports'],
            ['value' => '100', 'type' => 'int']
        );
    }

    public function down()
    {
        DB::connection('system')->table('marketplace_settings')
            ->where('key', 'auto_block_reports')
            ->delete();
    }
};
