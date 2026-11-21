<?php

namespace App\Database;

use Illuminate\Database\Schema\PostgresBuilder;

class PostgresSchemaBuilder extends PostgresBuilder
{
    public function getColumnListing($table)
    {
        [$database, $schema, $table] = $this->parseSchemaAndTable($table);
        $table = $this->connection->getTablePrefix().$table;

        // Keep the privilege-filtered listing used by hasColumn during model hydration.
        $results = $this->connection->selectFromWriteConnection(
            $this->grammar->compileColumnListing(),
            [$database, $schema, $table]
        );

        return $this->connection->getPostProcessor()->processColumnListing($results);
    }
}
