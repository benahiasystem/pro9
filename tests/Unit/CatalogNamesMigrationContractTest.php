<?php

namespace Tests\Unit;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Services\SeriesCodeGenerator;
use Tests\TestCase;

// ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
class CatalogNamesMigrationContractTest extends TestCase
{
    /** @test */
    public function tenant_seed_uses_venezuelan_names_without_removing_historical_ids(): void
    {
        $documentTypes = $this->rowsById('cat_document_types');

        self::assertSame('FACTURA DE VENTA', $documentTypes['01']['description']);
        self::assertSame('NOTA DE CRÉDITO', $documentTypes['07']['description']);
        self::assertSame('NOTA DE DÉBITO', $documentTypes['08']['description']);
        self::assertSame('GUÍA DE DESPACHO REMITENTE', $documentTypes['09']['description']);
        self::assertSame('COMPROBANTE DE RETENCIÓN', $documentTypes['20']['description']);
        self::assertSame('GUÍA DE DESPACHO TRANSPORTISTA', $documentTypes['31']['description']);
        self::assertSame('COMPROBANTE DE PERCEPCIÓN', $documentTypes['40']['description']);

        $appModules = $this->rowsById('app_modules');
        self::assertSame('Factura de venta', $appModules[1]['description']);

        self::assertArrayHasKey('03', $documentTypes);
        self::assertSame(1, $documentTypes['03']['active']);
        self::assertSame(['01', '80'], DocumentType::SALE_DOCUMENT_TYPES);
        self::assertContains('03', DocumentType::HISTORICAL_SALE_DOCUMENT_TYPES);
        self::assertArrayNotHasKey('03', config('tables.tenant.document_types'));
    }

    /** @test */
    public function only_gravado_and_exento_are_selectable_affectations(): void
    {
        $affectations = $this->rowsById('cat_affectation_igv_types');
        $activeIds = array_map('strval', array_keys(array_filter($affectations, static function (array $row): bool {
            return (int) $row['active'] === 1;
        })));

        sort($activeIds);

        self::assertSame(['10', '20'], $activeIds);
        self::assertSame('Gravado', $affectations['10']['description']);
        self::assertSame('Exento', $affectations['20']['description']);
        self::assertSame(['10', '20'], config('venezuela.tax.selectable_affectation_ids'));
    }

    /** @test */
    public function peruvian_catalog_options_are_hidden_but_their_rows_are_preserved(): void
    {
        $expectedHidden = [
            'cat_related_tax_document_types' => ['03', '04', '05'],
            'cat_other_tax_concept_types' => ['2003', '3001'],
            'cat_transfer_reason_types' => ['18', '19'],
            'cat_related_documents_types' => ['03', '05'],
            'cat_perception_types' => ['02', '03'],
            'cat_legend_types' => ['2001', '2002', '2003', '2005', '2006', '2007', '2008', '2009', '2010'],
            'cat_payment_method_types' => ['007', '008', '009', '011', '012', '013', '106', '107', '108'],
        ];

        foreach ($expectedHidden as $table => $ids) {
            $rows = $this->rowsById($table);
            foreach ($ids as $id) {
                self::assertArrayHasKey($id, $rows, "Missing historical {$table}.{$id}");
                self::assertSame(0, (int) $rows[$id]['active'], "Visible legacy option {$table}.{$id}");
            }
        }

        $discounts = $this->rowsById('cat_charge_discount_types');
        self::assertSame(1, (int) $discounts['00']['active']);
        self::assertSame(1, (int) $discounts['01']['active']);
        self::assertStringContainsString('IVA', $discounts['00']['description']);
        self::assertStringContainsString('IVA', $discounts['01']['description']);
        foreach (['02', '03', '47', '48', '49', '50'] as $id) {
            self::assertSame(0, (int) $discounts[$id]['active']);
        }
    }

    /** @test */
    public function incremental_migration_updates_and_deactivates_without_deleting_catalog_rows(): void
    {
        $source = $this->source('database/migrations/tenant/2026_08_22_235959_configure_venezuela_catalog_names.php');

        self::assertStringContainsString('DOCUMENT_DESCRIPTIONS', $source);
        self::assertStringContainsString('HIDDEN_CATALOG_IDS', $source);
        self::assertStringContainsString("'cat_affectation_igv_types'", $source);
        self::assertStringContainsString("'cat_other_tax_concept_types'", $source);
        self::assertStringContainsString("where('value', 'invoice')", $source);
        self::assertStringContainsString("where('id', '10')", $source);
        self::assertStringContainsString("where('id', '20')", $source);
        self::assertSame(2, substr_count($source, "where('id', (string) \$id)"));
        self::assertStringContainsString("->update(['active' => false])", $source);
        self::assertStringNotContainsString('->delete(', $source);
        self::assertStringNotContainsString('::delete(', $source);
        self::assertSame(1, substr_count($source, '########## INICIO CAMBIO CATÁLOGOS DE NOMBRES'));
        self::assertSame(1, substr_count($source, '######### FIN CAMBIO CATÁLOGOS DE NOMBRES'));
    }

    /** @test */
    public function presentation_contract_uses_final_names_and_hides_ubl_panels_by_capability(): void
    {
        $invoiceType = collect(SeriesCodeGenerator::SERIES_TYPES)->firstWhere('document_type_id', '01');
        self::assertSame('FACTURA DE VENTA', $invoiceType['label']);
        self::assertFalse(config('venezuela.visible_fiscal_features.ubl_attributes'));

        $ublViews = [
            'resources/js/views/tenant/purchases/partials/item.vue',
            'resources/js/views/tenant/purchase-settlements/partials/item.vue',
            'modules/Purchase/Resources/assets/js/views/fixed_asset_purchases/partials/item.vue',
            'modules/Purchase/Resources/assets/js/views/purchase-orders/partials/item.vue',
            'modules/Sale/Resources/assets/js/views/contracts/partials/item.vue',
        ];

        foreach ($ublViews as $file) {
            $source = $this->source($file);
            self::assertStringContainsString('show_ubl_attributes', $source, $file);
            self::assertStringContainsString('v-if="show_ubl_attributes', $source, $file);
        }

        self::assertSame('Tipo %IVA', trans('app.fields.affectation_igv_type_id'));
    }

    private function rowsById(string $table): array
    {
        $seed = require base_path('database/seeders/data/tenant_initial_data.php');
        $rows = $seed['tables'][$table]['rows'];

        return array_column($rows, null, 'id');
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));
        self::assertNotFalse($source, $path);

        return $source;
    }
}
// ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
