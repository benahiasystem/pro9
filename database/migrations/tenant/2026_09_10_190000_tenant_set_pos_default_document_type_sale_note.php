<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POS: comprobante por defecto = Nota de venta (80), no Boleta (03).
 * Al crear empresa el insert de configurations hereda el DEFAULT de columna;
 * por eso también se ajustan los defaults del schema.
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('configurations')) {
            return;
        }

        if (Schema::hasColumn('configurations', 'default_document_type_03')) {
            DB::connection('tenant')->statement(
                'ALTER TABLE configurations MODIFY default_document_type_03 TINYINT(1) NOT NULL DEFAULT 0'
            );
        }

        if (Schema::hasColumn('configurations', 'default_document_type_80')) {
            DB::connection('tenant')->statement(
                'ALTER TABLE configurations MODIFY default_document_type_80 TINYINT(1) NOT NULL DEFAULT 1'
            );
        }

        if (Schema::hasColumn('configurations', 'default_document_type_03')
            && Schema::hasColumn('configurations', 'default_document_type_80')) {
            DB::connection('tenant')->table('configurations')->update([
                'default_document_type_03' => false,
                'default_document_type_80' => true,
            ]);
        }
    }

    public function down()
    {
        if (!Schema::hasTable('configurations')) {
            return;
        }

        if (Schema::hasColumn('configurations', 'default_document_type_03')) {
            DB::connection('tenant')->statement(
                'ALTER TABLE configurations MODIFY default_document_type_03 TINYINT(1) NOT NULL DEFAULT 1'
            );
        }

        if (Schema::hasColumn('configurations', 'default_document_type_80')) {
            DB::connection('tenant')->statement(
                'ALTER TABLE configurations MODIFY default_document_type_80 TINYINT(1) NOT NULL DEFAULT 0'
            );
        }
    }
};
