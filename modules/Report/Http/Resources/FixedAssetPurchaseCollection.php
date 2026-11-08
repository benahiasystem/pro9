<?php

namespace Modules\Report\Http\Resources;

use App\Support\Exact\Decimal as D;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Report\Services\PurchaseReportAmounts;

class FixedAssetPurchaseCollection extends ResourceCollection
{
    private ?array $display;

    public function __construct($resource, ?array $display = null)
    {
        parent::__construct($resource);
        $this->display = $display;
    }

    public function toArray($request)
    {
        $prepared = $this->display === null
            ? PurchaseReportAmounts::presentation($this->collection, false)['rows']
            : $this->collection->map(function ($row) {
                $amounts = PurchaseReportAmounts::row($row, false);
                $amounts['display'] = $this->display[$amounts['report_key']] ?? throw new \LogicException('Falta la proyección de la compra de activo fijo.');
                return $amounts;
            });
        return $prepared->map(function ($amounts) {
            $row = $amounts['model'];
            $supplier = $row->supplier;
            $data = [
                'id'=>$row->id, 'document_type_description'=>optional($row->document_type)->description,
                'group_id'=>$row->group_id, 'fiscal_environment'=>$row->fiscal_environment,
                'date_of_issue'=>optional($row->date_of_issue)->format('Y-m-d'),
                'date_of_due'=>optional($row->date_of_due)->format('Y-m-d'),
                'number'=>$row->number_full, 'supplier_name'=>$supplier->name ?? null,
                'supplier_number'=>$supplier ? format_person_identity_document($supplier) : null,
                'currency_type_id'=>$amounts['currency_type_id'], 'state_type_id'=>$row->state_type_id,
                'exchange_rate_sale'=>$row->exchange_rate_sale, 'state_type_description'=>optional($row->state_type)->description,
                'created_at'=>optional($row->created_at)->format('Y-m-d H:i:s'),
                'updated_at'=>optional($row->updated_at)->format('Y-m-d H:i:s'),
                'calculation_snapshot'=>$amounts['calculation_snapshot'],
                'items'=>$row->items->map(function ($item, $key) {
                    $quantity = D::readRequired($item, 'quantity');
                    return ['key'=>$key+1, 'id'=>$item->id, 'description'=>$item->item->description ?? $item->item->name ?? null,
                        'quantity'=>D::represent($quantity), 'quantity_fraction'=>D::fraction($quantity)];
                }),
            ];
            foreach (PurchaseReportAmounts::FIELDS as $field) $data[$field] = $amounts['display'][$field];
            return $data;
        });
    }
}
