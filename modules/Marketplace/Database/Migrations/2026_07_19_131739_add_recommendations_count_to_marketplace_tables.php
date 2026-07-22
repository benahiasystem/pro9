<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // El del producto se muestra en el admin y alimenta el recuento del de
        // su tienda, pero NO se ordena por él (el feed ordena por el ranking de
        // la tienda, no del producto), así que no lleva índice propio.
        Schema::connection('system')->table('marketplace_items', function (Blueprint $table) {
            $table->unsignedInteger('recommendations_count')->default(0)->after('reports_count');
        });

        // El de la tienda NO es la suma de los de sus productos: son los
        // visitantes DISTINTOS que han recomendado algo suyo. Así un solo
        // entusiasta que recorra el catálogo entero sigue contando como uno.
        // Lo recalcula CatalogCounters. El orden «más recomendados» se resuelve
        // con un ORDER BY sobre esta columna indexada.
        Schema::connection('system')->table('marketplace_stores', function (Blueprint $table) {
            $table->unsignedInteger('recommendations_count')->default(0)->after('reports_count');

            $table->index(['status', 'recommendations_count'], 'mkt_stores_reco_order');
        });
    }

    public function down()
    {
        Schema::connection('system')->table('marketplace_items', function (Blueprint $table) {
            $table->dropColumn('recommendations_count');
        });

        Schema::connection('system')->table('marketplace_stores', function (Blueprint $table) {
            $table->dropIndex('mkt_stores_reco_order');
            $table->dropColumn('recommendations_count');
        });
    }
};
