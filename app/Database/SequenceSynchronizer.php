<?php

namespace App\Database;

use Illuminate\Database\Connection;

// ########## INICIO CAMBIO POSTGRESQL 17
final class SequenceSynchronizer
{
    /** Installation/seed/restore only: callers must have stopped concurrent writers. */
    public static function sync(Connection $db, ?string $table = null): void
    {
        if ($db->getDriverName() !== 'pgsql') return;
        $columns = $db->select("SELECT table_name, column_name FROM information_schema.columns WHERE table_schema='public' AND (column_default LIKE 'nextval(%' OR is_identity='YES')".($table ? ' AND table_name=?' : ''), $table ? [$table] : []);
        foreach ($columns as $column) {
            $quotedTable = PostgresBootstrap::identifier($column->table_name);
            $quotedColumn = PostgresBootstrap::identifier($column->column_name);
            $sequence = $db->selectOne('SELECT pg_get_serial_sequence(?, ?) AS name', ['public.'.$quotedTable, $column->column_name])->name;
            if ($sequence) $db->selectOne("SELECT setval(?::regclass, GREATEST(COALESCE(MAX($quotedColumn), 1), 1), MAX($quotedColumn) IS NOT NULL) FROM public.$quotedTable", [$sequence]);
        }
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
