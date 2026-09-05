<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cat_document_types')) {
            // Se conservan documentos históricos, pero se ocultan los tipos
            // transportista para impedir nuevas emisiones y selecciones.
            DB::table('cat_document_types')
                ->whereIn('id', ['31', '72'])
                ->update(['active' => 0]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cat_document_types')) {
            DB::table('cat_document_types')
                ->whereIn('id', ['31', '72'])
                ->update(['active' => 1]);
        }
    }
};
