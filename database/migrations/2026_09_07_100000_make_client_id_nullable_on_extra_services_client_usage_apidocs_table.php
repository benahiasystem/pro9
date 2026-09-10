<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite registrar el consumo de apidocs del propio admin / reseller.
 *
 * Las consultas hechas desde el dominio del sistema (no desde un tenant) no
 * tienen cliente asociado, asi que se guardan con client_id NULL. Hasta ahora
 * simplemente no se registraban, y por eso el desglose "Uso por cliente" nunca
 * cuadraba con el total mensual que reporta el proveedor: esas consultas si
 * consumen cuota del reseller.
 *
 * Se usa un ALTER directo en lugar de ->change(): doctrine/dbal puede recrear
 * la columna y perder la foreign key en el proceso. Un MODIFY la conserva, y
 * la FK sigue siendo valida porque MySQL no valida las filas con NULL.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('extra_services_client_usage_apidocs')) {
            return;
        }

        DB::statement('ALTER TABLE `extra_services_client_usage_apidocs` MODIFY `client_id` INT UNSIGNED NULL');
    }

    public function down()
    {
        if (! Schema::hasTable('extra_services_client_usage_apidocs')) {
            return;
        }

        // Las filas del sistema no tienen cliente al que volver, se descartan
        // para poder restaurar el NOT NULL.
        DB::table('extra_services_client_usage_apidocs')->whereNull('client_id')->delete();

        DB::statement('ALTER TABLE `extra_services_client_usage_apidocs` MODIFY `client_id` INT UNSIGNED NOT NULL');
    }
};
