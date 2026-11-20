<?php
namespace App\Database;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Strip only PostgreSQL CHAR padding, preserving VARCHAR, JSON and decimal bytes. */
final class PostgresStatement extends \PDOStatement
{
    private array $charTypes=[];
    protected function __construct() {}
    public function execute(?array $params=null): bool {$this->charTypes=[];return parent::execute($params);}
    private function isChar(int $index): bool
    {
        return $this->charTypes[$index] ??= ($this->getColumnMeta($index)['native_type'] ?? '')==='bpchar';
    }
    private function normalizeRow($row)
    {
        if (!is_object($row) && !is_array($row)) return $row;
        $position=0;$direct=count((array)$row)===$this->columnCount();
        foreach ($row as $key=>$value) {
            $index=is_int($key) ? $key : $position;$position++;
            // PDO PostgreSQL metadata performs catalog queries. Normal values need none.
            if (!is_string($value) || !str_ends_with($value,' ')) continue;
            if (!is_int($key) && !$direct) {
                $index=null;
                for ($i=0;$i<$this->columnCount();$i++) if (($this->getColumnMeta($i)['name'] ?? null)===$key) $index=$i;
                if ($index===null) continue;
            }
            if ($this->isChar($index)) {
                if (is_object($row)) $row->$key=rtrim($value,' '); else $row[$key]=rtrim($value,' ');
            }
        }
        return $row;
    }
    public function fetch(int $mode=\PDO::FETCH_DEFAULT,int $orientation=\PDO::FETCH_ORI_NEXT,int $offset=0): mixed {return $this->normalizeRow(parent::fetch($mode,$orientation,$offset));}
    public function fetchAll(int $mode=\PDO::FETCH_DEFAULT,mixed ...$args): array
    {
        $rows=parent::fetchAll($mode,...$args);
        if ($mode===\PDO::FETCH_COLUMN) return array_map(fn ($value)=>is_string($value) && str_ends_with($value,' ') && $this->isChar($args[0] ?? 0) ? rtrim($value,' ') : $value,$rows);
        return array_map(fn ($row)=>$this->normalizeRow($row),$rows);
    }
    public function fetchColumn(int $column=0): mixed
    {
        $value=parent::fetchColumn($column);return is_string($value) && str_ends_with($value,' ') && $this->isChar($column) ? rtrim($value,' ') : $value;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
