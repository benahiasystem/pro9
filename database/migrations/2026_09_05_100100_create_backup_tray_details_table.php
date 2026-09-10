<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle propio del modulo de backup, colgado de la bandeja generica.
 *
 * La FK con cascade hace que borrar la fila de la bandeja se lleve el detalle,
 * asi la limpieza de backups vencidos no necesita saber de esta tabla.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('backup_tray_details', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('download_tray_id');
            $table->string('scope');
            $table->unsignedInteger('hostname_id')->nullable();
            $table->string('client_name')->nullable();
            $table->string('database')->nullable();
            $table->string('batch_id')->nullable();
            $table->boolean('includes_files')->default(true);
            $table->timestamps();

            $table->foreign('download_tray_id')
                  ->references('id')->on('download_tray')
                  ->onDelete('cascade');

            $table->index('batch_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('backup_tray_details');
    }
};
