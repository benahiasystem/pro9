<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El titular de la portada pasa a ser editable desde Ajustes.
     *
     * Va en dos claves y no en una porque la segunda mitad se pinta en rosa y
     * cursiva: separarlas evita tener que meter HTML en un setting.
     */
    private const KEYS = [
        'hero_title' => 'Lo que venden tus vecinos,',
        'hero_highlight' => 'a un WhatsApp.',
    ];

    public function up()
    {
        foreach (self::KEYS as $key => $value) {
            DB::connection('system')->table('marketplace_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'type' => 'string']
            );
        }
    }

    public function down()
    {
        DB::connection('system')->table('marketplace_settings')
            ->whereIn('key', array_keys(self::KEYS))
            ->delete();
    }
};
