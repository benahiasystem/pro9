<?php

namespace Modules\Report\Http\Resources;

use Modules\Report\Services\CustomerReportAmounts;

final class CustomerDocumentCollection extends DocumentCollection
{
    private array $display;

    public function __construct($resource, array $display)
    {
        parent::__construct($resource);
        $this->display=$display;
    }

    public function toArray($request)
    {
        return parent::toArray($request)->map(function ($row) {
            $signed=$this->display['document:'.$row['id']]['total'] ?? throw new \LogicException('Falta la proyección del documento.');
            // The existing customer screen supplies the minus sign for credit notes.
            $row['total']=$row['document_type_id'] === '07' ? ltrim($signed, '-') : $signed;
            return $row;
        });
    }
}
