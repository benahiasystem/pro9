<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Numero de WhatsApp conectado al superadmin (reseller), usado para enviar
 * notificaciones salientes (recordatorios de pago a los tenants) via Evolution.
 * No es un bot conversacional: solo envia, no procesa mensajes entrantes.
 *
 * Ver App\Http\Controllers\System\WhatsAppNotifyController.
 */
class AddWhatsappNotifyToSystemConfigurations extends Migration
{
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('notify_wa_instance')->nullable()->after('qr_api_msg');
            $table->string('notify_wa_connection_state', 20)->default('disconnected')->after('notify_wa_instance');
            $table->string('notify_wa_connected_phone')->nullable()->after('notify_wa_connection_state');
            $table->string('notify_wa_profile_name')->nullable()->after('notify_wa_connected_phone');
            $table->timestamp('notify_wa_connected_at')->nullable()->after('notify_wa_profile_name');
            $table->boolean('notify_wa_enabled')->default(false)->after('notify_wa_connected_at');
        });
    }

    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn([
                'notify_wa_instance',
                'notify_wa_connection_state',
                'notify_wa_connected_phone',
                'notify_wa_profile_name',
                'notify_wa_connected_at',
                'notify_wa_enabled',
            ]);
        });
    }
}
