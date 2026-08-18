<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Programacion de la limpieza automatica de archivos por empresa.
 *
 * El superadmin define, para cada tenant, que carpetas se vacian y cada cuanto.
 * Un comando recorre las configuraciones activas y borra los archivos de
 * storage/app/tenancy/tenants/{uuid}/{paquete}.
 *
 * Ver App\Traits\StorageManagementTrait y App\Http\Controllers\System\StorageManagementController.
 */
class CreateStorageCleanupConfigurationsTable extends Migration
{
    public function up()
    {
        Schema::create('storage_cleanup_configurations', function (Blueprint $table) {

            $table->bigIncrements('id');

            // una sola configuracion por empresa: el detalle de que se limpia
            // vive en la columna packages, no en filas separadas
            $table->unsignedBigInteger('website_id')->unique();

            // carpetas a vaciar dentro del tenant, con los nombres de
            // StorageManagementController::PACKAGE_DELETE (pdf, sale_note, ...)
            $table->json('packages');

            $table->string('frequency', 10)->comment('daily, weekly o monthly');

            // solo aplica a weekly, 0 = domingo hasta 6 = sabado
            $table->unsignedTinyInteger('day_of_week')->nullable();

            // solo aplica a monthly. Un valor mayor a los dias del mes debe
            // resolverlo el comando, febrero no tiene 31
            $table->unsignedTinyInteger('day_of_month')->nullable();

            // hora de ejecucion en la zona horaria del scheduler (America/Lima)
            $table->time('time');

            $table->boolean('active')->default(true);

            // el scheduler corre cada minuto, esta marca evita repetir la
            // limpieza dentro de la misma ventana
            $table->timestamp('last_run_at')->nullable();

            $table->timestamps();

            // si se elimina la empresa su programacion deja de tener sentido
            $table->foreign('website_id')
                ->references('id')
                ->on('websites')
                ->onDelete('cascade');

            // el comando busca por estas dos columnas en cada corrida
            $table->index(['active', 'frequency']);

        });
    }

    public function down()
    {
        Schema::dropIfExists('storage_cleanup_configurations');
    }
}
