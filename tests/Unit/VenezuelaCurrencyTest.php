<?php

namespace Tests\Unit;

use App\Models\Tenant\ModelTenant;
use App\Support\Venezuela\Localization;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class VenezuelaCurrencyTest extends TestCase
{
    // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
    /** @test */
    public function it_exposes_the_venezuelan_currency_contract(): void
    {
        self::assertSame('VES', Localization::nationalCurrencyId());
        self::assertSame('USD', Localization::secondaryCurrencyId());
        self::assertSame('VES', ModelTenant::NATIONAL_CURRENCY_ID);
        self::assertSame('Bs.', Localization::currencySymbol('VES'));
        self::assertSame('$', Localization::currencySymbol('USD'));
        self::assertSame('Bs.', Localization::currencySymbol(null));
    }

    /** @test */
    public function tenant_seed_data_contains_only_ves_and_usd_currency_catalog_records(): void
    {
        $data = require database_path('seeders/data/tenant_initial_data.php');
        $rows = collect($data['tables']['cat_currency_types']['rows'])->keyBy('id');
        $legacyCurrencyIds = ['PE' . 'N', 'VE' . 'D'];

        self::assertSame(['USD', 'VES'], $rows->keys()->sort()->values()->all());
        self::assertSame('Bs.', $rows['VES']['symbol']);
        self::assertSame('Bolívares', $rows['VES']['description']);
        self::assertSame(1, $rows['VES']['active']);
        self::assertSame('$', $rows['USD']['symbol']);
        self::assertSame(1, $rows['USD']['active']);
        self::assertSame([], $rows->keys()->intersect($legacyCurrencyIds)->values()->all());
    }

    /** @test */
    public function active_runtime_sources_do_not_use_legacy_currency_codes_or_labels(): void
    {
        $violations = [];
        $legacyCurrencyIds = ['PE' . 'N', 'VE' . 'D'];
        $legacySymbol = 'S' . '/';
        $legacyName = 'so' . 'les';

        foreach ($this->activeRuntimeFiles() as $file) {
            $source = (string) file_get_contents($file);
            $hasLegacyCode = preg_match(
                '/\\b(?:' . implode('|', $legacyCurrencyIds) . ')\\b/',
                $source
            ) === 1;
            $hasLegacyLabel = preg_match(
                '/' . preg_quote($legacySymbol, '/') . '(?=\s|[.<&{\d"\'`$]|$)/',
                $source
            ) === 1
                || preg_match('/\\b' . $legacyName . '\\b/i', $source) === 1;

            if ($hasLegacyCode || $hasLegacyLabel) {
                $violations[] = $file;
            }
        }

        self::assertSame([], $violations, 'Persisten códigos o etiquetas monetarias obsoletas.');
    }

    /** @test */
    public function pos_defaults_to_ves_and_alternates_only_with_usd(): void
    {
        foreach (['index.vue', 'fast.vue', 'garage.vue'] as $file) {
            $source = (string) file_get_contents(
                resource_path("js/views/tenant/pos/{$file}")
            );

            self::assertStringContainsString('currency_type_id: "VES"', $source, $file);
            self::assertStringContainsString('=== "VES" ? "USD" : "VES"', $source, $file);
        }
    }

    /** @test */
    public function document_list_uses_the_currency_catalog_symbol(): void
    {
        $collection = (string) file_get_contents(
            app_path('Http/Resources/Tenant/DocumentCollection.php')
        );
        $list = (string) file_get_contents(
            resource_path('js/views/tenant/documents/index.vue')
        );

        self::assertStringContainsString("'currency_type_symbol' =>", $collection);
        self::assertStringContainsString('{{ row.currency_type_symbol }}', $list);
        self::assertStringContainsString('Localization::currencySymbol($row->currency_type_id)', $collection);
    }

    /** @test */
    public function currency_is_initialized_without_a_conversion_migration(): void
    {
        self::assertSame([], glob(database_path('migrations/tenant/*_migrate_currency_code_to_ves.php')));
        $payload = require database_path('seeders/data/tenant_initial_data.php');
        $ids = array_column($payload['tables']['cat_currency_types']['rows'], 'id');
        self::assertContains('VES', $ids);
        self::assertContains('USD', $ids);
        self::assertNotContains('PEN', $ids);
        self::assertNotContains('VED', $ids);
    }

    /** @test */
    public function culqi_rejects_ves_before_creating_a_charge(): void
    {
        foreach ([
            app_path('Http/Controllers/Tenant/AccountController.php'),
            base_path('modules/Ecommerce/Http/Controllers/CulqiController.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);
            $guardPosition = strpos($source, "NATIONAL_CURRENCY_ID === 'VES'");
            $chargePosition = strpos($source, '$culqi->Charges->create');

            self::assertNotFalse($guardPosition, $path);
            self::assertNotFalse($chargePosition, $path);
            self::assertLessThan($chargePosition, $guardPosition, $path);
            self::assertStringContainsString(
                'Culqi no está habilitado para cobros en bolívares',
                $source,
                $path
            );
        }
    }

    /** @test */
    public function incompatible_payment_providers_are_disabled_for_ves(): void
    {
        foreach ([
            app_path('Http/Controllers/System/PaymentOrderPublicController.php'),
            base_path('modules/Payment/Http/Controllers/PaymentGatewayController.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);

            self::assertStringContainsString('Localization::nationalCurrencyId()', $source, $path);
            self::assertStringContainsString('Culqi no está habilitado para cobros en bolívares', $source, $path);
            self::assertStringContainsString('Izipay no está habilitado para cobros en bolívares', $source, $path);
        }

        $gateway = (string) file_get_contents(
            base_path('modules/Payment/Http/Controllers/PaymentGatewayController.php')
        );
        self::assertStringContainsString(
            'MercadoPago no está habilitado para cobros en bolívares',
            $gateway
        );

        foreach ([
            resource_path('views/tenant/ecommerce/cart/detail.blade.php'),
            resource_path('views/tenant/ecommerce/cart/detail2.blade.php'),
            base_path('modules/Ecommerce/Resources/views/cart/detail2.blade.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);
            self::assertStringNotContainsString('action="https://www.paypal.com/cgi-bin/webscr"', $source, $path);
            self::assertStringContainsString('PayPal no está habilitado para cobros en bolívares', $source, $path);
        }
    }

    /**
     * @return iterable<string>
     */
    private function activeRuntimeFiles(): iterable
    {
        foreach ([app_path(), base_path('modules'), resource_path(), config_path()] as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'vue'], true)) {
                    continue;
                }

                $path = $file->getPathname();
                if (
                    str_contains($path, '/views_bk/')
                    || str_contains($path, '_bk.')
                    || str_contains($path, '/_bkp/')
                    || str_contains($path, '/vendor/')
                    || str_contains($path, 'vfs_fonts.js')
                    || str_contains($path, 'CodeErrors.xml')
                ) {
                    continue;
                }

                yield $path;
            }
        }
    }
    // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
}
