<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ítem de servicio "Penalidad", usado en las notas de débito con motivo 13.
     * Las penalidades contractuales son operaciones inafectas al IGV, por eso
     * se registra con afectación 30 (Inafecto - Operación Onerosa) y precio 0,
     * para que el monto se digite en cada nota de débito.
     */
    public function up()
    {
        // La afectación inafecta debe estar disponible en los selectores del tenant
        DB::table('cat_affectation_igv_types')
            ->where('id', '30')
            ->update(['active' => true]);

        $exists = DB::table('items')->where('internal_id', 'PENALIDAD')->exists();

        if (!$exists) {
            DB::table('items')->insert([
                'description'                      => 'Penalidad',
                'name'                             => 'Penalidad',
                'internal_id'                      => 'PENALIDAD',
                'item_type_id'                     => '02', // Servicio
                'unit_type_id'                     => 'ZZ', // Servicio
                'currency_type_id'                 => 'PEN',
                'sale_unit_price'                  => 0.00,
                'purchase_unit_price'              => 0.00,
                'sale_affectation_igv_type_id'     => '30', // Inafecto - Operación Onerosa
                'purchase_affectation_igv_type_id' => '30',
                'has_igv'                          => 0,
                'purchase_has_igv'                 => 0,
                'has_isc'                          => 0,
                'percentage_isc'                   => 0,
                'stock'                            => 0,
                'stock_min'                        => 0,
                'active'                           => 1,
                'is_set'                           => 0,
                'apply_store'                      => 0,
                'created_at'                       => now(),
                'updated_at'                       => now(),
            ]);
        }

        // Los catálogos se cachean por 1440 min; se limpia para que el ítem y la
        // afectación queden disponibles de inmediato
        cache()->forget('affectation_igv_types');
    }

    /**
     * Revierte la creación del ítem.
     *
     * No se desactiva la afectación 30: pudo haber estado activa antes de esta
     * migración o estar en uso por otros ítems del tenant.
     */
    public function down()
    {
        DB::table('items')->where('internal_id', 'PENALIDAD')->delete();
    }
};
