<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rollback de BD para tenants donde ya corrieron (y luego se revirtieron en git):
 * - USD activo por defecto
 * - Exonerado (20) activo por defecto
 * - POS comprobante por defecto = nota de venta
 *
 * Mantiene USD/exonerado inactivos y devuelve el POS a Factura por defecto.
 * La Boleta histórica nunca vuelve a habilitarse para nuevas emisiones.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('cat_currency_types')) {
            DB::connection('tenant')->table('cat_currency_types')
                ->where('id', 'USD')
                ->update(['active' => false]);
        }

        if (Schema::hasTable('cat_affectation_igv_types')) {
            DB::connection('tenant')->table('cat_affectation_igv_types')
                ->where('id', '20')
                ->update(['active' => false]);

            cache()->forget('affectation_igv_types');
        }

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
                'ALTER TABLE configurations MODIFY default_document_type_80 TINYINT(1) NOT NULL DEFAULT 0'
            );
        }

        if (Schema::hasColumn('configurations', 'default_document_type_03')
            && Schema::hasColumn('configurations', 'default_document_type_80')) {
            DB::connection('tenant')->table('configurations')->update([
                'default_document_type_03' => false,
                'default_document_type_80' => false,
            ]);
        }
    }

    public function down()
    {
        if (Schema::hasTable('cat_currency_types')) {
            DB::connection('tenant')->table('cat_currency_types')
                ->where('id', 'USD')
                ->update(['active' => true]);
        }

        if (Schema::hasTable('cat_affectation_igv_types')) {
            DB::connection('tenant')->table('cat_affectation_igv_types')
                ->where('id', '20')
                ->update(['active' => true]);

            cache()->forget('affectation_igv_types');
        }

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
};
