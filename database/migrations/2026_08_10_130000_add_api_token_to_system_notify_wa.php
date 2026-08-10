<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Token con el que un servicio externo se autentica contra la API publica de
 * envio de WhatsApp del superadmin (texto/media/pdf). Ver
 * App\Http\Controllers\System\Api\WhatsAppNotifyApiController.
 */
class AddApiTokenToSystemNotifyWa extends Migration
{
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('notify_wa_api_token', 64)->nullable()->after('notify_wa_enabled');
        });
    }

    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn('notify_wa_api_token');
        });
    }
}
