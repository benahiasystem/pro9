<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    private const DOCUMENT_DESCRIPTIONS = [
        '01' => 'FACTURA DE VENTA',
        '07' => 'NOTA DE CRÉDITO',
        '08' => 'NOTA DE DÉBITO',
        '09' => 'GUÍA DE DESPACHO REMITENTE',
        '20' => 'COMPROBANTE DE RETENCIÓN',
        '31' => 'GUÍA DE DESPACHO TRANSPORTISTA',
        '40' => 'COMPROBANTE DE PERCEPCIÓN',
    ];

    private const HIDDEN_CATALOG_IDS = [
        'cat_affectation_igv_types' => [
            '11', '12', '13', '14', '15', '16', '17',
            '21', '30', '31', '32', '33', '34', '35', '36', '37', '40',
        ],
        'cat_related_tax_document_types' => ['03', '04', '05'],
        'cat_other_tax_concept_types' => ['2003', '3001'],
        'cat_transfer_reason_types' => ['18', '19'],
        'cat_related_documents_types' => ['03', '05'],
        'cat_perception_types' => ['02', '03'],
        'cat_operation_types' => [
            '0101_itinerant', '0112', '0113',
            '0201', '0202', '0203', '0204', '0205', '0206', '0207', '0208',
            '0301', '0302', '0303', '0401', '0501',
            '1001', '1002', '1003', '1004', '2001',
        ],
        'cat_legend_types' => [
            '2001', '2002', '2003', '2005', '2006',
            '2007', '2008', '2009', '2010',
        ],
        'cat_charge_discount_types' => [
            '02', '03', '04', '05', '06', '45',
            '47', '48', '49', '50', '51', '52', '53',
        ],
        'cat_attribute_types' => [
            '3001', '3002', '3003', '3004', '3005', '3006',
            '4040', '4041', '4042', '4043', '4044', '4045', '4046', '4047', '4048', '4049',
            '4060', '4061', '4062', '4063', '4064',
            '5000', '5001', '5002', '5003',
            '6000', '6001', '6002', '6003', '6004',
            '7000', '7001', '7002', '7003', '7004', '7005',
            '7006', '7007', '7008', '7009', '7010', '7011',
        ],
        'cat_payment_method_types' => [
            '007', '008', '009', '011', '012', '013', '106', '107', '108',
        ],
    ];

    private const PREVIOUSLY_ACTIVE_IDS = [
        'cat_affectation_igv_types' => ['30'],
        'cat_related_tax_document_types' => ['03', '04', '05'],
        'cat_other_tax_concept_types' => ['2003', '3001'],
        'cat_transfer_reason_types' => ['18'],
        'cat_related_documents_types' => ['03', '05'],
        'cat_perception_types' => ['02', '03'],
        'cat_legend_types' => ['2001', '2002', '2003', '2005', '2006', '2007', '2008', '2009', '2010'],
        'cat_charge_discount_types' => ['02', '03', '47', '48', '49', '50'],
        'cat_payment_method_types' => ['007', '008', '009', '011', '012', '013', '106', '107', '108'],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::DOCUMENT_DESCRIPTIONS as $id => $description) {
                DB::table('cat_document_types')->where('id', (string) $id)->update([
                    'description' => $description,
                ]);
            }

            if (Schema::hasTable('app_modules')) {
                DB::table('app_modules')->where('value', 'invoice')->update([
                    'description' => 'Factura de venta',
                ]);
            }

            DB::table('cat_affectation_igv_types')->where('id', '10')->update([
                'active' => true,
                'description' => 'Gravado',
            ]);
            DB::table('cat_affectation_igv_types')->where('id', '20')->update([
                'active' => true,
                'description' => 'Exento',
            ]);

            DB::table('cat_charge_discount_types')->where('id', '00')->update([
                'active' => true,
                'description' => 'Descuentos que afectan la base imponible del IVA',
            ]);
            DB::table('cat_charge_discount_types')->where('id', '01')->update([
                'active' => true,
                'description' => 'Descuentos que no afectan la base imponible del IVA',
            ]);

            foreach (self::HIDDEN_CATALOG_IDS as $table => $ids) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->whereIn('id', $ids)->update(['active' => false]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $previousDocumentDescriptions = [
                '01' => 'FACTURA ELECTRÓNICA',
                '07' => 'NOTA DE CRÉDITO',
                '08' => 'NOTA DE DÉBITO',
                '09' => 'GUIA DE REMISIÓN REMITENTE',
                '20' => 'COMPROBANTE DE RETENCIÓN ELECTRÓNICA',
                '31' => 'GUÍA DE REMISIÓN TRANSPORTISTA',
                '40' => 'COMPROBANTE DE PERCEPCIÓN ELECTRÓNICA',
            ];

            foreach ($previousDocumentDescriptions as $id => $description) {
                DB::table('cat_document_types')
                    ->where('id', (string) $id)
                    ->where('description', self::DOCUMENT_DESCRIPTIONS[$id])
                    ->update(['description' => $description]);
            }

            if (Schema::hasTable('app_modules')) {
                DB::table('app_modules')
                    ->where('value', 'invoice')
                    ->where('description', 'Factura de venta')
                    ->update(['description' => 'Factura electrónica']);
            }

            foreach (self::PREVIOUSLY_ACTIVE_IDS as $table => $ids) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->whereIn('id', $ids)->update(['active' => true]);
                }
            }
        });
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
};
