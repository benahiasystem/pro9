<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Proveedor congelado de la conexion de notificaciones del superadmin
 * (numero conectado por QR), igual que evolution_provider/qr_api_provider
 * en los canales de tenant: NULL equivale a 'evolution' para conexiones
 * creadas antes de existir WAHA.
 */
class AddProviderToSystemNotifyWa extends Migration
{
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('notify_wa_provider', 20)->nullable()->after('notify_wa_api_token');
            $table->string('notify_wa_waha_server_key')->nullable()->after('notify_wa_provider');
        });
    }

    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn([
                'notify_wa_provider',
                'notify_wa_waha_server_key',
            ]);
        });
    }
}
