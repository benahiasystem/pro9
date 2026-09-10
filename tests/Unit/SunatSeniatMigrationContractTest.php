<?php

namespace Tests\Unit;

use Tests\TestCase;

// ########## INICIO CAMBIO SUNAT A SENIAT
class SunatSeniatMigrationContractTest extends TestCase
{
    /** @test */
    public function fiscal_item_code_is_hidden_but_its_data_contract_remains_compatible(): void
    {
        $forms = [
            'modules/Ecommerce/Resources/assets/js/views/item_sets/form.vue',
            'modules/Item/Resources/assets/js/views/items/item-detail.vue',
            'modules/Production/Resources/assets/js/view/item_production/form.vue',
            'resources/js/views/tenant/item_sets/form.vue',
            'resources/js/views/tenant/items/form.vue',
            'resources/js/views/tenant/pos/partials/form.vue',
        ];

        foreach ($forms as $path) {
            self::assertStringNotContainsString('v-model="form.item_code"', $this->source($path), $path);
        }

        $listings = [
            'modules/Digemid/Resources/assets/js/view/index.vue',
            'modules/Ecommerce/Resources/assets/js/views/item_sets/index.vue',
            'modules/Production/Resources/assets/js/view/item_production/index.vue',
            'modules/Production/Resources/assets/js/view/packaging/index.vue',
            'modules/Production/Resources/assets/js/view/production/index.vue',
            'resources/js/views/tenant/item_sets/index.vue',
            'resources/js/views/tenant/items/index.vue',
            'resources/js/views/system/configuration/visibleColumns.vue',
        ];

        foreach ($listings as $path) {
            $source = $this->source($path);
            self::assertDoesNotMatchRegularExpression('/^\s*item_code\s*:/m', $source, $path);
            self::assertStringNotContainsString('columns.item_code.visible', $source, $path);
            self::assertStringNotContainsString("col.key === 'item_code'", $source, $path);
        }

        $trait = $this->source('app/Traits/SunatItemCodeTrait.php');
        self::assertStringContainsString("'nullable'", $trait);
        self::assertStringContainsString("'digits:8'", $trait);
    }

    /** @test */
    public function migrated_exchange_rate_copy_does_not_claim_an_unimplemented_bcv_source(): void
    {
        foreach ($this->exchangeRateViews() as $path) {
            $source = $this->source($path);
            self::assertStringContainsString('content="Tipo de cambio del día"', $source, $path);
            self::assertStringNotContainsString('extraído de SUNAT', $source, $path);
            self::assertStringNotContainsString('extraído del BCV', $source, $path);
        }

        $service = $this->source('modules/ApiPeruDev/Data/ServiceData.php');
        self::assertStringContainsString("request('POST', '/api/tipo_de_cambio'", $service);
    }

    /** @test */
    public function migrated_identity_actions_use_a_neutral_search_label(): void
    {
        foreach ($this->identityViews() as $path) {
            $source = $this->source($path);
            $rendersSearchDirectly = str_contains($source, 'Buscar');
            $delegatesToSharedInput = str_contains($source, '<x-input-service');

            self::assertTrue(
                $rendersSearchDirectly || $delegatesToSharedInput,
                "{$path} debe mostrar Buscar directamente o delegar al componente compartido."
            );
            self::assertDoesNotMatchRegularExpression('/>[\s]*(SUNAT|RENIEC)[\s]*</i', $source, $path);
            self::assertDoesNotMatchRegularExpression('/buttonText\s*=\s*[\'\"](?:SUNAT|RENIEC)[\'\"]/', $source, $path);
        }

        foreach ($this->identityLabelSources() as $path) {
            self::assertStringContainsString('Buscar', $this->source($path), $path);
        }
    }

    /** @test */
    public function identity_document_type_zero_is_preserved_and_renamed_to_doc_sin_rif(): void
    {
        $payload = require database_path('seeders/data/tenant_initial_data.php');
        $identityDocumentTypes = collect($payload['tables']['cat_identity_document_types']['rows'])
            ->keyBy('id');

        self::assertTrue($identityDocumentTypes->has('0'));
        self::assertSame('Doc.sin.rif', $identityDocumentTypes->get('0')['description']);

        $migration = $this->source('database/migrations/tenant/2026_08_19_000332_rename_undomiciled_tax_document_to_doc_sin_rif.php');
        self::assertStringContainsString("->where('id', '0')", $migration);
        self::assertStringContainsString("->update(['description' => 'Doc.sin.rif'])", $migration);

        foreach ([
            'modules/Order/Imports/MiTiendaPeImport.php',
            'modules/Order/Resources/assets/js/views/order_notes/partials/options.vue',
        ] as $path) {
            $source = $this->source($path);
            self::assertStringContainsString('Doc.sin.rif', $source, $path);
            self::assertStringNotContainsString('Doc.trib.no.dom.sin.ruc', $source, $path);
        }
    }

    /** @test */
    public function internal_fiscal_contracts_remain_named_but_are_not_exposed_as_active_shipping_routes(): void
    {
        self::assertStringNotContainsString("Route::post('/sendSunat/{document}'", $this->source('routes/web.php'));
        self::assertStringNotContainsString('function sendDispatchToSunat', $this->source('app/Http/Controllers/Tenant/DispatchController.php'));
        self::assertStringContainsString("env('SUNAT_ALTERNATE_SERVER'", $this->source('config/configuration.php'));
        self::assertStringContainsString('LocalFiscalDocumentPolicy::registeredResponse()', $this->source('app/CoreFacturalo/Facturalo.php'));
        self::assertDirectoryDoesNotExist(base_path('app/CoreFacturalo/WS-BK'));
    }

    /** @test */
    public function generic_user_copy_no_longer_exposes_peruvian_authorities(): void
    {
        $expectations = [
            'resources/js/views/system/clients/form.vue' => ['Usuario Secundario Sunat'],
            'resources/js/views/system/companies/form.vue' => ['Usuario Secundario Sunat'],
            'resources/js/views/tenant/companies/form.vue' => ['portal de Sunat', 'Usuario Secundario Sunat'],
            'resources/js/views/tenant/components/partials/item_extra_info.vue' => ['No será enviado a SUNAT'],
            'resources/js/views/tenant/summaries/partials/regularize.vue' => ['validados por SUNAT'],
            'modules/Document/Resources/assets/js/views/documents/not_sent.vue' => ['OSE/SUNAT'],
            'modules/Document/Resources/assets/js/views/validate_documents/index.vue' => ['Estado Sunat'],
            'modules/Document/Resources/assets/js/views/validate_documents/partials/results.vue' => ['Estado Sunat', 'Estado Senit'],
            'resources/js/helpers/tours.js' => ['botón SUNAT/RENIEC'],
            'resources/js/helpers/help_summaries.json' => ['servidores de SUNAT/RENIEC'],
        ];

        foreach ($expectations as $path => $legacyLabels) {
            $source = $this->source($path);
            foreach ($legacyLabels as $legacyLabel) {
                self::assertStringNotContainsString($legacyLabel, $source, "{$path}: {$legacyLabel}");
            }
        }
    }

    /** @test */
    public function changed_sources_keep_balanced_contract_markers(): void
    {
        $paths = array_values(array_unique(array_merge(
            $this->exchangeRateViews(),
            $this->identityLabelSources(),
            [
                'modules/Digemid/Resources/assets/js/view/index.vue',
                'modules/Ecommerce/Resources/assets/js/views/item_sets/form.vue',
                'modules/Ecommerce/Resources/assets/js/views/item_sets/index.vue',
                'modules/Item/Resources/assets/js/views/items/item-detail.vue',
                'modules/Production/Resources/assets/js/view/item_production/form.vue',
                'modules/Production/Resources/assets/js/view/item_production/index.vue',
                'modules/Production/Resources/assets/js/view/packaging/index.vue',
                'modules/Production/Resources/assets/js/view/production/index.vue',
                'database/migrations/tenant/2026_08_19_000332_rename_undomiciled_tax_document_to_doc_sin_rif.php',
                'database/seeders/data/tenant_initial_data.php',
                'modules/Order/Imports/MiTiendaPeImport.php',
                'modules/Order/Resources/assets/js/views/order_notes/partials/options.vue',
                'resources/js/views/tenant/item_sets/form.vue',
                'resources/js/views/tenant/item_sets/index.vue',
                'resources/js/views/tenant/items/form.vue',
                'resources/js/views/tenant/items/index.vue',
                'resources/js/views/tenant/pos/partials/form.vue',
            ]
        )));

        foreach ($paths as $path) {
            $source = $this->source($path);
            self::assertSame(substr_count($source, '########## INICIO CAMBIO'), substr_count($source, '######### FIN CAMBIO'), $path);
            self::assertGreaterThan(0, substr_count($source, '########## INICIO CAMBIO'), $path);
        }
    }

    /** @return list<string> */
    private function exchangeRateViews(): array
    {
        return [
            'modules/Expense/Resources/assets/js/views/bank_loans/form.vue',
            'modules/Expense/Resources/assets/js/views/expenses/form.vue',
            'modules/Finance/Resources/assets/js/views/income/form.vue',
            'modules/Order/Resources/assets/js/views/order_notes/form.vue',
            'modules/Order/Resources/assets/js/views/order_notes/form_edit.vue',
            'modules/Purchase/Resources/assets/js/views/fixed_asset_purchases/form.vue',
            'modules/Purchase/Resources/assets/js/views/purchase-orders/form.vue',
            'modules/Purchase/Resources/assets/js/views/purchase-orders/generate.vue',
            'modules/Purchase/Resources/assets/js/views/purchase-orders/partials/document_generate.vue',
            'modules/Sale/Resources/assets/js/views/contracts/form.vue',
            'modules/Sale/Resources/assets/js/views/sale_opportunities/form.vue',
            'modules/Sale/Resources/assets/js/views/technical-services/form.vue',
            'resources/js/views/tenant/documents/invoice.vue',
            'resources/js/views/tenant/documents/invoice_generate.vue',
            'resources/js/views/tenant/documents/invoicetensu.vue',
            'resources/js/views/tenant/documents/invoiceupdate.vue',
            'resources/js/views/tenant/documents/note.vue',
            'resources/js/views/tenant/purchase-settlements/form.vue',
            'resources/js/views/tenant/purchases/form.vue',
            'resources/js/views/tenant/purchases/form_edit.vue',
            'resources/js/views/tenant/quotations/form.vue',
            'resources/js/views/tenant/quotations/form_edit.vue',
            'resources/js/views/tenant/sale_notes/form.vue',
        ];
    }

    /** @return list<string> */
    private function identityViews(): array
    {
        return [
            'resources/js/components/InputService.vue',
            'modules/ApiPeruDev/Resources/assets/js/components/InputService.vue',
            'modules/ApiPeruDev/Resources/assets/js/components/InputServiceGuest.vue',
            'modules/FullSuscription/Resources/assets/js/cliente.vue',
            'modules/FullSuscription/Resources/assets/js/clients/form.vue',
            'modules/FullSuscription/Resources/assets/js/clients/server.vue',
            'modules/Suscription/Resources/assets/js/clients/form.vue',
            'modules/Suscription/Resources/assets/js/clients/person.vue',
            'resources/js/views/tenant/dispatches/dispatchers/form.vue',
            'resources/js/views/tenant/dispatches/drivers/form.vue',
            'resources/js/views/tenant/dispatches/partials/buyer.vue',
            'resources/js/views/tenant/documents/partials/consigned.vue',
            'resources/js/views/tenant/persons/partials/consigned.vue',
            'resources/js/views/tenant/sale_notes/partials/consigned.vue',
            'resources/views/tenant/ecommerce/cart/detail.blade.php',
            'resources/views/tenant/ecommerce/cart/detail2.blade.php',
            'modules/Ecommerce/Resources/views/cart/detail2.blade.php',
        ];
    }

    private function source(string $path): string
    {
        self::assertFileExists(base_path($path));

        return (string) file_get_contents(base_path($path));
    }

    /** @return list<string> */
    private function identityLabelSources(): array
    {
        return [
            'resources/js/components/InputService.vue',
            'modules/ApiPeruDev/Resources/assets/js/components/InputService.vue',
            'modules/ApiPeruDev/Resources/assets/js/components/InputServiceGuest.vue',
            'resources/views/tenant/ecommerce/cart/detail.blade.php',
            'resources/views/tenant/ecommerce/cart/detail2.blade.php',
            'modules/Ecommerce/Resources/views/cart/detail2.blade.php',
        ];
    }
}
// ######### FIN CAMBIO SUNAT A SENIAT
