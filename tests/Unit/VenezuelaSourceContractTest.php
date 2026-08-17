<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

// ########### INICIO PRUEBAS CONTRATO FUENTES VENEZUELA
class VenezuelaSourceContractTest extends TestCase
{
    /** @test */
    public function active_sources_do_not_create_peruvian_defaults_or_legacy_currency_and_phone_values(): void
    {
        $legacyPen = 'PE' . 'N';
        $legacyVed = 'VE' . 'D';
        $patterns = [
            '/\+51/',
            "/country_id['\"]?\s*(?::|=>|=)\s*['\"]PE['\"]/",
            "/codigo_pais['\"]?\s*(?::|=>|=)\s*['\"]PE['\"]/",
            "/nationality_id['\"]?\s*(?::|=>|=)\s*['\"]PE['\"]/",
            "/currency_type_id['\"]?\s*(?::|=>|=)\s*['\"](?:{$legacyPen}|{$legacyVed})['\"]/",
            "/['\"]150101['\"]/",
        ];
        $violations = [];

        foreach ($this->activeSourceFiles() as $path) {
            $source = (string) file_get_contents($path);
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $source) === 1) {
                    $violations[] = $path;
                    break;
                }
            }
        }

        self::assertSame([], array_values(array_unique($violations)));
    }

    /** @test */
    public function new_record_forms_default_to_venezuela_and_ves(): void
    {
        foreach ([
            'resources/js/views/tenant/customers/form.vue',
            'resources/js/views/tenant/suppliers/form.vue',
            'resources/js/views/tenant/dispatches/OriginAddress/Form.vue',
            'modules/Suscription/Resources/assets/js/clients/form.vue',
        ] as $path) {
            self::assertStringContainsString("country_id: 'VE'", (string) file_get_contents(base_path($path)), $path);
        }

        foreach (['index.vue', 'fast.vue', 'garage.vue'] as $file) {
            $path = resource_path("js/views/tenant/pos/{$file}");
            $source = (string) file_get_contents($path);
            self::assertStringContainsString('currency_type_id: "VES"', $source, $path);
            self::assertStringContainsString('=== "VES" ? "USD" : "VES"', $source, $path);
        }
    }

    /** @test */
    public function consolidated_schema_supports_venezuela_currency_geography_and_people(): void
    {
        $currency = $this->migrationSource('cat_currency_types');
        self::assertStringContainsString('CREATE TABLE `cat_currency_types`', $currency);
        self::assertStringContainsString('`id` varchar(255)', $currency);
        self::assertStringNotContainsString("'VE" . "D'", $currency);

        self::assertStringContainsString('`id` char(2)', $this->migrationSource('departments'));
        self::assertStringContainsString('`department_id` char(2)', $this->migrationSource('provinces'));
        self::assertStringContainsString('`province_id` char(4)', $this->migrationSource('districts'));
        self::assertStringContainsString("DEFAULT 'VE'", $this->migrationSource('origin_addresses'));

        $persons = $this->migrationSource('persons');
        self::assertStringContainsString('`website` text', $persons);
        self::assertStringContainsString('`observation` longtext', $persons);

        $foreignKeys = $this->foreignKeyMigrationSource();
        self::assertStringContainsString('ALTER TABLE `provinces` ADD CONSTRAINT', $foreignKeys);
        self::assertStringContainsString('ALTER TABLE `districts` ADD CONSTRAINT', $foreignKeys);
    }

    /** @test */
    public function locations_come_from_the_tenant_database_with_tenant_scoped_cache(): void
    {
        $helper = (string) file_get_contents(app_path('helper.php'));
        self::assertStringContainsString('Department::query()', $helper);
        self::assertStringContainsString("->with('provinces', 'provinces.districts')", $helper);
        self::assertStringContainsString('locations:v2:{$tenantDatabase}:{$countryId}', $helper);
        self::assertStringNotContainsString('file_get_contents', $helper);
        self::assertFileDoesNotExist(database_path('data/venezuela_locations.php'));
    }

    /** @test */
    public function customer_form_requests_nationality_only_for_foreign_documents(): void
    {
        $form = (string) file_get_contents(resource_path('js/views/tenant/persons/form.vue'));
        self::assertStringContainsString("v-if=\"type !== 'customers' || isForeignDocument\"", $form);
        self::assertStringContainsString("nationality_id: 'VE'", $form);
        self::assertStringContainsString("return this.form.identity_document_type_id === '4'", $form);
        self::assertStringContainsString("address.country_id === 'VE'", $form);
    }

    /** @test */
    public function document_list_and_purchase_selector_use_catalog_symbols_and_ves(): void
    {
        $collection = (string) file_get_contents(app_path('Http/Resources/Tenant/DocumentCollection.php'));
        $list = (string) file_get_contents(resource_path('js/views/tenant/documents/index.vue'));
        $controller = (string) file_get_contents(app_path('Http/Controllers/Tenant/DocumentController.php'));
        $purchaseItem = (string) file_get_contents(resource_path('js/views/tenant/purchases/partials/item.vue'));

        self::assertStringContainsString("'currency_type_symbol' =>", $collection);
        self::assertStringContainsString('{{ row.currency_type_symbol }}', $list);
        self::assertStringContainsString('Localization::nationalCurrencyId()', $controller);
        self::assertStringContainsString('Localization::currencySymbol($currencyTypeId)', $controller);
        self::assertStringContainsString('v-for="option in availableCurrencyTypes"', $purchaseItem);
        self::assertStringContainsString(':label="option.symbol"', $purchaseItem);
        self::assertStringContainsString(':value="option.id"', $purchaseItem);
    }

    /** @test */
    public function pos_keeps_payment_visible_and_document_selection_safe(): void
    {
        $pos = (string) file_get_contents(resource_path('js/views/tenant/pos/index.vue'));
        $payment = (string) file_get_contents(resource_path('js/views/tenant/pos/partials/payment.vue'));

        self::assertStringContainsString('d-flex flex-column pos-checkout-column', $pos);
        self::assertStringContainsString('flex-grow-1 pos-checkout-details', $pos);
        self::assertStringContainsString('data-testid="pos-open-payment"', $pos);
        self::assertStringContainsString('<span>PAGAR', $pos);
        self::assertStringContainsString("@click.native=\"selectDocumentType('01')\"", $payment);
        self::assertStringContainsString('this.form.document_type_id = documentTypeId', $payment);
        self::assertStringContainsString('return Boolean(this.businessTurns && this.businessTurns.active)', $payment);
        self::assertStringNotContainsString('qz.websocket.isActive()', $payment);
    }

    /** @test */
    public function active_presentations_do_not_use_peruvian_currency_labels(): void
    {
        $legacyName = 'so' . 'les';
        $legacySymbol = 'S' . '/';
        $violations = [];

        foreach ($this->activeSourceFiles() as $path) {
            $source = (string) file_get_contents($path);
            if (
                preg_match('/\b' . preg_quote($legacyName, '/') . '\b/i', $source) === 1
                || preg_match('/' . preg_quote($legacySymbol, '/') . '(?=\s|[.<&{\d"\'`$]|$)/', $source) === 1
            ) {
                $violations[] = $path;
            }
        }

        self::assertSame([], array_values(array_unique($violations)));
    }

    /** @test */
    public function tenancy_mock_seeder_is_idempotent_and_identifies_venezuelan_records(): void
    {
        $seeder = (string) file_get_contents(database_path('seeders/TenancyMockDataSeeder.php'));
        self::assertStringContainsString("'currency_type_id' => 'VES'", $seeder);
        self::assertStringContainsString("'country_id' => 'VE'", $seeder);
        self::assertStringContainsString('MOCK-', $seeder);
        self::assertStringContainsString('updateOrInsert', $seeder);
    }

    /** @return list<string> */
    private function activeSourceFiles(): array
    {
        $files = [];
        foreach ([app_path(), base_path('modules'), resource_path('js')] as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $path = $file->getPathname();
                if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'vue'], true)) {
                    continue;
                }
                if (str_contains($path, '/views_bk/') || str_contains($path, '_bk.') || str_contains($path, '_bkp/') || str_contains($path, '/vendor/') || str_contains($path, 'vfs_fonts.js')) {
                    continue;
                }
                $files[] = $path;
            }
        }

        return $files;
    }

    private function migrationSource(string $table): string
    {
        $files = glob(database_path("migrations/tenant/*_create_{$table}_table.php")) ?: [];
        self::assertCount(1, $files, "Debe existir una migración consolidada para {$table}.");

        return (string) file_get_contents($files[0]);
    }

    private function foreignKeyMigrationSource(): string
    {
        $files = glob(database_path('migrations/tenant/*_add_tenant_foreign_keys.php')) ?: [];
        self::assertCount(1, $files, 'Debe existir una única migración final de claves foráneas.');

        return (string) file_get_contents($files[0]);
    }
}
// ########### FIN PRUEBAS CONTRATO FUENTES VENEZUELA
