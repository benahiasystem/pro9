<?php

namespace Tests\Unit;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Services\SeriesCodeGenerator;
use Tests\TestCase;

// ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
class CatalogNamesMigrationContractTest extends TestCase
{
    /** @test */
    public function tenant_seed_uses_the_reduced_venezuelan_document_catalog(): void
    {
        $documentTypes = $this->rowsById('cat_document_types');

        self::assertSame('FACTURA DE VENTA', $documentTypes['01']['description']);
        self::assertSame('NOTA DE CRÉDITO', $documentTypes['07']['description']);
        self::assertSame('NOTA DE DÉBITO', $documentTypes['08']['description']);
        self::assertSame('ORDEN DE ENTREGA', $documentTypes['09']['description']);
        self::assertSame('COMPROBANTE DE RETENCIÓN', $documentTypes['20']['description']);
        self::assertSame('Nota de Transferencia Almacén', $documentTypes['U4']['description']);

        $appModules = $this->rowsById('app_modules');
        self::assertSame('Factura de venta', $appModules[1]['description']);

        self::assertSame(
            ['01', '07', '08', '09', '20', '80', 'NE76', 'U2', 'U3', 'U4'],
            array_map('strval', array_keys($documentTypes))
        );
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
    public function removed_peruvian_catalogs_are_absent_from_initial_tenant_data(): void
    {
        $seed = require base_path('database/seeders/data/tenant_initial_data.php');
        foreach ([
            'cat_other_tax_concept_types',
            'cat_perception_types',
            'cat_related_documents_types',
            'cat_related_tax_document_types',
            'cat_summary_status_types',
            'cat_system_isc_types',
            'pse_providers',
            'cat_detraction_types', 'cat_payment_method_types',
            'departments',
            'provinces',
            'districts',
        ] as $table) {
            self::assertArrayNotHasKey($table, $seed['tables'], $table);
        }

        $discounts = $this->rowsById('cat_charge_discount_types');
        self::assertSame(1, (int) $discounts['00']['active']);
        self::assertSame(1, (int) $discounts['01']['active']);
        self::assertStringContainsString('IVA', $discounts['00']['description']);
        self::assertStringContainsString('IVA', $discounts['01']['description']);
        self::assertSame(['00', '01', '02', '03', '46', '62'], array_map('strval', array_keys($discounts)));
        self::assertSame(['1000'], array_map('strval', array_keys($this->rowsById('cat_legend_types'))));
    }

    /** @test */
    public function consolidated_schema_does_not_create_removed_catalog_tables(): void
    {
        foreach ([
            'cat_other_tax_concept_types', 'cat_perception_types',
            'cat_related_documents_types', 'cat_related_tax_document_types',
            'cat_summary_status_types', 'cat_system_isc_types',
            'pse_providers', 'cat_detraction_types', 'cat_payment_method_types',
        ] as $table) {
            self::assertSame([], glob(database_path("migrations/tenant/*_create_{$table}_table.php")) ?: [], $table);
        }

        $foreignKeys = $this->source('database/migrations/tenant/2026_08_17_000328_add_tenant_foreign_keys.php');
        self::assertStringNotContainsString('REFERENCES `cat_system_isc_types`', $foreignKeys);
        self::assertStringNotContainsString('REFERENCES `cat_perception_types`', $foreignKeys);
        self::assertStringNotContainsString('REFERENCES `cat_summary_status_types`', $foreignKeys);
        self::assertStringNotContainsString('REFERENCES `pse_providers`', $foreignKeys);
        self::assertStringNotContainsString('ALTER TABLE `cat_detraction_types`', $foreignKeys);
    }

    /** @test */
    public function item_models_do_not_eager_load_the_removed_isc_catalog(): void
    {
        foreach ([
            'app/Models/Tenant/DocumentItem.php',
            'app/Models/Tenant/PurchaseItem.php',
            'app/Models/Tenant/QuotationItem.php',
            'app/Models/Tenant/SaleNoteItem.php',
            'modules/Order/Models/OrderNoteItem.php',
            'modules/Purchase/Models/FixedAssetPurchaseItem.php',
            'modules/Purchase/Models/PurchaseOrderItem.php',
            'modules/Sale/Models/ContractItem.php',
            'modules/Sale/Models/SaleOpportunityItem.php',
        ] as $file) {
            $source = $this->source($file);
            self::assertStringNotContainsString('system_isc_type', $this->eagerLoads($source), $file);
            self::assertStringContainsString('function system_isc_type()', $source, $file);
        }
    }

    /** @test */
    public function hidden_global_discount_ids_remain_available_to_configured_sales_flows(): void
    {
        $model = $this->source('app/Models/Tenant/Catalogs/ChargeDiscountType.php');

        self::assertStringContainsString("whereIn('id', ['02', '03'])", $model);
        self::assertStringNotContainsString("whereIn('id', ['02', '03'])->whereActive()", $model);

        foreach ([
            'app/Http/Controllers/Tenant/DocumentController.php',
            'app/Http/Controllers/Tenant/QuotationController.php',
            'app/Http/Controllers/Tenant/PurchaseController.php',
            'app/Http/Controllers/Tenant/PosController.php',
            'app/Http/Controllers/Tenant/ConfigurationController.php',
            'modules/Restaurant/Models/ConfigurationController.php',
        ] as $file) {
            self::assertStringContainsString(
                'ChargeDiscountType::getGlobalDiscounts()',
                $this->source($file),
                $file
            );
        }
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

    private function eagerLoads(string $source): string
    {
        preg_match('/protected \\$with\\s*=\\s*\\[(.*?)\\];/s', $source, $matches);
        self::assertArrayHasKey(1, $matches);

        return $matches[1];
    }
}
// ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
