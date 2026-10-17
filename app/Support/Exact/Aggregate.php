<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Read original fractions in bounded batches; SQL remains responsible for ordinary filters. */
final class Aggregate
{
    public static function query($query, array $fields, int $batchSize = 500): array
    {
        if ($batchSize<1) throw new \InvalidArgumentException('Tamaño de lote inválido.');
        $query=clone $query;
        if ($query instanceof \Illuminate\Database\Eloquent\Builder) $query->withoutEagerLoads();
        $totals=array_fill_keys($fields,Decimal::of(0));
        foreach ($query->reorder()->lazyById($batchSize) as $row) {
            foreach ($fields as $field) $totals[$field]=$totals[$field]->plus(Decimal::read($row,$field))->simplified();
        }
        return $totals;
    }

    public static function records(iterable $records, string $field): \Brick\Math\BigRational
    {
        return Decimal::sum((function () use ($records,$field) {foreach($records as $record)yield Decimal::read($record,$field);})());
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
