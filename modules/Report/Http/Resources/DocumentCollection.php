<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use \App\CoreFacturalo\Helpers\Template\ReportHelper;
use App\CoreFacturalo\Helpers\Template\TemplateHelper;
use Modules\Item\Models\WebPlatform;

class DocumentCollection extends ResourceCollection
{


    public function toArray($request) {


        \App\Services\Fiscal\FiscalIdentity::preload($this->collection);
        $documents = new \Illuminate\Database\Eloquent\Collection($this->collection->all());
        $documents->loadMissing([
            'seller', 'sale_note', 'quotation.sale_opportunity', 'agent',
            'currency_type', 'payments.global_payment', 'payments.payment_method_type', 'fee.currency_type',
        ]);
        $documents->filter(fn ($row) => $row->document_type_id === '01')->loadMissing('affected_documents.document');
        // ########## INICIO CAMBIO POSTGRESQL 17
        $customers=\App\Models\Tenant\Person::whereIn('id',$this->collection->pluck('customer_id')->unique()->all())
            ->with(['district','department','province'])->get()->keyBy('id')->all();
        // ######### FIN CAMBIO POSTGRESQL 17
        $itemIds = $documents->flatMap(fn ($document) => $document->items->pluck('item_id'))
            ->filter()->unique()->values();
        $platformsByItem = $itemIds->isEmpty() ? collect() : WebPlatform::query()
            ->join('items', 'items.web_platform_id', '=', 'web_platforms.id')
            ->whereIn('items.id', $itemIds)
            ->get(['web_platforms.id', 'web_platforms.name', 'items.id as catalog_item_id'])
            ->mapWithKeys(fn (WebPlatform $platform) => [
                $platform->getAttribute('catalog_item_id') => $platform->only(['id', 'name']),
            ]);

        return $this->collection->map(function (\App\Models\Tenant\Document $row) use ($customers, $platformsByItem) {


            $affected_document = null;
            if(in_array($row->document_type_id,['07','08']) && $row->note){

                $series = ($row->note->affected_document) ? $row->note->affected_document->series : $row->note->data_affected_document->series;
                $number =  ($row->note->affected_document) ? $row->note->affected_document->number : $row->note->data_affected_document->number;
                $affected_document = $row->note->affected_document ? $row->note->affected_document->number_full : \App\Services\Fiscal\FiscalIdentity::numberFull($series, $number);
            }

            $signal = $row->document_type_id;
            $state = $row->state_type_id;

            $web_platforms = $row->items->pluck('item_id')->unique()->sort()->map(
                fn ($id) => $platformsByItem->get($id)
            )->filter()->values();
            $seller = $row->getSellerData();

            $stablishment_info=ReportHelper::getLocationData($row,$customers);

            $payments_total= [];
            $payment_description='';
            $payments_total = TemplateHelper::getDetailedPayment($row);

            $payment_description = collect($payments_total)->flatten(1)
                ->map(fn ($payment) => $payment['display'] ?? $payment['description'])
                ->implode(' - ');

            //dd($row->sale_note);
            return [
                'id' => $row->id,
                'group_id' => $row->group_id,
                'fiscal_environment' => $row->fiscal_environment,
                'fiscal_environment_description' => $row->fiscal_environment_type->description,
                'date_of_issue' => $row->date_of_issue->format('Y-m-d'),
                'date_of_due' => (in_array($row->document_type_id, ['01'])) ? $row->invoice->date_of_due->format('Y-m-d') : null,
                'serie' => $row->fiscal_identity['series'],
                'fiscal_identity' => $row->fiscal_identity,
                'number' => $row->fiscal_identity['document_number'],
                'customer_name' => $row->customer->name,
                'customer_number' => format_person_identity_document($row->customer),
                'currency_type_id' => $row->currency_type_id,
                'district' => $stablishment_info['district'],
                'department' => $stablishment_info['department'],
                'province' => $stablishment_info['province'],
                'client_direction' => $row->customer->name,
                'ruc' => $row->customer->number,
                'note_sale' => $row->sale_note?$row->sale_note->number_full:'',
                'date_sale' => $row->sale_note?$row->sale_note->date_of_issue->format('Y-m-d'):'',
                'payment_form' => \App\Services\Payments\ReceiptPresentation::firstMethod($row->payments),
                'payment_method' => $payment_description,
                'series' => $row->fiscal_identity['series'],
                'establishment_id' => $row->establishment_id,
                'alone_number' => $row->fiscal_identity['document_number'],
                'purchase_order' => $row->purchase_order,
                'guides' => !empty($row->guides)?(array)$row->guides:null,

                'total_exportation' => in_array($row->state_type_id,['09','11']) ? number_format(0,2, ".","") : \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'total_exportation'), 2, '.', ''),
                'total_exonerated' =>  in_array($row->state_type_id,['09','11']) ? number_format(0,2, ".","") : \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'total_exonerated'), 2, '.', ''),
                'total_taxed' => in_array($row->state_type_id,['09','11']) ? number_format(0,2, ".","") : \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'total_taxed'), 2, '.', ''),
                'total_igv' =>  in_array($row->state_type_id,['09','11']) ? number_format(0,2, ".","") : \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'total_igv'), 2, '.', ''),
                'total' =>  in_array($row->state_type_id,['09','11']) ? number_format(0,2, ".","") : \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'total'), 2, '.', ''),
                'total_charge' => in_array($row->state_type_id,['09','11']) ? '0.00' : \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'total_charge'), 2, '.', ''),
                'report_total_fractions' => \Modules\Report\Services\DocumentCurrencyTotals::fractions($row),



                'state_type_id' => $row->state_type_id,
                'state_type_description' => $row->state_type->description,
                'document_type_description' => $row->document_type->description,
                'document_type_id' => $row->document_type->id,
                'affected_document' => $affected_document,
                'user_name' => ($seller) ? $seller->name : '',
                'user_email' => ($seller) ? $seller->email : '',

                'notes' => (in_array($row->document_type_id, ['01'])) ? $row->affected_documents->map(function($row) {
                    return [
                        'id' => $row->id,
                        'document_id' => $row->document_id,
                        'note_type_description' => ($row->note_type == 'credit') ? 'NC':'ND',
                        'description' => $row->document->number_full,
                    ];
                }) : null,
                'quotation_number_full' => ($row->quotation) ? $row->quotation->number_full : '',
                'sale_opportunity_number_full' => isset($row->quotation->sale_opportunity) ? $row->quotation->sale_opportunity->number_full : '',
                'web_platforms' => $web_platforms,
                'plate_number' => $row->plate_number,
                'items' => $row->items->map(function($row, $key) {
                    return [
                        'key' => $key + 1,
                        'id' => $row->id,
                        'description' => $row->item->description,
                        'quantity' => \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::read($row, 'quantity')),
                        'quantity_fraction' => \App\Support\Exact\Decimal::fraction(\App\Support\Exact\Decimal::read($row, 'quantity'))
                    ];
                }),

                'agent_name' => optional($row->agent)->search_description,
                'reference_data' => $row->reference_data,
                'plate_number' => $row->getPlateNumberSaleReport(),

            ];
        });
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
