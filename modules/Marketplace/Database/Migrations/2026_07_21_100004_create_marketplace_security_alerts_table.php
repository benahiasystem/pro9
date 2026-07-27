<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alertas de seguridad para el admin (plan-seguridad-marketplace.md, A1.3).
     *
     * Nada de esto bloquea una publicación por sí solo: son avisos de cosas que
     * NO deben pasar en silencio.
     *
     * - whatsapp_changed: una tienda aprobada cambió su número de pedidos.
     * - feed_sweep: una IP recorrió toda la paginación del feed en segundos
     *   (patrón de scraper, no de vecino).
     * - secret_reset: el admin reseteó la credencial de una tienda; el
     *   siguiente sync vuelve a ser trust-on-first-use.
     */
    public function up()
    {
        Schema::connection('system')->create('marketplace_security_alerts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('type', 40)->index();
            $table->string('message', 500);
            $table->json('meta')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::connection('system')->dropIfExists('marketplace_security_alerts');
    }
};
