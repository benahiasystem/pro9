<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // ########## INICIO CAMBIO AFECTACIÓN IVA
    private const IVA_TYPES = [
        '10' => 'Gravado',
        '20' => 'Exento',
    ];

    public function up(): void
    {
        foreach (self::IVA_TYPES as $id => $description) {
            DB::table('cat_affectation_igv_types')->updateOrInsert(
                ['id' => $id],
                [
                    'active' => true,
                    'exportation' => false,
                    'free' => false,
                    'description' => $description,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('cat_affectation_igv_types')
            ->where('id', '10')
            ->where('description', 'Gravado')
            ->update(['description' => 'Gravado - Operación Onerosa']);

        DB::table('cat_affectation_igv_types')
            ->where('id', '20')
            ->where('description', 'Exento')
            ->update([
                'active' => false,
                'description' => 'Exonerado - Operación Onerosa',
            ]);
    }
    // ######### FIN CAMBIO AFECTACIÓN IVA
};
