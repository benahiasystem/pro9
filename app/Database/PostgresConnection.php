<?php

namespace App\Database;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Preserve scalar contracts and carry 64-bit integers losslessly to JavaScript. */
class PostgresConnection extends \Illuminate\Database\PostgresConnection
{
    use ValidatesBeforeCommit;
    private static ?\WeakMap $configuredStatements=null;
    private function configureStatements($pdo)
    {
        self::$configuredStatements ??= new \WeakMap();
        if (!isset(self::$configuredStatements[$pdo])) {
            $pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS,[PostgresStatement::class]);self::$configuredStatements[$pdo]=true;
        }
        return $pdo;
    }
    public function getPdo() {return $this->configureStatements(parent::getPdo());}
    public function getReadPdo() {return $this->configureStatements(parent::getReadPdo());}

    protected function causedByConcurrencyError(\Throwable $error)
    {
        return SqlErrors::retryable($error);
    }

    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new PostgresQueryGrammar());
    }

    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new PostgresSchemaGrammar());
    }

    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new PostgresSchemaBuilder($this);
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return array_map([self::class, 'transport'], parent::select($query, $bindings, $useReadPdo));
    }

    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        foreach (parent::cursor($query, $bindings, $useReadPdo) as $row) yield self::transport($row);
    }

    public static function transport($row)
    {
        foreach ($row as $key => $value) {
            $value=is_bool($value) ? (int) $value : IntegerTransport::value($value);
            if (is_object($row)) $row->$key = $value;
            else $row[$key] = $value;
        }
        return $row;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
