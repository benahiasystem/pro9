<?php

namespace App\Database;

use Illuminate\Support\Fluent;

// ########## INICIO CAMBIO POSTGRESQL 17
class PostgresSchemaGrammar extends \Illuminate\Database\Schema\Grammars\PostgresGrammar
{
    public function compileTableExists()
    {
        // Same BASE TABLE visibility as PostgreSQL 17 information_schema.tables,
        // without projecting metadata/type joins during each request bootstrap.
        return <<<'SQL'
SELECT 1
FROM pg_catalog.pg_class c
JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace
WHERE current_database()=? AND n.nspname=? AND c.relname=?
  AND c.relkind IN ('r','p') AND n.oid<>pg_catalog.pg_my_temp_schema()
  AND NOT pg_catalog.pg_is_other_temp_schema(n.oid)
  AND (pg_catalog.pg_has_role(c.relowner,'USAGE')
       OR pg_catalog.has_table_privilege(c.oid,'SELECT,INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER')
       OR pg_catalog.has_any_column_privilege(c.oid,'SELECT,INSERT,UPDATE,REFERENCES'))
LIMIT 1
SQL;
    }

    public function compileColumnListing()
    {
        // Schema::hasColumn is on the hot path of exact-value model hydration.
        // Preserve information_schema visibility without its type/domain/default joins.
        return <<<'SQL'
SELECT a.attname AS column_name
FROM pg_catalog.pg_attribute a
JOIN pg_catalog.pg_class c ON c.oid=a.attrelid
JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace
WHERE current_database()=? AND n.nspname=? AND c.relname=?
  AND c.relkind IN ('r','v','f','p') AND a.attnum>0 AND NOT a.attisdropped
  AND NOT pg_catalog.pg_is_other_temp_schema(n.oid)
  AND (pg_catalog.pg_has_role(c.relowner,'USAGE')
       OR pg_catalog.has_column_privilege(c.oid,a.attnum,'SELECT,INSERT,UPDATE,REFERENCES'))
ORDER BY a.attnum
SQL;
    }
    private function isTechnicalReference(Fluent $column): bool
    {
        return str_ends_with($column->name, '_id') || in_array($column->name, [
            'accepted_by', 'approved_by', 'created_by', 'posted_by', 'prepared_by', 'recorded_by', 'reopened_by', 'reopening_prepared_by', 'resolved_by', 'reviewed_by', 'uploaded_by', 'validated_by', 'withdrawn_by'
        ], true);
    }

    protected function typeInteger(Fluent $column)
    {
        if ($column->autoIncrement) return 'bigserial';
        if ($this->isTechnicalReference($column)) {
            return $column->unsigned ? 'bigint check ('.$this->wrap($column->name).' >= 0)' : 'bigint';
        }
        return $column->unsigned ? 'bigint check ('.$this->wrap($column->name).' between 0 and 4294967295)' : parent::typeInteger($column);
    }

    protected function typeBigInteger(Fluent $column)
    {
        if ($column->autoIncrement) return 'bigserial';
        if ($column->unsigned && $this->isTechnicalReference($column)) return 'bigint check ('.$this->wrap($column->name).' >= 0)';
        return $column->unsigned ? 'numeric(20,0) check ('.$this->wrap($column->name).' between 0 and 18446744073709551615)' : parent::typeBigInteger($column);
    }

    protected function typeMediumInteger(Fluent $column)
    {
        if ($column->autoIncrement || $this->isTechnicalReference($column)) return $this->typeInteger($column);
        return 'integer check ('.$this->wrap($column->name).' between '.($column->unsigned ? '0 and 16777215)' : '-8388608 and 8388607)');
    }

    protected function typeTinyInteger(Fluent $column)
    {
        if ($column->autoIncrement) return 'bigserial';
        return 'smallint check ('.$this->wrap($column->name).' between '.($column->unsigned ? '0 and 255)' : '-128 and 127)');
    }

    protected function typeSmallInteger(Fluent $column)
    {
        if ($column->autoIncrement) return 'bigserial';
        return $column->unsigned ? 'integer check ('.$this->wrap($column->name).' between 0 and 65535)' : parent::typeSmallInteger($column);
    }

    protected function typeString(Fluent $column)
    {
        $column->collation ??= $column->name === 'migration' || preg_match('/(?:password|token|secret|uuid|hash|external_id|api_key)/', $column->name) ? 'C' : 'sab_ci_ai';
        return parent::typeString($column);
    }

    protected function typeText(Fluent $column)
    {
        $column->collation ??= 'sab_ci_ai';
        return parent::typeText($column);
    }

    protected function typeChar(Fluent $column)
    {
        $column->collation ??= 'sab_ci_ai';
        return parent::typeChar($column);
    }
    protected function typeMediumText(Fluent $column) { return $this->typeText($column); }
    protected function typeLongText(Fluent $column) { return $this->typeText($column); }
    protected function typeTimestamp(Fluent $column)
    {
        $type=parent::typeTimestamp($column);
        // MySQL CURRENT_TIMESTAMP is evaluated at the statement, including inside a transaction.
        $now=($column->precision ?? 0)===0 ? "date_trunc('second', statement_timestamp())" : 'statement_timestamp()';
        return str_replace(' default CURRENT_TIMESTAMP',' default '.$now,$type);
    }
    protected function modifyCollate(\Illuminate\Database\Schema\Blueprint $blueprint, Fluent $column)
    {
        if ($column->collation && str_starts_with($column->collation, 'utf8')) $column->collation = str_ends_with($column->collation, '_bin') ? 'C' : 'sab_ci_ai';
        return parent::modifyCollate($blueprint, $column);
    }

    protected function modifyDefault(\Illuminate\Database\Schema\Blueprint $blueprint, Fluent $column)
    {
        if ($column->type === 'boolean' && $column->default !== null) return ' default '.($column->default ? 'true' : 'false');
        return parent::modifyDefault($blueprint, $column);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
