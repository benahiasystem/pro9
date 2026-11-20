<?php

namespace App\Database;

// ########## INICIO CAMBIO POSTGRESQL 17
final class TextSearch
{
    /** SQL expressions must be produced by a grammar, never supplied by a request. */
    public static function expression(string $wrappedExpression): string
    {
        return 'public.unaccent(normalize(('.$wrappedExpression.')::text, NFC)) COLLATE "sab_search"';
    }

    public static function predicate(string $column, string $parameter, bool $negated = false): string
    {
        $text = "CASE WHEN pg_typeof($column) = 'pg_catalog.bool'::regtype THEN CASE ($column)::text WHEN 'true' THEN '1' WHEN 'false' THEN '0' END ELSE ($column)::text END";
        return self::expression($text).($negated ? ' NOT ILIKE ' : ' ILIKE ').self::expression($parameter);
    }

    public static function whereGuideNumber($query, string $column, string $pattern)
    {
        if (!in_array($column, ['guides', 'documents.guides', 'purchases.guides'], true)) throw new \InvalidArgumentException('Columna de guías no autorizada.');
        $builder = $query instanceof \Illuminate\Database\Eloquent\Builder ? $query->getQuery() : $query;
        $field = $builder->getGrammar()->wrap($column);
        $array = "CASE json_typeof($field) WHEN 'array' THEN $field WHEN 'object' THEN json_build_array($field) ELSE '[]'::json END";
        return $query->whereRaw('EXISTS (SELECT 1 FROM json_array_elements('.$array.') AS sab_guide(value) WHERE '.self::predicate("sab_guide.value->>'number'", '?').')', [$pattern]);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
