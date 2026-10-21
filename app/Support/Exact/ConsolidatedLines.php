<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Bounded parent reads; old allocations are derived temporarily, never backfilled. */
final class ConsolidatedLines
{
    private array $parents = [];

    public function __construct(private $connection) {}

    public function rows(iterable $records): \Generator
    {
        $records = is_array($records) ? $records : iterator_to_array((function () use ($records) { yield from $records; })(), false);
        if (count($records) > 500) throw new \InvalidArgumentException('El lote del consolidado supera 500 partidas.');
        $wanted = [];
        foreach ($records as $row) {
            $source = $row->source_type;
            if (!in_array($source, ['document', 'sale_note'], true) || $row->source_id === null) {
                throw new \UnexpectedValueException('Falta el origen de la partida del consolidado.');
            }
            $wanted[$source][$row->source_id] = true;
        }
        foreach ($wanted as $source=>$ids) {
            $table = $source === 'document' ? 'documents' : 'sale_notes';
            $missing = array_filter(array_keys($ids), fn($id)=>!isset($this->parents[$source.':'.$id]));
            if (!$missing) continue;
            foreach ($this->connection->table($table)->whereIn('id', $missing)->get(['id','total','total_value','total_igv',
                'total_other_taxes','total_discount','total_charge','total_prepayment','discounts','calculation_snapshot']) as $header) {
                $this->parents[$source.':'.$header->id] = ['header'=>$header, 'key'=>HeaderAllocation::key($header), 'context'=>null];
            }
            foreach ($missing as $id) if (!isset($this->parents[$source.':'.$id])) throw new \UnexpectedValueException('No se encontró la cabecera de la partida.');
        }
        $fallback = $frozen = [];
        foreach ($records as $position=>$row) {
            $key = $row->source_type.':'.$row->source_id;
            $frozen[$position] = $this->frozen($row, $this->parents[$key]['key']);
            if ($frozen[$position] === null && $this->parents[$key]['context'] === null) $fallback[$row->source_type][$row->source_id] = true;
        }
        foreach ($fallback as $source=>$ids) {
            $table = $source === 'document' ? 'document_items' : 'sale_note_items';
            $foreign = $source === 'document' ? 'document_id' : 'sale_note_id';
            $stats = [];
            foreach (array_keys($ids) as $id) $stats[$id] = HeaderAllocation::stats();
            foreach ($this->connection->table($table)->whereIn($foreign, array_keys($ids))->lazyById(500) as $line) {
                HeaderAllocation::add($stats[$line->$foreign], $line);
            }
            foreach ($stats as $id=>$values) {
                $key = $source.':'.$id;
                $this->parents[$key]['context'] = HeaderAllocation::context($this->parents[$key]['header'], $values);
            }
        }
        foreach ($records as $position=>$row) {
            $entry = $this->parents[$row->source_type.':'.$row->source_id];
            yield SalesConsolidated::line($row, $frozen[$position] ?? HeaderAllocation::line($row, $entry['context']));
        }
        // Cache only a batch of parents; no query-per-row or unbounded report cache.
        while (count($this->parents) > 500) array_shift($this->parents);
    }

    private function frozen($line, string $key): ?array
    {
        $snapshot = $line->calculation_snapshot;
        if (is_string($snapshot)) $snapshot = JsonDecimals::decode($snapshot);
        if ($snapshot === null) return null;
        if (!is_array($snapshot) || ($snapshot['version'] ?? null) !== Decimal::VERSION || !is_array($snapshot['values'] ?? null)) {
            throw new \UnexpectedValueException('Snapshot de partida inválido.');
        }
        if (($snapshot['inputs']['header_allocation_key'] ?? null) !== $key) return null;
        $values = [];
        foreach (HeaderAllocation::FIELDS as $field) $values[$field] = Decimal::readRequired($line, $field);
        if (!$values['line_total']->isEqualTo(Decimal::readRequired($line, 'total'))) throw new \UnexpectedValueException('El reparto conservado no corresponde a la partida.');
        return $values;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
