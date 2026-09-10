<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bandeja de descargas del central.
 *
 * Contenedor generico: no sabe nada de backups. Cualquier modulo del central que
 * genere un archivo pesado en cola registra aca su avance, y si necesita guardar
 * datos propios lo hace en su propia tabla de detalle apuntando a esta.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('download_tray', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('module');
            $table->string('format');
            $table->string('type')->nullable();
            $table->string('path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('disk')->default('local');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status')->default('PENDING');
            $table->text('error_message')->nullable();
            $table->datetime('date_init')->nullable();
            $table->datetime('date_end')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->text('payload_request')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['module', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('download_tray');
    }
};
