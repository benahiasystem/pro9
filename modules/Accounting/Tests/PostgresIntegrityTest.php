<?php

namespace Modules\Accounting\Tests;

use App\Database\PostgresBootstrap;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Support\PostgresIntegrity;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccountingPostgresFixture;

final class PostgresIntegrityTest extends TestCase
{
    private ?AccountingPostgresFixture $fixture = null;

    protected function tearDown(): void
    {
        try { $this->fixture?->close(); }
        finally { parent::tearDown(); }
    }

    private function start(bool $install = false): \Illuminate\Database\Connection
    {
        if (getenv('PRO9_POSTGRES_TESTS') !== '1') self::markTestSkipped('Utilice PostgreSQL temporal aislado.');
        $this->fixture = new AccountingPostgresFixture();
        $this->fixture->start($install);
        return DB::connection('tenant');
    }

    public function test_guard_installation_is_atomic_and_drop_is_reversible_without_orphan_functions(): void
    {
        $db = $this->start();
        $name = 'accounting_test_guard_'.str_repeat('long_identifier_',5);
        $function = PostgresBootstrap::name($name.'_fn');
        try {
            PostgresIntegrity::reject('accounting_missing_test_table', $name, 'UPDATE', 'Controlled guard');
            self::fail('La tabla inexistente debe abortar también la creación de función.');
        } catch (\Illuminate\Database\QueryException $error) {
            self::assertSame('42P01', $error->errorInfo[0]);
        }
        self::assertSame(0, (int) $db->selectOne('SELECT count(*) AS count FROM pg_proc WHERE pronamespace=\'public\'::regnamespace AND proname=?', [$function])->count);
        $db->statement('CREATE TABLE accounting_guard_test (id bigint PRIMARY KEY, value text)');
        $db->table('accounting_guard_test')->insert(['id' => 1, 'value' => 'original']);
        for ($cycle=0; $cycle<2; ++$cycle) {
            PostgresIntegrity::reject('accounting_guard_test', $name, 'UPDATE', 'Controlled guard');
            try {
                $db->transaction(fn() => $db->table('accounting_guard_test')->where('id',1)->update(['value' => 'rejected']));
                self::fail('El trigger debe rechazar la actualización.');
            } catch (\Illuminate\Database\QueryException $error) { self::assertSame('45000', $error->errorInfo[0]); }
            self::assertSame('original', $db->table('accounting_guard_test')->value('value'));
            PostgresIntegrity::drop('accounting_guard_test', $name);
            self::assertSame(0, (int) $db->selectOne('SELECT count(*) AS count FROM pg_proc WHERE pronamespace=\'public\'::regnamespace AND proname=?', [$function])->count);
            self::assertSame(1, $db->table('accounting_guard_test')->where('id',1)->update(['value' => 'original']));
        }
    }

    public function test_new_reversible_module_migrations_repeat_with_identical_catalogs(): void
    {
        $db = $this->start(true);
        $before = \App\Database\SchemaInspector::snapshot($db);
        $migrations = [];
        foreach (['2026_11_12_001200_preserve_accounting_supports.php','2026_11_12_001400_preserve_source_resolutions.php'] as $file) {
            $migrations[] = require dirname(__DIR__).'/Database/Migrations/'.$file;
        }
        $functions = fn() => $db->select("SELECT p.proname, pg_get_functiondef(p.oid) AS definition FROM pg_proc p WHERE p.pronamespace='public'::regnamespace AND p.proname LIKE 'accounting_%_fn' ORDER BY p.proname");
        $expectedFunctions = $functions();
        for ($cycle=0; $cycle<2; ++$cycle) {
            foreach (array_reverse($migrations) as $migration) $db->transaction(fn() => $migration->down());
            foreach ($migrations as $migration) $db->transaction(fn() => $migration->up());
            self::assertEquals($before, \App\Database\SchemaInspector::snapshot($db));
            self::assertEquals($expectedFunctions, $functions());
        }
        $names = array_column($db->select("SELECT c.relname FROM pg_class c WHERE c.relnamespace='public'::regnamespace AND c.relkind='i'"),'relname');
        self::assertCount(count(array_unique($names)), $names);
        foreach ($names as $name) self::assertLessThanOrEqual(63, strlen($name), $name);
    }
}
