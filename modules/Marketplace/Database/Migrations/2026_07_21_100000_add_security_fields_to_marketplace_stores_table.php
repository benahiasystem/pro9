<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos de seguridad de la tienda (plan-seguridad-marketplace.md, A1.1).
     *
     * - show_address: opt-in de la dirección exacta. Default false: la dirección
     *   es el dato que convierte una amenaza telefónica en una presencial, así
     *   que solo se publica si la tienda lo decide a propósito.
     * - address_zone: la «zona referencial» que se muestra en su lugar
     *   («Bloque 7», «Portería principal»). La escribe la tienda en la app.
     * - secret_hash: hash SHA-256 de la credencial por tienda (TOFU). El
     *   secreto en claro solo viaja una vez, en la respuesta del primer sync;
     *   aquí queda solo su hash. Null = tienda de antes de esta migración, que
     *   adopta secreto en su siguiente sync.
     * - hidden_at: «Ocultar mi tienda» desde la app. Con fecha, la tienda no es
     *   pública aunque siga approved; el siguiente sync la republica.
     */
    public function up()
    {
        Schema::connection('system')->table('marketplace_stores', function (Blueprint $table) {
            $table->boolean('show_address')->default(false)->after('address');
            $table->string('address_zone', 120)->nullable()->after('show_address');
            $table->char('secret_hash', 64)->nullable()->after('app_version');
            $table->timestamp('hidden_at')->nullable()->after('disabled_at');
        });
    }

    public function down()
    {
        Schema::connection('system')->table('marketplace_stores', function (Blueprint $table) {
            $table->dropColumn(['show_address', 'address_zone', 'secret_hash', 'hidden_at']);
        });
    }
};
