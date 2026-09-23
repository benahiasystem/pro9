<?php
namespace Tests\Unit;
use App\Services\Fiscal\FiscalSaleNoteItemComparison;
use PHPUnit\Framework\TestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalSaleNoteItemComparisonTest extends TestCase
{
    private function row(array $changes = []): array
    {
        return array_replace(['item_id' => 1, 'quantity' => '2.0000', 'unit_value' => '100.000000', 'unit_price' => '116.000000',
            'price_type_id' => '01', 'affectation_igv_type_id' => '10', 'percentage_igv' => '16.00', 'quantity_factor' => '1.0000',
            'total_value' => '200.00', 'total_base_igv' => '200.00', 'total_igv' => '32.00', 'total_taxes' => '32.00', 'total' => '232.00',
            'item' => ['unit_type_id' => 'UND'], 'discounts' => [], 'charges' => []], $changes);
    }
    public function test_equivalent_grouped_rows_and_decimal_representations_are_accepted(): void
    {
        $group = $this->row(['quantity' => 4, 'unit_value' => 100, 'unit_price' => 116, 'total_value' => 400,
            'total_base_igv' => 400, 'total_igv' => 64, 'total_taxes' => 64, 'total' => 464]);
        FiscalSaleNoteItemComparison::assertEquivalent([$this->row(), $this->row()], [$group]);
        self::assertTrue(true);
    }
    public function test_json_source_snapshots_and_adjustments_compare_to_input_arrays(): void
    {
        $adjustment = ['discount_type_id' => '00', 'factor' => '0.100000', 'base' => '200.00', 'amount' => '20.00'];
        $source = $this->row(['item' => '{"unit_type_id":"UND"}', 'discounts' => json_encode([$adjustment])]);
        $input = $this->row(['discounts' => [array_replace($adjustment, ['description' => 'Visible description', 'amount' => 20])]]);
        FiscalSaleNoteItemComparison::assertEquivalent([(object) $source], [$input]);
        self::assertTrue(true);
    }
    /** @dataProvider changedEconomics */
    public function test_changed_line_is_rejected_even_if_document_total_is_unchanged(array $change): void
    {
        $this->expectException(\DomainException::class);
        FiscalSaleNoteItemComparison::assertEquivalent([$this->row()], [$this->row($change)]);
    }
    public static function changedEconomics(): array
    {
        return [[['unit_value' => 99]], [['unit_price' => 115]], [['percentage_igv' => 8]], [['affectation_igv_type_id' => '20']],
            [['total_base_igv' => 199]], [['total_igv' => 31]], [['total_taxes' => 31]], [['total_value' => 199]],
            [['total_discount' => 1]], [['total_charge' => 1]], [['total_other_taxes' => 1]], [['total' => 231]],
            [['item' => ['unit_type_id' => 'SERV']]], [['quantity_factor' => 2]], [['price_type_id' => '02']],
            [['quantity' => 1]], [['discounts' => [['discount_type_id' => '00', 'amount' => 1]]]],
            [['charges' => [['charge_type_id' => '50', 'amount' => 1]]]], [['unit_price' => 'invalid']]];
    }
    public function test_opposite_tax_changes_cannot_cancel_across_different_prices(): void
    {
        $first = $this->row(); $second = $this->row(['unit_value' => 200, 'unit_price' => 232]);
        $this->expectException(\DomainException::class);
        FiscalSaleNoteItemComparison::assertEquivalent([$first, $second], [array_replace($first, ['total_igv' => 31]), array_replace($second, ['total_igv' => 33])]);
    }
    public function test_supported_small_float_price_is_normalized_without_scientific_notation(): void
    {
        FiscalSaleNoteItemComparison::assertEquivalent([$this->row(['unit_value' => '0.000001'])], [$this->row(['unit_value' => 0.000001])]);
        self::assertTrue(true);
    }
    public function test_adjusted_rows_cannot_be_merged_while_losing_adjustment_instances(): void
    {
        $source = $this->row(['discounts' => [['discount_type_id' => '00', 'base' => 200, 'amount' => 20, 'factor' => '0.10']], 'total_discount' => 20]);
        $merged = array_replace($source, ['quantity' => 4, 'total_value' => 400, 'total_base_igv' => 400,
            'total_igv' => 64, 'total_taxes' => 64, 'total_discount' => 40, 'total' => 464]);
        $this->expectException(\DomainException::class);
        FiscalSaleNoteItemComparison::assertEquivalent([$source, $source], [$merged]);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
