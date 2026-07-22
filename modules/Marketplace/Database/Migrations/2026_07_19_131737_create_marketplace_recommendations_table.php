<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // El espejo positivo de marketplace_reports: igual de mínima, sin motivo
        // ni texto libre. Un pulgar es un pulgar.
        //
        // Existe como tabla y no como simple contador porque hace falta poder
        // responder «¿ya recomendé esto?» en cada carga de la pantalla. Sin esa
        // respuesta el pulgar no puede reflejar la realidad, y una interfaz que
        // finge que el clic contó es peor que no tener la función.
        Schema::connection('system')->create('marketplace_recommendations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('item_id')->index();

            // Identidad del visitante: el uuid de la cookie firmada que emite
            // EnsureMarketplaceVisitor. No es la IP a propósito — en el wifi de
            // un edificio todos los vecinos comparten IP, y con ella el pulgar
            // mentiría a la mayoría.
            $table->char('visitor_id', 36);

            // Se conserva solo como rastro para auditar un ranking sospechoso.
            // No participa en ninguna regla.
            $table->string('ip', 45)->nullable();

            $table->timestamp('created_at')->nullable();

            // Un visitante recomienda cada producto una vez. Sin caducidad: el
            // pulgar es reversible, así que no hace falta ningún cupo diario
            // que caduque y vuelva a abrirse.
            $table->unique(['visitor_id', 'item_id'], 'mkt_reco_once_per_visitor');

            // El ranking de la tienda cuenta visitantes DISTINTOS, así que esta
            // es la consulta caliente.
            $table->index(['store_id', 'visitor_id'], 'mkt_reco_store_visitor');

            $table->foreign('store_id')
                ->references('id')->on('marketplace_stores')
                ->onDelete('cascade');

            $table->foreign('item_id')
                ->references('id')->on('marketplace_items')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::connection('system')->dropIfExists('marketplace_recommendations');
    }
};
