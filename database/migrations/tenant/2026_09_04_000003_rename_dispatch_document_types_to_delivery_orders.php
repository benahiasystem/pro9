<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DELIVERY_ORDER_TYPES = [
        '09' => 'ORDEN DE ENTREGA',
        '31' => 'ORDEN DE ENTREGA DEL TRANSPORTISTA',
        '71' => 'Orden de entrega complementaria',
        '72' => 'Orden de entrega del transportista complementaria',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('cat_document_types')) {
            return;
        }

        foreach (self::DELIVERY_ORDER_TYPES as $id => $description) {
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

        $dispatchDescriptions = [
            '09' => 'DESPACHO DEL REMITENTE',
            '31' => 'DESPACHO DEL TRANSPORTISTA',
            '71' => 'Despacho complementario del remitente',
            '72' => 'Despacho complementario del transportista',
        ];

        foreach ($dispatchDescriptions as $id => $description) {
            DB::table('cat_document_types')
                ->whereRaw('BINARY `id` = ?', [$id])
                ->update(['description' => $description]);
        }
    }
};
