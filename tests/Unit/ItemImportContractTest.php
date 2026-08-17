<?php

// ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
namespace Tests\Unit;

use App\Support\ItemImport\ItemImportContract;
use PHPUnit\Framework\TestCase;

class ItemImportContractTest extends TestCase
{
    /** @var ItemImportContract */
    private $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contract = new ItemImportContract();
    }

    /** @test */
    public function it_accepts_the_twenty_core_headers_and_optional_image_header(): void
    {
        self::assertSame([], $this->contract->validateHeaders(ItemImportContract::HEADERS));

        $headers = array_merge(ItemImportContract::HEADERS, [ItemImportContract::OPTIONAL_IMAGE_HEADER]);
        self::assertSame([], $this->contract->validateHeaders($headers));
    }

    /** @test */
    public function it_rejects_changed_or_displaced_headers(): void
    {
        $headers = ItemImportContract::HEADERS;
        $headers[3] = 'Código Sunat';

        $errors = $this->contract->validateHeaders($headers);

        self::assertSame(1, $errors[0]['row']);
        self::assertSame(4, $errors[0]['column']);
        self::assertStringContainsString('Código', $errors[0]['messages'][0]);
    }

    /** @test */
    public function it_accepts_a_row_compatible_with_the_items_schema_and_catalogs(): void
    {
        $errors = $this->contract->validateRow(
            $this->validRow(),
            2,
            $this->catalogs()
        );

        self::assertSame([], $errors);
    }

    /** @test */
    public function it_rejects_values_that_would_break_database_compatibility(): void
    {
        $row = $this->validRow();
        $row[1] = str_repeat('A', 31);
        $row[5] = 'VED';
        $row[6] = '10,50';
        $row[10] = null;
        $row[11] = 0;
        $row[17] = 'LOTE-1';
        $row[18] = '2026-08-12';

        $errors = $this->contract->validateRow($row, 7, $this->catalogs(), true);
        $columns = array_column($errors, 'column');

        self::assertContains(2, $columns);
        self::assertContains(6, $columns);
        self::assertContains(7, $columns);
        self::assertContains(11, $columns);
        self::assertContains(12, $columns);
        self::assertContains(19, $columns);
    }

    /**
     * @test
     * @dataProvider incompatibleDatabaseValues
     */
    public function it_validates_each_excel_value_against_its_database_contract(
        int $index,
        $value,
        int $expectedColumn,
        bool $requiresLot = false
    ): void {
        $row = $this->validRow();
        $row[$index] = $value;

        if ($requiresLot) {
            $row[17] = 'LOTE-1';
        }

        $errors = $this->contract->validateRow($row, 9, $this->catalogs());

        self::assertContains($expectedColumn, array_column($errors, 'column'));
    }

    public function incompatibleDatabaseValues(): array
    {
        return [
            'description required' => [0, null, 1],
            'internal id varchar 30' => [1, str_repeat('I', 31), 2],
            'model varchar 100' => [2, str_repeat('M', 101), 3],
            'item code exact SUNAT format' => [3, 'ABC-123', 4],
            'unit catalog' => [4, 'INVALID', 5],
            'currency catalog' => [5, 'VED', 6],
            'sale price decimal 16 6' => [6, '12345678901.123456', 7],
            'sale affectation catalog' => [7, '99', 8],
            'has iva boolean text' => [8, 'VERDADERO', 9],
            'purchase price decimal 16 6' => [9, '1.1234567', 10],
            'purchase affectation required' => [10, null, 11],
            'stock decimal 16 4' => [11, '1.12345', 12],
            'stock min decimal 12 2' => [12, '1.123', 13],
            'category varchar 255' => [13, str_repeat('C', 256), 14],
            'brand varchar 255' => [14, str_repeat('B', 256), 15],
            'name varchar 1000' => [15, str_repeat('N', 1001), 16],
            'second name varchar 600' => [16, str_repeat('S', 601), 17],
            'lot varchar 255' => [17, str_repeat('L', 256), 18],
            'date must be excel serial' => [18, '2026-08-12', 19, true],
            'barcode varchar 150' => [19, str_repeat('B', 151), 20],
            'image url' => [20, 'ftp://example.com/image.jpg', 21],
        ];
    }

    /** @test */
    public function it_accepts_a_lot_with_a_native_excel_date_serial(): void
    {
        $row = $this->validRow();
        $row[17] = 'LOTE-1';
        $row[18] = 45881;

        self::assertSame([], $this->contract->validateRow($row, 2, $this->catalogs()));
    }

    private function catalogs(): array
    {
        return [
            'unit_type_ids' => ['NIU'],
            'currency_type_ids' => ['VES', 'USD'],
            'affectation_igv_type_ids' => ['10', '20'],
            'existing_internal_ids' => [],
        ];
    }

    private function validRow(): array
    {
        return [
            'Producto de prueba',
            'ITEM-001',
            'MODELO-1',
            '12345678',
            'NIU',
            'VES',
            10.50,
            '10',
            'SI',
            5.25,
            '10',
            1,
            0,
            'Categoría',
            'Marca',
            'Descripción adicional',
            'Nombre secundario',
            null,
            null,
            'BAR-001',
            'https://example.com/producto.jpg',
        ];
    }
}
// ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
