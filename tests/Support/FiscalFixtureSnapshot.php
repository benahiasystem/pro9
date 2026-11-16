<?php

namespace Tests\Support;

use Illuminate\Database\Connection;

/** Referencia privada del proceso para crear fixtures sin repetir las migraciones. */
final class FiscalFixtureSnapshot
{
    private static ?array $tables = null;

    public static function capture(Connection $db): void
    {
        self::assertTemporaryDatabase($db);
        $snapshot = [];
        foreach ($db->select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']) as $entry) {
            $name = array_values((array) $entry)[0];
            $quoted = '`' . str_replace('`', '``', $name) . '`';
            $ddl = array_values((array) $db->selectOne('SHOW CREATE TABLE ' . $quoted))[1];
            $columns = [];
            foreach ($db->select('SHOW COLUMNS FROM ' . $quoted) as $column) {
                if (!preg_match('/\b(?:VIRTUAL|STORED) GENERATED\b/i', $column->Extra)) $columns[] = $column->Field;
            }
            $fields = implode(', ', array_map(static fn ($column) => '`' . str_replace('`', '``', $column) . '`', $columns));
            $rows = $fields === '' ? [] : array_map(static fn ($row) => (array) $row, $db->select('SELECT ' . $fields . ' FROM ' . $quoted));
            $snapshot[] = ['name' => $name, 'ddl' => $ddl, 'rows' => $rows];
        }
        self::$tables = $snapshot;
    }

    public static function restore(Connection $db): bool
    {
        self::assertTemporaryDatabase($db);
        if (self::$tables === null) return false;
        if ($db->select('SHOW TABLES')) throw new \LogicException('El fixture fiscal requiere una base temporal vacía.');
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (self::$tables as $table) $db->unprepared($table['ddl']);
            foreach (self::$tables as $table) {
                foreach (array_chunk($table['rows'], 100) as $rows) $db->table($table['name'])->insert($rows);
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
        return true;
    }

    private static function assertTemporaryDatabase(Connection $db): void
    {
        $name = (string) $db->getDatabaseName();
        if (!preg_match('/^sab_fiscal_test_[a-f0-9]{12}$/D', $name)) {
            throw new \LogicException('El fixture fiscal sólo admite bases temporales de prueba.');
        }
        $current = $db->selectOne('SELECT DATABASE() AS name');
        if (!$current || $current->name !== $name) throw new \LogicException('La conexión fiscal no apunta a su base temporal.');
    }
}
