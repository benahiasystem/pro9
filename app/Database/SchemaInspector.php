<?php

namespace App\Database;

use Illuminate\Database\Connection;

// ########## INICIO CAMBIO POSTGRESQL 17
/** PostgreSQL catalog inspection shared by acceptance tests and recovery. */
final class SchemaInspector
{
    public static function tables(Connection $db): array
    {
        return array_map(static fn ($r) => $r->name, $db->select("SELECT tablename AS name FROM pg_tables WHERE schemaname='public' ORDER BY tablename"));
    }
    public static function columns(Connection $db, string $table, ?string $column = null): array
    {
        return $db->select(<<<'SQL'
SELECT a.attname AS name, replace(replace(format_type(a.atttypid,a.atttypmod), 'character varying', 'varchar'), 'character(', 'char(') AS type,
       NOT a.attnotnull AS nullable, EXISTS (SELECT 1 FROM pg_index pi WHERE pi.indrelid=c.oid AND pi.indisprimary AND a.attnum=ANY(pi.indkey)) AS primary, pg_get_expr(d.adbin,d.adrelid) AS "default",
       a.attgenerated <> '' AS generated, a.attidentity <> '' OR pg_get_expr(d.adbin,d.adrelid) LIKE 'nextval(%' AS has_sequence
FROM pg_attribute a JOIN pg_class c ON c.oid=a.attrelid JOIN pg_namespace n ON n.oid=c.relnamespace
LEFT JOIN pg_attrdef d ON d.adrelid=a.attrelid AND d.adnum=a.attnum
WHERE n.nspname='public' AND c.relname=? AND a.attnum>0 AND NOT a.attisdropped
SQL.($column !== null ? ' AND a.attname=?' : '').' ORDER BY a.attnum', $column !== null ? [$table, $column] : [$table]);
    }
    public static function column(Connection $db, string $table, string $column): ?object
    {
        return self::columns($db, $table, $column)[0] ?? null;
    }
    public static function indexes(Connection $db, string $table, ?string $name = null): array
    {
        return $db->select(<<<'SQL'
SELECT idx.relname AS name, i.indisunique::int AS unique, (NOT i.indisunique)::int AS non_unique, i.indisprimary::int AS primary,
       a.attname AS column, k.ordinality AS position
FROM pg_index i JOIN pg_class t ON t.oid=i.indrelid JOIN pg_namespace n ON n.oid=t.relnamespace
JOIN pg_class idx ON idx.oid=i.indexrelid
CROSS JOIN LATERAL unnest(i.indkey) WITH ORDINALITY k(attnum,ordinality)
LEFT JOIN pg_attribute a ON a.attrelid=t.oid AND a.attnum=k.attnum
WHERE n.nspname='public' AND t.relname=?
SQL.($name === 'PRIMARY' ? ' AND i.indisprimary' : ($name !== null ? ' AND idx.relname=?' : '')).' ORDER BY idx.relname,k.ordinality', $name !== null && $name !== 'PRIMARY' ? [$table, PostgresBootstrap::name($name)] : [$table]);
    }
    public static function foreignKeys(Connection $db, ?string $table = null): array
    {
        return $db->select(<<<'SQL'
SELECT t.relname AS table, a.attname AS column, c.conname AS name, rt.relname AS reference_table,
       ra.attname AS reference_column, c.condeferrable::int AS deferrable, c.condeferred::int AS initially_deferred,
       c.confdeltype AS delete_action, c.confupdtype AS update_action, s.i AS position
FROM pg_constraint c JOIN pg_class t ON t.oid=c.conrelid JOIN pg_namespace n ON n.oid=t.relnamespace
JOIN pg_class rt ON rt.oid=c.confrelid CROSS JOIN LATERAL generate_subscripts(c.conkey,1) s(i)
JOIN pg_attribute a ON a.attrelid=t.oid AND a.attnum=c.conkey[s.i]
JOIN pg_attribute ra ON ra.attrelid=rt.oid AND ra.attnum=c.confkey[s.i]
WHERE n.nspname='public' AND c.contype='f'
SQL.($table !== null ? ' AND t.relname=?' : '').' ORDER BY t.relname,c.conname,s.i', $table !== null ? [$table] : []);
    }
    public static function snapshot(Connection $db): array
    {
        $result = [];
        foreach (self::tables($db) as $table) $result[$table] = ['columns' => array_map(static fn ($r)=>(array)$r, self::columns($db, $table)), 'indexes' => array_map(static fn ($r)=>(array)$r, self::indexes($db, $table)), 'foreign_keys' => array_map(static fn ($r)=>(array)$r, self::foreignKeys($db, $table))];
        return $result;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
