<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columna para la bandeja: user_id conserva el autor del evento para
 * reprocesarlo con el mismo usuario.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('sync_events', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable()->after('machine_id');
        });
    }

    public function down()
    {
        Schema::table('sync_events', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
