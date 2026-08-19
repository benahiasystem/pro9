<?php

namespace Tests\Unit;

use App\Support\Venezuela\Localization;
use Tests\TestCase;

// ########## INICIO CAMBIO AFECTACIÓN IVA
class VenezuelaIvaContractTest extends TestCase
{
    /** @test */
    public function it_exposes_one_venezuelan_iva_source_of_truth(): void
    {
        self::assertSame(0.16, Localization::taxRate());
        self::assertSame(16.0, Localization::taxPercentage());
        self::assertSame(1.16, Localization::taxMultiplier());
        self::assertSame(['10', '20'], Localization::selectableAffectationIds());
    }

    /** @test */
    public function it_calculates_taxed_and_exempt_totals_without_renaming_internal_contracts(): void
    {
        $base = 100.0;
        $tax = round($base * Localization::taxRate(), 2);

        self::assertSame(16.0, $tax);
        self::assertSame(116.0, $base + $tax);
        self::assertSame(100.0, $base + 0.0);

        $model = (string) file_get_contents(app_path('Models/Tenant/Catalogs/AffectationIgvType.php'));
        self::assertStringContainsString('affectation_igv_type_id', $this->activeSourceContract());
        self::assertStringContainsString('selectableAffectationIds()', $model);
    }

    /** @test */
    public function it_configures_new_and_existing_tenants_with_gravado_and_exento(): void
    {
        $seed = (string) file_get_contents(database_path('seeders/data/tenant_initial_data.php'));
        $migration = (string) file_get_contents(
            database_path('migrations/tenant/2026_08_18_000331_configure_venezuela_iva.php')
        );

        foreach ([$seed, $migration] as $source) {
            self::assertStringContainsString("'10'", $source);
            self::assertStringContainsString("'20'", $source);
            self::assertStringContainsString("'Gravado'", $source);
            self::assertStringContainsString("'Exento'", $source);
        }

        self::assertStringContainsString('updateOrInsert', $migration);
        self::assertStringNotContainsString("->delete()", $migration);
    }

    /** @test */
    public function visible_item_contracts_use_iva_and_keep_igv_bindings(): void
    {
        foreach ([
            'modules/Item/Resources/assets/js/views/items/item-detail.vue',
            'resources/js/views/tenant/items/form.vue',
            'resources/js/views/tenant/items/index.vue',
            'modules/Digemid/Resources/assets/js/view/index.vue',
        ] as $path) {
            $source = (string) file_get_contents(base_path($path));
            self::assertMatchesRegularExpression('/(?:Incluye|Tiene) IVA/', $source, $path);
            self::assertDoesNotMatchRegularExpression('/(?:Incluye|Tiene) Igv/i', $source, $path);
            self::assertStringContainsString('igv', strtolower($source), $path);
        }
    }

    /** @test */
    public function store_and_frontend_defaults_use_the_venezuelan_rate(): void
    {
        $store = (string) file_get_contents(base_path('modules/Store/Http/Controllers/StoreController.php'));
        $mixin = (string) file_get_contents(resource_path('js/mixins/functions.js'));

        self::assertStringContainsString('Localization::taxRate()', $store);
        self::assertStringContainsString('percentage_igv: 0.16', $mixin);
        self::assertStringNotContainsString('return 0.18', $store);
    }

    /** @test */
    public function blade_markers_do_not_break_raw_php_blocks(): void
    {
        foreach ([
            'app/CoreFacturalo/Templates/xml/invoice.blade.php',
            'modules/Report/Resources/views/documents/report_pdf.blade.php',
            'modules/Report/Resources/views/documents/report_excel.blade.php',
        ] as $path) {
            $source = (string) file_get_contents(base_path($path));

            preg_match_all('/(?:@php|<\?php)(.*?)(?:@endphp|\?>)/s', $source, $phpBlocks);

            foreach ($phpBlocks[1] as $phpBlock) {
                self::assertStringNotContainsString('{{--', $phpBlock, $path);
            }
        }
    }

    /** @test */
    public function ubl_xml_keeps_the_sunat_igv_tax_name(): void
    {
        foreach ([
            'invoice.blade.php',
            'credit.blade.php',
            'debit.blade.php',
            'summary.blade.php',
            'purchase_settlement.blade.php',
        ] as $template) {
            $source = (string) file_get_contents(
                app_path("CoreFacturalo/Templates/xml/{$template}")
            );

            self::assertStringContainsString('<cbc:Name>IGV</cbc:Name>', $source, $template);
            self::assertStringNotContainsString('<cbc:Name>IVA</cbc:Name>', $source, $template);
        }
    }

    /** @test */
    public function pos_totals_do_not_contain_the_obsolete_commented_summary(): void
    {
        $source = (string) file_get_contents(
            resource_path('js/views/tenant/pos/index.vue')
        );

        self::assertSame(1, substr_count($source, '<td>IVA</td>'));
        self::assertStringNotContainsString('<div class="col-12 text-right px-0" v-if="form.total_igv > 0">', $source);
        self::assertStringNotContainsString('</div> -->', $source);
    }

    /** @test */
    public function iva_change_markers_are_balanced(): void
    {
        $diff = shell_exec('git diff --unified=0 -- . ":(exclude)public/build" ":(exclude)public/js"') ?: '';

        self::assertSame(
            substr_count($diff, '########## INICIO CAMBIO IGV A IVA'),
            substr_count($diff, '######### FIN CAMBIO IGV A IVA')
        );
        self::assertSame(
            substr_count($diff, '########## INICIO CAMBIO AFECTACIÓN IVA'),
            substr_count($diff, '######### FIN CAMBIO AFECTACIÓN IVA')
        );
    }

    private function activeSourceContract(): string
    {
        return implode("\n", [
            (string) file_get_contents(app_path('Models/Tenant/Item.php')),
            (string) file_get_contents(resource_path('js/views/tenant/items/form.vue')),
        ]);
    }
}
// ######### FIN CAMBIO AFECTACIÓN IVA
