<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DashboardTopKpiVisualContractTest extends TestCase
{
    /** @test */
    public function top_dashboard_amounts_use_bolivars_and_a_non_overlapping_layout(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/modules/Dashboard/Resources/assets/js/views/RowTop.vue'
        );

        self::assertNotFalse($source);
        self::assertSame(4, substr_count($source, 'Bs. {{'));
        self::assertSame(4, substr_count($source, 'class="kpi-amount font-weight-bold m-0"'));
        self::assertStringContainsString('.kpi-row .card-kpi {', $source);
        self::assertStringContainsString('min-height: 96px;', $source);
        self::assertStringContainsString('justify-content: space-between;', $source);
        self::assertStringContainsString('text-overflow: ellipsis;', $source);
        self::assertStringNotContainsString('text-nowrap', $source);
    }

    /** @test */
    public function widget_dashboard_kpis_reserve_separate_space_for_title_and_value(): void
    {
        $basePath = dirname(__DIR__, 2).'/modules/Dashboard/Resources/assets/js/widgets';
        $card = file_get_contents($basePath.'/WidgetCard.vue');
        $renderer = file_get_contents($basePath.'/renderers/KpiRenderer.vue');

        self::assertNotFalse($card);
        self::assertNotFalse($renderer);
        self::assertStringContainsString(":class=\"{ 'wg-body-kpi': isKpi }\"", $card);
        self::assertStringContainsString("<template v-else-if=\"dataset\">\n          <div class=\"wg-head\" :class=\"{ 'wg-head-kpi': isKpi }\">", $card);
        self::assertStringContainsString("return this.widget.type === 'kpi' || this.widget.type === 'kpi_spark'", $card);
        self::assertStringContainsString('.wg-body-kpi {', $card);
        self::assertStringContainsString('padding: 0.5rem 0.875rem !important;', $card);
        self::assertStringContainsString('.wg-head-kpi {', $card);
        self::assertStringContainsString('flex: 0 0 auto;', $card);
        self::assertStringContainsString('margin-bottom: 0.15rem;', $card);
        self::assertStringContainsString('return Math.max(40, cell - 44)', $card);
        self::assertStringContainsString('.wg-kpi-value {', $renderer);
        self::assertStringContainsString('line-height: 1.05;', $renderer);
        self::assertStringContainsString('white-space: nowrap;', $renderer);
    }
}
