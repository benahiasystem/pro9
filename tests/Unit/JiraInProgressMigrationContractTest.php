<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

// ########## INICIO CAMBIO SIN XML CDR SUNAT
class JiraInProgressMigrationContractTest extends TestCase
{
    /** @test */
    public function fiscal_generation_storage_download_and_shipping_have_local_policy_barriers(): void
    {
        $facturalo = $this->source('app/CoreFacturalo/Facturalo.php');
        self::assertStringContainsString('LocalFiscalDocumentPolicy::registeredResponse()', $facturalo);
        self::assertStringNotContainsString('function createXmlUnsigned', $facturalo);
        self::assertStringNotContainsString('function senderXmlSignedBill', $facturalo);
        self::assertStringNotContainsString('function consultCdr', $facturalo);

        $storage = $this->source('app/CoreFacturalo/Helpers/Storage/StorageDocument.php');
        self::assertStringContainsString("['unsigned', 'signed', 'cdr', 'cdr_xml', 'cdr_b64']", $storage);
        self::assertStringContainsString('isFiscalXmlFileType($file_type)', $storage);

        $download = $this->source('app/Http/Controllers/Tenant/DownloadController.php');
        self::assertStringContainsString("in_array(\$type, ['xml', 'cdr', 'cdr_xml'], true)", $download);

        $email = $this->source('app/Mail/Tenant/DocumentEmail.php');
        self::assertStringNotContainsString("'signed'", $email);
        self::assertStringNotContainsString("'cdr'", $email);
        self::assertStringContainsString("'.pdf'", $email);

        $configuration = $this->source('app/Models/Tenant/Configuration.php');
        self::assertStringNotContainsString('LocalFiscalDocumentPolicy', $configuration);
        self::assertStringNotContainsString('send_auto', $configuration);
        self::assertStringNotContainsString('auto_send_dispatchs_to_sunat', $configuration);
        self::assertStringNotContainsString('sunat_alternate_server', $configuration);
        self::assertStringNotContainsString('ticket_single_shipment', $configuration);

        foreach ([
            'resources/js/views/tenant/pos/partials/payment.vue',
            'resources/js/views/tenant/pos/partials/fast_payment.vue',
            'resources/js/views/tenant/pos/partials/fast_payment_garage.vue',
            'resources/js/views/tenant/sale_notes/partials/option_documents.vue',
            'resources/js/views/tenant/documents/index.vue',
            'resources/js/views/tenant/contingencies/index.vue',
        ] as $path) {
            $source = $this->source($path);
            self::assertStringNotContainsString('/documents/send/', $source, $path);
            self::assertStringNotContainsString('clickResend(', $source, $path);
        }
    }

    /** @test */
    public function fiscal_shipping_validation_and_detraction_report_routes_are_not_registered(): void
    {
        $sources = implode("\n", array_map(fn (string $path): string => $this->source($path), [
            'routes/web.php',
            'routes/api.php',
            'modules/Document/Routes/web.php',
            'modules/ApiPeruDev/Routes/web.php',
            'modules/Report/Routes/web.php',
        ]));
        $activeSources = preg_replace('/^\s*\/\/.*$/m', '', $sources) ?? $sources;

        foreach ([
            "Route::get('documents/send/",
            "Route::post('documents_server'",
            "Route::post('document_check_server'",
            "Route::post('/sendSunat/",
            "Route::get('documents/consult_cdr/",
            "Route::post('/status_ticket'",
            'document-detractions',
            'massive_validate_cpe',
            'regularize-shipping',
            "Route::prefix('documents/not-sent')",
        ] as $routeFragment) {
            self::assertStringNotContainsString($routeFragment, $activeSources, $routeFragment);
        }

        foreach ([
            'modules/Report/Http/Controllers/ReportDocumentDetractionController.php',
            'modules/Report/Http/Resources/DocumentDetractionCollection.php',
            'modules/Report/Resources/assets/js/views/document-detractions/index.vue',
            'modules/Report/Resources/views/document-detractions/index.blade.php',
        ] as $path) {
            self::assertFileDoesNotExist(base_path($path), $path);
        }

        $notifications = $this->source('app/Http/Helpers/HeaderNotifications.php');
        self::assertStringNotContainsString("safeAppend(\$notifications, 'appendDocumentsNotSent')", $notifications);
        self::assertStringNotContainsString("safeAppend(\$notifications, 'appendSystemAlerts')", $notifications);

        $redirects = $this->source('app/Http/Middleware/RedirectModuleLevel.php');
        self::assertStringNotContainsString("case 'document_not_sent':", $redirects);
        self::assertStringNotContainsString("case 'regularize_shipping':", $redirects);

        self::assertFileDoesNotExist(base_path('modules/PseService'));
        self::assertFileDoesNotExist(base_path('modules/Document/Resources/assets/js/components/DataTableValidateDocuments.vue'));
        self::assertFileDoesNotExist(base_path('modules/Document/Resources/assets/js/views/validate_documents'));
    }

    /** @test */
    public function document_lists_keep_pdf_without_xml_or_cdr_actions(): void
    {
        foreach ([
            'resources/js/views/tenant/perceptions/index.vue',
            'resources/js/views/tenant/retentions/index.vue',
            'resources/js/views/tenant/purchase-settlements/index.vue',
            'resources/js/views/tenant/contingencies/index.vue',
            'resources/js/views/tenant/documents/index.vue',
        ] as $path) {
            $source = preg_replace('/<!--.*?-->/s', '', $this->source($path)) ?? $this->source($path);
            self::assertStringContainsString('pdf', strtolower($source), $path);
            self::assertDoesNotMatchRegularExpression('/clickDownload\([^\n]*(?:xml|cdr)/i', $source, $path);
        }
    }

    /** @test */
    public function item_forms_do_not_keep_retired_tax_fields(): void
    {
        $itemForms = [
            'resources/js/views/tenant/items/form.vue',
            'modules/Item/Resources/assets/js/views/items/item-detail.vue',
        ];
        foreach ($itemForms as $path) {
            $source = $this->source($path);
            self::assertDoesNotMatchRegularExpression('/v-model=["\'][^"\']*(?:has_isc|purchase_has_isc|subject_to_detraction)/', $source, $path);
            self::assertStringNotContainsString('has_isc', $source, $path);
            self::assertStringNotContainsString('subject_to_detraction', $source, $path);
        }

        foreach ($this->plasticBagForms() as $path) {
            $source = $this->source($path);
            self::assertDoesNotMatchRegularExpression('/v-model=["\']form\.has_plastic_bag_taxes["\']/', $source, $path);
            self::assertStringNotContainsString('has_plastic_bag_taxes', $source, $path);
        }
    }

    /** @test */
    public function reports_and_pdfs_have_no_retired_tax_fields_or_visibility_switch(): void
    {
        $files = $this->reportBladeFiles();
        self::assertNotEmpty($files);
        foreach ($files as $path) {
            self::assertDoesNotMatchRegularExpression(
                '/total_isc|system_isc|total_plastic_bag_taxes|showIsc\s*\(/i',
                (string) file_get_contents($path),
                $path
            );
        }
    }

    /** @test */
    public function sales_report_and_its_table_share_the_same_visible_column_contract(): void
    {
        $report = $this->source('modules/Report/Resources/assets/js/views/documents/index.vue');
        $table = $this->source('modules/Report/Resources/assets/js/components/DataTableReportsDocuments.vue');

        self::assertStringNotContainsString('columns.total_isc.visible', $report);
        self::assertStringNotContainsString('visibleColumns.total_isc.visible', $table);
        self::assertStringContainsString('Object.keys(this.columns).reduce', $report);
        self::assertStringContainsString('currentCols[key]', $report);
        self::assertStringNotContainsString('filter(function(num)', $report);
        self::assertStringContainsString(':salesColumnKeys="salesVisibleColumnKeys"', $report);
        self::assertStringContainsString('salesVisibleColumnKeys()', $report);
        self::assertStringContainsString('v-for="columnKey in salesColumnKeys"', $table);
        self::assertStringContainsString('salesFooterLabelColumnKey', $table);
        self::assertStringContainsString('salesFooterValue(columnKey, currency)', $table);
        self::assertStringContainsString("'text-start': isSalesTotalColumn(columnKey)", $table);
        self::assertStringNotContainsString(':colspan="colspanFootSales"', $table);
        self::assertStringNotContainsString('colspanFootSales:', $table);
    }

    /** @return list<string> */
    private function plasticBagForms(): array
    {
        return [
            'modules/FullSuscription/Resources/assets/js/payment_receipt/partials/item.vue',
            'modules/FullSuscription/Resources/assets/js/payment_receipt/partials/item_CPE.vue',
            'modules/Suscription/Resources/assets/js/payment_receipt/partials/item.vue',
            'modules/Item/Resources/assets/js/views/items/item-detail.vue',
            'resources/js/views/tenant/documents/partials/item.vue',
            'resources/js/views/tenant/items/form.vue',
            'resources/js/views/tenant/pos/partials/form.vue',
            'resources/js/views/tenant/quotations/partials/item.vue',
            'resources/js/views/tenant/sale_notes/partials/item.vue',
        ];
    }

    /** @return list<string> */
    private function reportBladeFiles(): array
    {
        $files = [];
        foreach ([
            base_path('app/CoreFacturalo/Templates/pdf'),
            base_path('modules/Report/Resources/views'),
            base_path('modules/Account/Resources/views'),
        ] as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function source(string $path): string
    {
        self::assertFileExists(base_path($path), $path);

        return (string) file_get_contents(base_path($path));
    }
}
// ######### FIN CAMBIO SIN XML CDR SUNAT
