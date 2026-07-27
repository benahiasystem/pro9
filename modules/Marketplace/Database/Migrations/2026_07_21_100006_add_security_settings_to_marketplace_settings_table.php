<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ajustes de la capa de seguridad (plan-seguridad-marketplace.md, A1.4).
     *
     * - contact_limit_per_hour: contactos que un mismo visitante (cookie+IP)
     *   puede generar por hora. Un vecino real pide 2–3 por visita; un scraper
     *   pide 200. El front muestra el aviso a pantalla completa al llegar a la
     *   mitad de este límite.
     * - contact_retention_days: cuánto vive el registro de contactos antes de
     *   que marketplace:purge lo borre (Ley 29733: minimizar retención).
     * - arco_email: canal ARCO visible en el banner de datos personales. Vacío
     *   = el enlace no se muestra.
     */
    public function up()
    {
        $rows = [
            ['key' => 'contact_limit_per_hour', 'value' => '10', 'type' => 'int'],
            ['key' => 'contact_retention_days', 'value' => '365', 'type' => 'int'],
            ['key' => 'arco_email', 'value' => '', 'type' => 'string'],
        ];

        foreach ($rows as $row) {
            DB::connection('system')->table('marketplace_settings')->updateOrInsert(
                ['key' => $row['key']],
                ['value' => $row['value'], 'type' => $row['type']]
            );
        }
    }

    public function down()
    {
        DB::connection('system')->table('marketplace_settings')
            ->whereIn('key', ['contact_limit_per_hour', 'contact_retention_days', 'arco_email'])
            ->delete();
    }
};
