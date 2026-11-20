<?php

namespace App\Database;

use Illuminate\Database\Connection;

// ########## INICIO CAMBIO POSTGRESQL 17
final class ForeignKeyIndexes
{
    public static function ensure(Connection $db, string $table, array $columns, string $name): void
    {
        $indexes = [];
        foreach (SchemaInspector::indexes($db, $table) as $index) $indexes[$index->name][] = $index->column;
        foreach ($indexes as $existing) if (array_slice($existing, 0, count($columns)) === $columns) return;
        $index = PostgresBootstrap::identifier(PostgresBootstrap::name($name));
        $db->statement('CREATE INDEX '.$index.' ON '.PostgresBootstrap::identifier($table).' ('.implode(',', array_map([PostgresBootstrap::class, 'identifier'], $columns)).')');
        $db->statement('COMMENT ON INDEX '.$index." IS 'pro9:foreign_key_index'");
    }
    public static function remove(Connection $db, string $name): void
    {
        $name = PostgresBootstrap::name($name);
        $own = $db->selectOne("SELECT c.oid FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relkind='i' AND c.relname=? AND obj_description(c.oid,'pg_class')='pro9:foreign_key_index'", [$name]);
        if ($own) $db->statement('DROP INDEX '.PostgresBootstrap::identifier($name));
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
