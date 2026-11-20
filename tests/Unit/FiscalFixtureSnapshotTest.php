<?php

namespace Tests\Unit;

use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\FiscalFixtureSnapshot;

final class FiscalFixtureSnapshotTest extends TestCase
{
    /** @dataProvider operations */
    public function test_non_temporary_database_is_rejected_before_any_query(string $operation): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('getDatabaseName')->willReturn('tenancy_real');
        $db->expects(self::never())->method('select');
        $this->expectException(\LogicException::class);
        FiscalFixtureSnapshot::$operation($db);
    }

    public function operations(): array
    {
        return [['capture'], ['restore']];
    }

    /** @dataProvider operations */
    public function test_connection_pointing_to_a_different_database_is_rejected(string $operation): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('getDatabaseName')->willReturn('pro9_fiscal_test_123456abcdef');
        $db->method('selectOne')->willReturn((object) ['name' => 'tenancy_real']);
        $db->expects(self::never())->method('select');
        $this->expectException(\LogicException::class);
        FiscalFixtureSnapshot::$operation($db);
    }
}
