<?php

namespace Modules\Report\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Une DocumentItem + SaleNoteItem para el reporte de productos sin tipo de documento.
 * Expone count / latest / offset / limit / get / paginate como el query Eloquent del reporte.
 */
class CombinedGeneralItemQuery
{
    /** @var \Illuminate\Database\Eloquent\Builder */
    private $documentQuery;

    /** @var \Illuminate\Database\Eloquent\Builder */
    private $saleNoteQuery;

    private $offset = 0;

    private $limit = null;

    private $latestColumn = 'id';

    public function __construct($documentQuery, $saleNoteQuery)
    {
        $this->documentQuery = $documentQuery;
        $this->saleNoteQuery = $saleNoteQuery;
    }

    public function latest($column = 'id')
    {
        $this->latestColumn = $column;

        return $this;
    }

    public function offset($value)
    {
        $this->offset = (int) $value;

        return $this;
    }

    public function limit($value)
    {
        $this->limit = $value === null ? null : (int) $value;

        return $this;
    }

    public function count()
    {
        return $this->documentQuery->count() + $this->saleNoteQuery->count();
    }

    public function get()
    {
        $documents = (clone $this->documentQuery)->with([
            'document.customer',
            'document.document_type',
            'relation_item.brand',
            'relation_item.web_platform',
            'relation_item.sets',
        ])->get();

        $saleNotes = (clone $this->saleNoteQuery)->with([
            'sale_note.customer',
            'relation_item.brand',
            'relation_item.web_platform',
            'relation_item.sets',
        ])->get();

        $merged = $documents->concat($saleNotes)->sortByDesc(function ($row) {
            $parent = $row->document ?? $row->sale_note ?? null;
            $date = $parent && $parent->date_of_issue
                ? $parent->date_of_issue->format('YmdHis')
                : '00000000000000';

            return $date . '-' . str_pad((string) $row->{$this->latestColumn}, 12, '0', STR_PAD_LEFT);
        })->values();

        if ($this->limit !== null) {
            return $merged->slice($this->offset, $this->limit)->values();
        }

        if ($this->offset > 0) {
            return $merged->slice($this->offset)->values();
        }

        return $merged;
    }

    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);
        $total = $this->count();
        $results = $this->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
                'query' => request()->query(),
            ]
        );
    }
}
