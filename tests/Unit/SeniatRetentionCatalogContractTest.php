<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SeniatRetentionCatalogContractTest extends TestCase
{
    public function test_initial_catalogs_match_the_manual_codes_and_keep_percentages_textual(): void
    {
        $root = dirname(__DIR__, 2);
        $tables = (require $root.'/database/seeders/data/tenant_initial_data.php')['tables'];
        $concepts = $tables['cat_retention_concept']['rows'];
        $types = $tables['cat_retention_types']['rows'];

        self::assertSame(['id'], $tables['cat_retention_concept']['key_columns']);
        self::assertSame(['id'], $tables['cat_retention_types']['key_columns']);
        self::assertSame(array_map(static fn (int $code): string => sprintf('%03d', $code), range(1, 86)), array_column($concepts, 'id'));
        self::assertCount(86, array_unique(array_column($concepts, 'id')));
        foreach ($concepts as $concept) {
            self::assertSame(['id', 'description', 'percentage_label'], array_keys($concept));
            self::assertNotSame('', $concept['description']);
            self::assertNotSame('', $concept['percentage_label']);
        }

        $byCode = array_column($concepts, null, 'id');
        self::assertSame('Variable', $byCode['001']['percentage_label']);
        self::assertSame('3 %', $byCode['002']['percentage_label']);
        self::assertSame('4,95 %', $byCode['024']['percentage_label']);
        self::assertSame('1 %', $byCode['053']['percentage_label']);
        self::assertStringContainsString('15 %', $byCode['005']['percentage_label']);
        self::assertStringContainsString('22 %', $byCode['005']['percentage_label']);
        self::assertStringContainsString('34 %', $byCode['005']['percentage_label']);

        self::assertSame([
            ['id' => '01', 'description' => 'Dividendo en acciones', 'abbreviation' => 'DA'],
            ['id' => '02', 'description' => 'Dividendo en efectivo', 'abbreviation' => 'DE'],
            ['id' => '03', 'description' => 'Venta de acciones', 'abbreviation' => 'VA'],
        ], $types);
    }
}
