<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Modules\Item\Models\ProductVariable;
use Modules\Item\Services\ProductVariableCatalogParser;
use Tests\TestCase;

class ProductVariableCatalogParserTest extends TestCase
{
    /**
     * @var ProductVariableCatalogParser
     */
    protected $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ProductVariableCatalogParser();
    }

    public function test_groups_rows_by_attribute_name()
    {
        $parsed = $this->parser->parse($this->rows([
            ['Nombre', 'Tipo', 'Valor', 'Color', 'Orden', 'Activo'],
            ['Talla', 'lista', 'S', '', '1', '1'],
            ['Talla', 'lista', 'M', '', '2', '1'],
            ['Color', 'color', 'Rojo', '#E53935', '1', '1'],
        ]));

        $this->assertSame([], $parsed['errors']);
        $this->assertCount(2, $parsed['groups']);
        $this->assertSame('Talla', $parsed['groups'][0]['name']);
        $this->assertSame(ProductVariable::VALUE_TYPE_LIST, $parsed['groups'][0]['value_type']);
        $this->assertCount(2, $parsed['groups'][0]['values']);
        $this->assertSame('Color', $parsed['groups'][1]['name']);
        $this->assertSame(ProductVariable::VALUE_TYPE_COLOR, $parsed['groups'][1]['value_type']);
        $this->assertSame('#E53935', $parsed['groups'][1]['values'][0]['color']);
    }

    public function test_splits_comma_separated_values()
    {
        $parsed = $this->parser->parse($this->rows([
            ['Nombre', 'Tipo', 'Valor', 'Color', 'Orden', 'Activo'],
            ['Material', 'lista', 'Algodón, Poliéster, Lino', '', '1', '1'],
        ]));

        $this->assertSame([], $parsed['errors']);
        $this->assertSame(['Algodón', 'Poliéster', 'Lino'], array_column($parsed['groups'][0]['values'], 'value'));
        $this->assertSame([1, 2, 3], array_column($parsed['groups'][0]['values'], 'position'));
    }

    public function test_requires_color_for_color_type()
    {
        $parsed = $this->parser->parse($this->rows([
            ['Nombre', 'Tipo', 'Valor', 'Color', 'Orden', 'Activo'],
            ['Color', 'color', 'DesconocidoXYZ', '', '', '1'],
        ]));

        $this->assertNotEmpty($parsed['errors']);
        $this->assertStringContainsString('necesita color', $parsed['errors'][0]);
        $this->assertSame([], $parsed['groups']);
    }

    public function test_maps_color_names_when_hex_is_missing()
    {
        $parsed = $this->parser->parse($this->rows([
            ['Nombre', 'Tipo', 'Valor', 'Color', 'Orden', 'Activo'],
            ['Color', 'color', 'Rojo', '', '1', '1'],
        ]));

        $this->assertSame([], $parsed['errors']);
        $this->assertSame('#E53935', $parsed['groups'][0]['values'][0]['color']);
    }

    public function test_parses_inline_hex_in_value()
    {
        $parsed = $this->parser->parse($this->rows([
            ['Nombre', 'Tipo', 'Valor', 'Color', 'Orden', 'Activo'],
            ['Color', 'color', 'Rojo#FF0000, Azul#0000FF', '', '1', '1'],
        ]));

        $this->assertSame([], $parsed['errors']);
        $this->assertSame('Rojo', $parsed['groups'][0]['values'][0]['value']);
        $this->assertSame('#FF0000', $parsed['groups'][0]['values'][0]['color']);
        $this->assertSame('#0000FF', $parsed['groups'][0]['values'][1]['color']);
    }

    public function test_detects_duplicate_values_in_file()
    {
        $parsed = $this->parser->parse($this->rows([
            ['Nombre', 'Tipo', 'Valor', 'Color', 'Orden', 'Activo'],
            ['Talla', 'lista', 'S', '', '1', '1'],
            ['Talla', 'lista', 's', '', '2', '1'],
        ]));

        $this->assertNotEmpty($parsed['errors']);
        $this->assertStringContainsString('duplicado', $parsed['errors'][0]);
        $this->assertCount(1, $parsed['groups'][0]['values']);
    }

    public function test_rejects_mixed_types_for_the_same_attribute()
    {
        $parsed = $this->parser->parse($this->rows([
            ['Nombre', 'Tipo', 'Valor', 'Color', 'Orden', 'Activo'],
            ['Acabado', 'lista', 'Mate', '', '1', '1'],
            ['Acabado', 'color', 'Rojo', '#E53935', '2', '1'],
        ]));

        $this->assertNotEmpty($parsed['errors']);
        $this->assertStringContainsString('tipos mezclados', $parsed['errors'][0]);
        $this->assertSame([], $parsed['groups']);
    }

    public function test_parses_activo_flags()
    {
        $this->assertTrue($this->parser->parseActivo('sí'));
        $this->assertTrue($this->parser->parseActivo('1'));
        $this->assertFalse($this->parser->parseActivo('no'));
        $this->assertFalse($this->parser->parseActivo('0'));
        $this->assertNull($this->parser->parseActivo('tal vez'));
    }

    public function test_skips_empty_rows_and_counts_data_rows()
    {
        $parsed = $this->parser->parse($this->rows([
            ['Nombre', 'Tipo', 'Valor', 'Color', 'Orden', 'Activo'],
            ['Talla', 'lista', 'S', '', '1', '1'],
            ['', '', '', '', '', ''],
            [null, null, null, null, null, null],
            ['Talla', 'lista', 'M', '', '2', '1'],
        ]));

        $this->assertSame(2, $parsed['total']);
        $this->assertCount(2, $parsed['groups'][0]['values']);
        $this->assertSame([], $parsed['errors']);
    }

    public function test_accepts_header_aliases()
    {
        $parsed = $this->parser->parse($this->rows([
            ['Atributo', 'Tipo de valor', 'Valores', 'Hex', 'Posición', 'Estado'],
            ['Talla', 'Lista simple', 'S', '', '1', 'sí'],
        ]));

        $this->assertSame([], $parsed['errors']);
        $this->assertSame('Talla', $parsed['groups'][0]['name']);
        $this->assertSame('S', $parsed['groups'][0]['values'][0]['value']);
        $this->assertTrue($parsed['groups'][0]['values'][0]['active']);
    }

    public function test_reports_missing_name_and_value()
    {
        $parsed = $this->parser->parse($this->rows([
            ['Nombre', 'Tipo', 'Valor', 'Color', 'Orden', 'Activo'],
            ['', 'lista', 'S', '', '1', '1'],
            ['Talla', 'lista', '', '', '1', '1'],
        ]));

        $this->assertCount(2, $parsed['errors']);
        $this->assertStringContainsString('nombre', $parsed['errors'][0]);
        $this->assertStringContainsString('valor', $parsed['errors'][1]);
        $this->assertSame([], $parsed['groups']);
    }

    /**
     * @param array $rows
     * @return Collection
     */
    protected function rows(array $rows): Collection
    {
        return collect($rows)->map(function ($row) {
            return collect($row);
        });
    }
}
