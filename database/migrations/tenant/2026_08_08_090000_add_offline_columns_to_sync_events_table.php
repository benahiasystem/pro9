<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas para la bandeja: user_id (autor del evento, para reprocesar con el
 * mismo usuario) y xml_unsigned (el XML de la máquina en ventas — sin él un
 * reintento no puede repetir el test de contrato byte a byte).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('sync_events', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable()->after('machine_id');
            $table->longText('xml_unsigned')->nullable()->after('payload');
        });
    }

    public function down()
    {
        Schema::table('sync_events', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'xml_unsigned']);
        });
    }
};
