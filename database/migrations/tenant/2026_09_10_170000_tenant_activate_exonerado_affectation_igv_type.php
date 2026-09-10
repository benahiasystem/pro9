<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Activa "Exonerado - Operación Onerosa" (20) en el listado de afectación
 * por producto. Es habitual en librerías y región selva; el seed lo dejó
 * inactivo tras el cambio NRUS.
 */
return new class extends Migration
{
    public function up()
    {
        DB::connection('tenant')->table('cat_affectation_igv_types')
            ->where('id', '20')
            ->update(['active' => true]);

        cache()->forget('affectation_igv_types');
    }

    public function down()
    {
        DB::connection('tenant')->table('cat_affectation_igv_types')
            ->where('id', '20')
            ->update(['active' => false]);

        cache()->forget('affectation_igv_types');
    }
};
