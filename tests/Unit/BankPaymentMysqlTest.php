<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;

/** Focused schema verification, executed separately from the expanded fiscal suite. */
final class BankPaymentMysqlTest extends FiscalEmissionSchemaTest
{
    public function test_bank_payment_schema_and_rollback_in_disposable_mysql(): void
    {
        $db = DB::connection('tenant');
        $paths = glob(database_path('migrations/tenant/*.php'));
        sort($paths);
        $migrations = array_map(static fn ($path) => require $path, $paths);
        $schemas = null;
        for ($cycle=0; $cycle<2; $cycle++) {
            foreach ($migrations as $migration) $migration->up();
            (new \Database\Seeders\TenantMigrationDataSeeder())->run();
            self::assertTrue($db->getSchemaBuilder()->hasTable('payment_commissions'));
            foreach (['bank_account_id', 'commission_percentage', 'minimum_commission'] as $column) {
                self::assertTrue($db->getSchemaBuilder()->hasColumn('payment_method_types', $column));
            }
            self::assertSame(0, $db->table('payment_method_types')->where('collection_kind','account')->where('is_active',1)->count());
            self::assertSame(0, $db->table('payment_method_types')->whereNotNull('bank_account_id')->count());
            $current = array_map(fn ($table) => (array)$db->selectOne('SHOW CREATE TABLE `'.$table.'`'), ['payment_method_types','payment_commissions']);
            if ($schemas !== null) self::assertSame($schemas, $current);
            $schemas = $current;
            foreach (array_reverse($migrations) as $migration) $migration->down();
            self::assertFalse($db->getSchemaBuilder()->hasTable('payment_commissions'));
        }
    }
}
