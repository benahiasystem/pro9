<?php

namespace Modules\Report\Http\Resources;

use App\Models\Tenant\PurchaseItem;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ItemCollection extends ResourceCollection
{
    private ?array $display;

    public function __construct($resource, ?array $display = null)
    {
        parent::__construct($resource);
        $this->display=$display;
    }


    public function toArray($request) {


        return $this->collection->map(function ($row, $key) {


            $observation = null;
            $class = get_class($row);
            if ($class == PurchaseItem::class) {
                /** @var \App\Models\Tenant\PurchaseItem $row */
                $document = $row->purchase;
                $customer_name = $document->supplier->name;
                $customer_number = format_person_identity_document($document->supplier);
                /** @var \App\Models\Tenant\PurchaseItem $row */
                $purchase = $row->purchase;
                $observation=$purchase->observation;
            } else {
                /** @var \App\Models\Tenant\DocumentItem $row */
                $document = $row->document;
                $customer_name = $document->customer->name;
                $customer_number = format_person_identity_document($document->customer);
            }
            $amounts=\Modules\Report\Services\ItemReportAmounts::row($row);
            $display=$this->display === null
                ? \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($amounts,'total'),2,'.','')
                : ($this->display[$amounts['report_key']] ?? throw new \LogicException('Falta la proyección de la partida.'));
            return [
                'id'                        => $row->id,
                'date_of_issue'             => $document->date_of_issue->format('Y-m-d'),
                'customer_name'             => $customer_name,
                'customer_number'           => $customer_number,
                'series'                    => $document->series,
                'alone_number'              => $document->number,
                'quantity'                  => $amounts['quantity'],
                'unit_type_id'              => $amounts['unit_type_id'],
                'currency_type_id'          => $amounts['currency_type_id'],
                'total'                     => $display,
                'calculation_snapshot'      => $amounts['calculation_snapshot'],
                'state_type_id'             => $document->state_type_id,
                'document_type_description' => $document->document_type->description,
                'document_type_id'          => $document->document_type->id,
                'web_platform_name'         => optional($row->relation_item->web_platform)->name,
                'observation'=>$observation,

            ];
        });
    }
}
