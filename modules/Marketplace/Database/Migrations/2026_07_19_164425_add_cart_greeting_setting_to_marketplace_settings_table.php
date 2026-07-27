<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Saludo con el que abre el mensaje del pedido (carrito).
     *
     * El gemelo multi-producto de `whatsapp_greeting`: aquel encabeza el mensaje
     * de un solo producto, este el del carrito, donde debajo va la lista de
     * ítems con sus cantidades. Se deja editable por lo mismo que el otro: toda
     * la copia que llega al WhatsApp de la tienda vive en Ajustes.
     */
    public function up()
    {
        DB::connection('system')->table('marketplace_settings')->updateOrInsert(
            ['key' => 'whatsapp_cart_greeting'],
            ['value' => 'Hola, quiero hacer este pedido que armé en el marketplace:', 'type' => 'string']
        );
    }

    public function down()
    {
        DB::connection('system')->table('marketplace_settings')
            ->where('key', 'whatsapp_cart_greeting')
            ->delete();
    }
};
