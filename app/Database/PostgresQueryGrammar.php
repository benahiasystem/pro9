<?php

namespace App\Database;

use Illuminate\Database\Query\Builder;

// ########## INICIO CAMBIO POSTGRESQL 17
class PostgresQueryGrammar extends \Illuminate\Database\Query\Grammars\PostgresGrammar
{
    protected function whereBasic(Builder $query, $where)
    {
        $operator = strtolower($where['operator']);
        if (in_array($operator, ['like', 'ilike', 'not like', 'not ilike'], true)) {
            return TextSearch::predicate($this->wrap($where['column']), $this->parameter($where['value']), str_starts_with($operator, 'not '));
        }
        return parent::whereBasic($query, $where);
    }

    protected function compileOrdersToArray(Builder $query, $orders)
    {
        return array_map(function ($order) {
            if (isset($order['sql'])) return $order['sql'];
            $direction = strtolower($order['direction']);
            return $this->wrap($order['column']).' '.$direction.($direction === 'desc' ? ' NULLS LAST' : ' NULLS FIRST');
        }, $orders);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
