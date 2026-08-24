<?php

namespace Tests\Unit;

use App\Models\Tenant\PurchaseSettlementItem;
use App\Support\Venezuela\Localization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
    public function current_operation_structure_prepares_and_persists_sixteen_percent_iva(): void
    {
        $calculator = (string) file_get_contents(resource_path('js/helpers/functions.js'));
        self::assertStringContainsString('pigv = 0.16', $calculator);
        self::assertStringContainsString('percentage_igv: pigv * 100', $calculator);

        $quotation = (string) file_get_contents(app_path('Http/Controllers/Tenant/QuotationController.php'));
        self::assertStringContainsString('Localization::taxPercentage()', $quotation);

        foreach ([
            'app/Models/Tenant/DocumentItem.php',
            'app/Models/Tenant/SaleNoteItem.php',
            'app/Models/Tenant/PurchaseItem.php',
            'app/Models/Tenant/QuotationItem.php',
            'app/Models/Tenant/PurchaseSettlementItem.php',
            'app/Models/Tenant/TechnicalServiceItem.php',
            'modules/Order/Models/OrderNoteItem.php',
            'modules/Purchase/Models/FixedAssetPurchaseItem.php',
            'modules/Purchase/Models/PurchaseOrderItem.php',
            'modules/Sale/Models/ContractItem.php',
            'modules/Sale/Models/SaleOpportunityItem.php',
            'modules/Suscription/Models/Tenant/ItemRelSuscriptionPlan.php',
            'modules/FullSuscription/Models/Tenant/ItemRelSuscriptionPlan.php',
        ] as $path) {
            $model = (string) file_get_contents(base_path($path));
            self::assertStringContainsString("'percentage_igv'", $model, $path);
            self::assertStringContainsString("'total_igv'", $model, $path);
        }
    }

    /** @test */
    public function an_operation_detail_really_saves_the_sixteen_percent_rate_and_amount(): void
    {
        config(['database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        DB::purge('tenant');
        Schema::connection('tenant')->create('iva_contract_items', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('affectation_igv_type_id', 2);
            $table->decimal('total_base_igv', 12, 2);
            $table->decimal('percentage_igv', 12, 2);
            $table->decimal('total_igv', 12, 2);
            $table->decimal('total_taxes', 12, 2);
        });

        $base = 100.0;
        $iva = round($base * Localization::taxRate(), 2);

        $detail = new PurchaseSettlementItem();
        $detail->setTable('iva_contract_items');
        $detail->fill([
            'affectation_igv_type_id' => '10',
            'total_base_igv' => $base,
            'percentage_igv' => Localization::taxPercentage(),
            'total_igv' => $iva,
            'total_taxes' => $iva,
        ]);
        Model::withoutEvents(function () use ($detail): void {
            $detail->save();
        });

        $stored = DB::connection('tenant')->table('iva_contract_items')->first();
        self::assertEquals(16.0, (float) $stored->percentage_igv);
        self::assertEquals(16.0, (float) $stored->total_igv);
        self::assertEquals(16.0, (float) $stored->total_taxes);

        DB::disconnect('tenant');
    }

    /** @test */
    public function corrected_creation_flows_do_not_keep_the_obsolete_eighteen_percent_rate(): void
    {
        foreach ([
            'app/Http/Controllers/Tenant/QuotationController.php',
            'app/Imports/DocumentImportExcelFormat.php',
            'app/Imports/DocumentsImport.php',
            'app/Imports/DocumentsImportTwoFormat.php',
            'modules/Order/Imports/MiTiendaPeImport.php',
            'modules/Ecommerce/Http/Resources/ItemBarCollection.php',
            'modules/Ecommerce/Resources/assets/js/frontend/cart-app.js',
            'modules/Ecommerce/Resources/views/cart/detail2.blade.php',
            'resources/js/views/tenant/documents/invoiceupdate.vue',
            'resources/js/views/tenant/pos/partials/form.vue',
            'resources/js/views/tenant/quotations/form.vue',
            'resources/js/views/tenant/quotations/form_edit.vue',
            'resources/js/views/tenant/quotations/partials/define_prices.vue',
        ] as $path) {
            $source = (string) file_get_contents(base_path($path));
            self::assertDoesNotMatchRegularExpression('/(?<![\d.])(?:0\.18|1\.18)(?![\d.])/', $source, $path);
            self::assertDoesNotMatchRegularExpression('/(?:percentage_igv|porcentaje_igv)[^\n]{0,40}(?:\?\?|\?:|=|:)\s*[\'\"]?18\b/', $source, $path);
        }
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
    public function garage_pos_displays_the_dynamic_sixteen_percent_iva_rate(): void
    {
        $garage = (string) file_get_contents(resource_path('js/views/tenant/pos/garage.vue'));
        $payment = (string) file_get_contents(resource_path('js/views/tenant/pos/partials/fast_payment_garage.vue'));

        self::assertStringContainsString(':percentage-igv="percentage_igv"', $garage);
        self::assertSame(2, substr_count($payment, 'IVA ({{ ivaPercentageLabel }}%)'));
        self::assertStringContainsString('(Number(this.percentageIgv) || 0.16) * 100', $payment);
        self::assertStringNotContainsString('IVA (18%)', $payment);
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
