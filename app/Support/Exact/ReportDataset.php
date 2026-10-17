<?php

namespace App\Support\Exact;

use Illuminate\Pagination\LengthAwarePaginator;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Private per-execution spool: repeatable report reads without retaining every row in RAM. */
final class ReportDataset implements \IteratorAggregate, \Countable
{
    private $file;
    private int $size = 0;

    public function __construct(iterable $rows)
    {
        $this->file = tmpfile();
        if ($this->file === false) throw new \RuntimeException('No se pudo crear el conjunto temporal del reporte.');
        foreach ($rows as $row) {
            $line = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
            if (fwrite($this->file, $line) !== strlen($line)) throw new \RuntimeException('No se pudo conservar el conjunto del reporte.');
            $this->size++;
        }
        fflush($this->file);
    }

    public function count(): int { return $this->size; }

    public function sum(string $field): \Brick\Math\BigRational
    {
        return Decimal::sum((function () use ($field) { foreach ($this as $row) yield Decimal::readRequired($row, $field); })());
    }

    public function getIterator(): \Traversable
    {
        $reader = fopen(stream_get_meta_data($this->file)['uri'], 'rb');
        if ($reader === false) throw new \RuntimeException('No se pudo recuperar el conjunto del reporte.');
        try {
            while (($line = fgets($reader)) !== false) yield json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (!feof($reader)) throw new \RuntimeException('Lectura incompleta del reporte.');
        } finally { fclose($reader); }
    }

    public function filter(callable $predicate): self
    {
        return new self((function () use ($predicate) { foreach ($this as $row) if ($predicate($row)) yield $row; })());
    }

    public function map(callable $transform): self
    {
        return new self((function () use ($transform) { foreach ($this as $row) yield $transform($row); })());
    }

    /** Stable external merge sort; at most 500 rows and 32 merge heads per pass. */
    public function sorted(callable $compare, int $batchSize = 500): self
    {
        if ($batchSize < 1 || $batchSize > 500) throw new \InvalidArgumentException('Lote de reporte fuera de rango.');
        $levels = []; $batch = []; $ordinal = 0;
        $push = static function (self $run) use (&$levels, $compare): void {
            $level = 0;
            while (true) {
                $levels[$level][] = $run;
                if (count($levels[$level]) < 32) return;
                $run = self::merge($levels[$level], $compare);
                $levels[$level] = [];
                $level++;
            }
        };
        $flush = static function (array $batch) use ($compare): self {
            usort($batch, static fn($a,$b)=>$compare($a['row'],$b['row']) ?: $a['ordinal'] <=> $b['ordinal']);
            return new self((function () use ($batch) { foreach ($batch as $entry) yield $entry['row']; })());
        };
        foreach ($this as $row) {
            $batch[] = ['row'=>$row, 'ordinal'=>$ordinal++];
            if (count($batch) === $batchSize) { $push($flush($batch)); $batch = []; }
        }
        if ($batch) $push($flush($batch));
        $runs = [];
        foreach (array_reverse($levels) as $level) foreach ($level as $run) $runs[] = $run;
        if (!$runs) return new self([]);
        while (count($runs) > 1) {
            $next = [];
            foreach (array_chunk($runs, 32) as $group) $next[] = self::merge($group, $compare);
            $runs = $next;
        }
        return $runs[0];
    }

    private static function merge(array $runs, callable $compare): self
    {
        return new self((function () use ($runs, $compare) {
            $heap = new class($compare) extends \SplPriorityQueue {
                public function __construct(private $comparison) {}
                public function compare($a, $b): int
                {
                    return -($this->comparison)($a[0], $b[0]) ?: $b[1] <=> $a[1];
                }
            };
            $heap->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
            $iterators = [];
            foreach ($runs as $position=>$run) {
                $iterator = $run->getIterator(); $iterator->rewind(); $iterators[$position] = $iterator;
                if ($iterator->valid()) $heap->insert([$iterator->current(), $position], [$iterator->current(), $position]);
            }
            while (!$heap->isEmpty()) {
                [$row, $position] = $heap->extract();
                yield $row;
                $iterator = $iterators[$position]; $iterator->next();
                if ($iterator->valid()) $heap->insert([$iterator->current(), $position], [$iterator->current(), $position]);
            }
        })());
    }

    public function sortBy(string $field): self
    {
        return $this->sorted(static fn($a,$b)=>strnatcmp((string)$a[$field], (string)$b[$field]));
    }

    public function sortExact(string $field, bool $descending = false): self
    {
        return $this->sorted(static function ($a, $b) use ($field, $descending) {
            $result = Decimal::readRequired($a, $field)->compareTo(Decimal::readRequired($b, $field));
            return $descending ? -$result : $result;
        });
    }

    public function paginate(int $perPage, int $page, array $options = []): LengthAwarePaginator
    {
        if ($perPage < 1 || $perPage > 500 || $page < 1) throw new \InvalidArgumentException('Paginación fuera de rango.');
        $offset = ($page - 1) * $perPage; $rows = []; $position = 0;
        foreach ($this as $row) {
            if ($position++ < $offset) continue;
            $rows[] = $row;
            if (count($rows) === $perPage) break;
        }
        return new LengthAwarePaginator($rows, $this->size, $perPage, $page, $options);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
