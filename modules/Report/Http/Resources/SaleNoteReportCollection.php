<?php

namespace Modules\Report\Http\Resources;

use App\Models\Tenant\Configuration;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Report\Services\SaleNoteReportAmounts;

final class SaleNoteReportCollection extends ResourceCollection
{
    private array $display;

    public function __construct($resource, array $display)
    {
        parent::__construct($resource);
        $this->display = $display;
    }

    public function toArray($request)
    {
        $configuration = Configuration::first();
        return $this->collection->map(function ($note) use ($configuration) {
            $amounts = SaleNoteReportAmounts::row($note);
            $data = $note->getCollectionData($configuration);
            $display = $this->display[$amounts['report_key']] ?? throw new \LogicException('Falta la proyección de la nota de venta.');
            foreach (SaleNoteReportAmounts::FIELDS as $field) $data[$field] = $display[$field];
            $data['calculation_snapshot'] = $amounts['calculation_snapshot'];
            return $data;
        });
    }
}
