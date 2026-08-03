<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('configurations')) {
            return;
        }

        if (!Schema::hasColumn('configurations', 'default_document_type_03')
            || !Schema::hasColumn('configurations', 'default_document_type_80')) {
            return;
        }

        DB::table('configurations')->update([
            'default_document_type_03' => false,
            'default_document_type_80' => true,
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('configurations')) {
            return;
        }

        if (!Schema::hasColumn('configurations', 'default_document_type_03')
            || !Schema::hasColumn('configurations', 'default_document_type_80')) {
            return;
        }

        DB::table('configurations')->update([
            'default_document_type_03' => true,
            'default_document_type_80' => false,
        ]);
    }
};
