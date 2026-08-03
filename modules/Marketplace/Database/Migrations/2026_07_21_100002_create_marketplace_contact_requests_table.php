<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El rastro de la puerta de contacto (plan-seguridad-marketplace.md, A1.2).
     *
     * Cada enlace de WhatsApp generado deja una fila: quién lo pidió (nombre y
     * WhatsApp que él mismo declaró, más su cookie y su IP), para qué tienda y
     * con qué ítems. Es la evidencia que hoy no existe: si una tienda denuncia
     * extorsión, el admin exporta este registro para la PNP/Fiscalía.
     *
     * El código público es 'REQ-' + id con padding; no se guarda por separado.
     * La purga la hace marketplace:purge según contact_retention_days.
     */
    public function up()
    {
        Schema::connection('system')->create('marketplace_contact_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('store_id')->index();
            // Solo en type=product: el producto puntual que originó el contacto.
            $table->unsignedBigInteger('item_id')->nullable();

            $table->enum('type', ['store', 'product', 'cart']);
            // Snapshot de los ítems del pedido (type=cart): [{id, name, qty}].
            $table->json('items')->nullable();

            // Lo que el comprador declaró en la puerta.
            $table->string('buyer_name', 80);
            $table->string('buyer_whatsapp', 20);

            // El rastro técnico: la cookie firmada del visitante y su IP.
            $table->char('visitor_id', 36)->index();
            $table->string('ip', 45)->index();

            $table->timestamp('created_at')->nullable();

            $table->index(['store_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::connection('system')->dropIfExists('marketplace_contact_requests');
    }
};
