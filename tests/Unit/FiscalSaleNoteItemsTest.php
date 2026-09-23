<?php
namespace Tests\Unit;

use App\Services\Fiscal\FiscalSaleNoteItems;
use PHPUnit\Framework\TestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalSaleNoteItemsTest extends TestCase
{
    private function row(array $changes = []): array
    {
        return array_replace(['id' => 1, 'sale_note_id' => 1, 'item_id' => 8, 'quantity' => '1.000000',
            'unit_value' => '10.000000', 'unit_price' => '11.600000', 'affectation_igv_type_id' => '10', 'percentage_igv' => '16.00',
            'total_value' => '10.00', 'total_base_igv' => '10.00', 'total_igv' => '1.60', 'total_taxes' => '1.60', 'total' => '11.60',
            'discounts' => [], 'charges' => [], 'item' => ['unit_type_id' => 'UND', 'description' => 'Source product']], $changes);
    }
    public function test_identical_rows_sum_stored_amounts_and_quantities_without_binary_rounding(): void
    {
        $rows = [$this->row(), $this->row(['id' => 2, 'sale_note_id' => 2])];
        $group = FiscalSaleNoteItems::group($rows);
        self::assertCount(1, $group);
        self::assertSame('2.000000', $group[0]['quantity']);
        self::assertSame('23.200000', $group[0]['total']);
        self::assertSame('3.200000', $group[0]['total_igv']);
        self::assertSame('1.000000', $rows[0]['quantity']);
    }
    /** @dataProvider distinctEconomics */
    public function test_different_economics_and_details_keep_their_own_rows(array $change): void
    {
        $rows = [$this->row(), $this->row($change)];
        self::assertSame($rows, FiscalSaleNoteItems::group($rows));
    }
    public static function distinctEconomics(): array
    {
        return [[['unit_price' => '23.20']], [['unit_value' => '20']], [['percentage_igv' => '8']],
            [['affectation_igv_type_id' => '20']], [['warehouse_id' => 2]],
            [['discounts' => [['amount' => '1.00']]]], [['charges' => [['amount' => '1.00']]]],
            [['item' => ['unit_type_id' => 'SERV']]], [['item' => ['presentation' => ['id' => 2]]]],
            [['item' => ['lots' => [['id' => 1]]]]], [['IdLoteSelected' => [['id' => 1]]]],
            [['attributes' => [['description' => 'Unique characteristic']]]]];
    }
    public function test_repeated_detailed_rows_are_not_merged(): void
    {
        $row = $this->row(['discounts' => [['amount' => '1.00']]]);
        self::assertSame([$row, $row], FiscalSaleNoteItems::group([$row, $row]));
    }
    public function test_eloquent_object_snapshots_keep_nested_details(): void
    {
        $row = $this->row(['item' => (object) ['presentation' => (object) ['id' => 2]]]);
        $group = FiscalSaleNoteItems::group([$row, $row]);
        self::assertCount(2, $group);
        self::assertSame(2, $group[0]['item']['presentation']['id']);
    }
    public function test_preserves_stored_rounding_instead_of_repricing_merged_quantity(): void
    {
        $row = $this->row(['quantity' => '0.333333', 'unit_price' => '0.10', 'total' => '0.03']);
        $group = FiscalSaleNoteItems::group([$row, $row, $row]);
        self::assertSame('0.090000', $group[0]['total']);
        self::assertSame('0.999999', $group[0]['quantity']);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
