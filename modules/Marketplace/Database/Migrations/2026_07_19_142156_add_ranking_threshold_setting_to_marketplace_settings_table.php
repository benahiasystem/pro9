<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cuántos vecinos distintos hacen falta para que el aval de una tienda
     * cuente.
     *
     * Por debajo del umbral la tienda no muestra badge y **no gana posición**:
     * se ordena junto a las que aún no tienen ninguna. Es lo que evita que una
     * tienda con una sola recomendación —la de un amigo, o la suya— adelante a
     * las demás. La preferencia solo aparece cuando el número ya significa
     * algo.
     *
     * 0 = sin umbral (se muestra y ordena desde la primera). Se sube conforme
     * crece la comunidad, sin desplegar nada.
     */
    public function up()
    {
        DB::connection('system')->table('marketplace_settings')->updateOrInsert(
            ['key' => 'ranking_threshold'],
            ['value' => '10', 'type' => 'int']
        );
    }

    public function down()
    {
        DB::connection('system')->table('marketplace_settings')
            ->where('key', 'ranking_threshold')
            ->delete();
    }
};
