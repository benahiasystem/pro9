<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ########### INICIO CONTRATO FLUJO DE PRODUCTOS
    private const WAREHOUSE_DOCUMENT_TYPES = [
        'U2' => 'NOTA DE INGRESO ALMACÉN',
        'U3' => 'NOTA DE SALIDA ALMACÉN',
    ];
    // ########### FIN CONTRATO FLUJO DE PRODUCTOS

    public function up(): void
    {
        if (!Schema::hasTable('cat_document_types')) {
            return;
        }

        foreach (self::WAREHOUSE_DOCUMENT_TYPES as $id => $description) {
            DB::table('cat_document_types')
                ->whereRaw('BINARY `id` = ?', [$id])
                ->update(['description' => $description]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('cat_document_types')) {
            return;
        }

        $guideDescriptions = [
            'U2' => 'Guía de Ingreso Almacén',
            'U3' => 'Guía de Salida Almacén',
        ];

        foreach ($guideDescriptions as $id => $description) {
            DB::table('cat_document_types')
                ->whereRaw('BINARY `id` = ?', [$id])
                ->update(['description' => $description]);
        }
    }
};
