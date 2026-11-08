<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

/**
 * Class PurchaseCollection
 *
 * @package App\Http\Resources\Tenant
 */
class PurchaseCollection extends ResourceCollection
{
    private ?array $display;

    public function __construct($resource, ?array $display = null)
    {
        parent::__construct($resource);
        $this->display = $display;
    }
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return Collection
     */
    public function toArray($request)
    {

        $convert = \Modules\Report\Services\PurchaseReportAmounts::converted($request->input('apply_conversion_to_pen'));
        $prepared = $this->display === null
            ? \Modules\Report\Services\PurchaseReportAmounts::presentation($this->collection, $convert)['rows']
            : $this->collection->map(function ($row) use ($convert) {
                $amounts = \Modules\Report\Services\PurchaseReportAmounts::row($row, $convert);
                $amounts['display'] = $this->display[$amounts['report_key']] ?? throw new \LogicException('Falta la proyección de la compra.');
                return $amounts;
            });
        return $prepared->map(function ($amounts) {
            $row = $amounts['model'];
            $data = $row->getCollectionData();
            foreach (\Modules\Report\Services\PurchaseReportAmounts::FIELDS as $field) $data[$field] = $amounts['display'][$field];
            $data['currency_type_id'] = $amounts['currency_type_id'];
            $data['original_currency_type_id'] = $amounts['original_currency_type_id'];
            $data['calculation_snapshot'] = $amounts['calculation_snapshot'];
            $data['description_apply_conversion_to_pen'] = $amounts['converted'] ? 'Se aplicó conversión a bolívares' : null;
            return $data;
        });

    }

}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
