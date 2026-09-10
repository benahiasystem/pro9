<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HotelRentProductsTemplateContractTest extends TestCase
{
    /** @test */
    public function rent_product_totals_use_a_complete_table_row_without_nested_html_comments_or_iva(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/modules/Hotel/Resources/assets/js/views/rooms/AddProductToRoom.vue'
        );

        self::assertNotFalse($source);
        self::assertStringNotContainsString('<!-- <td', $source);
        self::assertStringNotContainsString('</td> -->', $source);
        $totals = $this->between($source, '<tfoot v-if="form.products.length > 0">', '</tfoot>');
        self::assertStringContainsString('<td colspan="5"></td>', $totals);
        self::assertStringNotContainsString('<strong>IVA</strong>', $totals);
        self::assertStringNotContainsString('this.form.igv', $totals);
        self::assertStringNotContainsString('class="text-center"', $totals);
        self::assertStringContainsString(
            '<td class="text-end">'.PHP_EOL.'                                            <strong>SUBTOTAL</strong>',
            $totals
        );
        $this->assertFragmentsInOrder($totals, [
            '<strong>SUBTOTAL</strong>',
            '<strong>{{ this.form.subtotal | toDecimals }}</strong>',
            '<strong>TOTAL</strong>',
            '<strong>{{ this.form.total | toDecimals }}</strong>',
            '<td colspan="5"></td>',
        ]);
    }

    /** @test */
    public function sales_document_skill_limits_the_hotel_iva_rule_to_hotel_sale_note_summary(): void
    {
        $skill = file_get_contents(
            dirname(__DIR__, 2).'/.codex/skills/mantener-facturas-notas-venta-sin-boleta/SKILL.md'
        );

        self::assertNotFalse($skill);
        self::assertStringContainsString('source_module=HOTEL', $skill);
        self::assertStringContainsString('regla de presentación exclusiva de Hotel', $skill);
    }

    /** @test */
    public function hotel_product_modal_hides_discounts_charges_and_special_attributes_only_for_hotel(): void
    {
        $root = dirname(__DIR__, 2);
        $hotel = file_get_contents($root.'/modules/Hotel/Resources/assets/js/views/rooms/AddProductToRoom.vue');
        $sharedItemForm = file_get_contents($root.'/resources/js/views/tenant/documents/partials/item.vue');
        $skill = file_get_contents($root.'/.codex/skills/mantener-operacion-local-fiscal-pro9/SKILL.md');

        self::assertStringContainsString(':show-discounts-charges-attributes="false"', $hotel);
        self::assertStringContainsString("'showDiscountsChargesAttributes'", $sharedItemForm);
        self::assertStringContainsString(
            'v-if="showDiscounts && showDiscountsChargesAttributes !== false"',
            $sharedItemForm
        );
        self::assertStringContainsString('Mantener la regla vigente del flujo', $skill);
    }

    private function between(string $source, string $start, string $end): string
    {
        $startPosition = strpos($source, $start);
        self::assertNotFalse($startPosition, $start);
        $endPosition = strpos($source, $end, $startPosition);
        self::assertNotFalse($endPosition, $end);

        return substr($source, $startPosition, $endPosition - $startPosition);
    }

    private function assertFragmentsInOrder(string $source, array $fragments): void
    {
        $lastPosition = -1;
        foreach ($fragments as $fragment) {
            $position = strpos($source, $fragment);
            self::assertNotFalse($position, $fragment);
            self::assertGreaterThan($lastPosition, $position, $fragment);
            $lastPosition = $position;
        }
    }
}
