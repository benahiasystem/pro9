<?php

namespace Modules\Report\Http\Controllers\Api;

use App\CoreFacturalo\Helpers\Template\TemplateHelper;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Report\Traits\ReportTrait;

class ReportController extends Controller
{

    use ReportTrait;

    private function getDateRange() {
        return
        [
            now()->firstOfMonth()->format('Y-m-d'),
            now()->endOfMonth()->format('Y-m-d'),
        ];
    }

    private function findModel(string $arg, $model, $param)
    {
        $arg = str_replace('_', ' ', $arg);
        return $model::where($param, 'like', "%{$arg}%")
            ->first();
    }

    public function quotations(Request  $request)
    {

        $response = $this->parserRequest($request);
        if (!$response['success']) {
            return $response;
        }
        Log::info($request->all());
        $records = $this->getRecords($request->all(), \App\Models\Tenant\Quotation::class);

        return $this->getCollectionQuotations($records->get());
    }

    public function generalItems(Request $request)
    {

        if (!$request->input('type')) {
            $request->merge(['type' =>  'sale']);
        }

        $response = $this->parserRequest($request);

        if (!$response['success']) {
            return $response;
        }

        return \App\Support\Exact\ReadSnapshot::run(\DB::connection('tenant'), function () use ($request) {
            $controller = new \Modules\Report\Http\Controllers\ReportGeneralItemController();
            $records = $controller->getRecordsItems($request->all())->latest('id')->get();
            return $this->getCollectionGeneralItems($records, $request);
        });
    }


    public function documents(Request $request)
    {

        $response = $this->parserRequest($request);
        if (!$response['success']) {
            return $response;
        }

        $records = $this->getRecords($request->all(), Document::class);
        return $this->getCollectionDocuments($records->get());
    }


    private function getCollectionGeneralItems($records, Request $request)
    {
        $costs = $request->input('type') === 'sale'
            ? \Modules\Report\Services\EstimatedPurchaseCost::forLines($records, (string)$request->input('document_type_id') === '80') : [];
        return $records->transform(function($row) use($request, $costs) {
            if ($request->input('type') === 'sale') {
                $exact = [];
                foreach (['quantity','unit_value','unit_price','total_discount','total_value','total_igv','total'] as $field) {
                    $exact[$field] = \App\Support\Exact\Decimal::readRequired($row, $field);
                }
                $data = \Modules\Report\Http\Resources\GeneralItemCollection::getDocument($row);
                if ($request->input('document_type_id') == '80') {
                    $observation = $data['observation']?$data['observation']:'';
                } else {
                    $observation = $data['additional_information']?$data['additional_information'][0]:'';
                }
                $isSaleNote = (string)$request->input('document_type_id') === '80';
                $document = $isSaleNote ? $row->sale_note : $row->document;
                $item = $row->relation_item;
                $item_web_platform = $item->getWebPlatformModel();
                if ($item_web_platform) {
                    $web_platform = $item_web_platform->name;
                }
                $cost = $costs[spl_object_id($row)];
                $warehouse_description = \App\CoreFacturalo\Helpers\Template\ReportHelper::getWarehouseDescription($row, $document);
                $seller = $document->seller_id ? $document->seller : $document->user;
                return [
                    'date_of_issue' => $document->date_of_issue->format('Y-m-d'),
                    'user' => optional($document->user)->name,
                    'district' => $document->establishment->district->description,
                    'province' => $document->establishment->province->description,
                    'department' => $document->establishment->department->description,
                    'type_document' => $isSaleNote ? 'NOTA DE VENTA' : $document->document_type->description,
                    'serie' => $document->series,
                    'number' => $document->number,
                    'purchase_order' => $document->purchase_order,
                    'web_platform' => $item_web_platform ? $web_platform : '',
                    'annulled' => $document->state_type_id == '11' ? true : false,
                    'identity_document_customer' => $document->customer->identity_document_type->description,
                    'number_document_customer' => $document->customer->number,
                    'customer_name' => $document->customer->name,
                    'seller' => optional($seller)->name,
                    'observation' => $observation,
                    'currency' => $document->currency_type_id,
                    'unit_type'=> $row->item->unit_type_id ?? null,
                    'internal_id'=> $row->item->internal_id ?? ($item->internal_id ?? ''),
                    'description'=> $row->item->description ?? '',
                    'quantity'=> \App\Support\Exact\Decimal::represent($exact['quantity']),
                    'payments_method' => '',
                    'unit_value' => \App\Support\Exact\Decimal::represent($exact['unit_value']),
                    'unit_price' => \App\Support\Exact\Decimal::represent($exact['unit_price']),
                    'discount' => \App\Support\Exact\Decimal::represent($exact['total_discount']),
                    'total_value' => \App\Support\Exact\Decimal::represent($exact['total_value']),
                    'affectation_igv_type' => $row->affectation_igv_type_id,
                    'igv' => \App\Support\Exact\Decimal::represent($exact['total_igv']),
                    'total' => \App\Support\Exact\Decimal::represent($exact['total']),
                    'calculation_snapshot' => \App\Support\Exact\Decimal::snapshot($exact),
                    'purchase_total' => \App\Support\Exact\Decimal::represent($cost['amount']),
                    'purchase_total_fraction' => \App\Support\Exact\Decimal::fraction($cost['amount']),
                    'purchase_total_estimate_source' => $cost['source'],
                    'exchange_rate_sale' => $document->exchange_rate_sale,
                    'warehouse_description' => $warehouse_description
                ];
            } else if ($request->input('type') === 'purchase') {
                $exact = [];
                foreach (['quantity','unit_value','unit_price','total_discount','total_value','total_igv','total'] as $field) {
                    $exact[$field] = \App\Support\Exact\Decimal::readRequired($row, $field);
                }
                $purchase = $row->purchase;
                $warehouse_description = \App\CoreFacturalo\Helpers\Template\ReportHelper::getWarehouseDescription($row, $purchase);
                Log::info('purchase', ['purchase' => $row]);
                return [
                    'date_of_issue' => $purchase->date_of_issue->format('Y-m-d'),
                    'document_type' => $purchase->document_type->description,
                    'serie' => $purchase->series,
                    'number' => $purchase->number,
                    'cancelled' => $purchase->state_type_id == '11' ? true : false,
                    'identity_document_supplier' => $purchase->supplier->identity_document_type->description,
                    'number_document_supplier' => $purchase->supplier->number,
                    'supplier_name' => $purchase->supplier->name,
                    'currency' => $purchase->currency_type_id,
                    'unit_type'=> $row->item->unit_type_id,
                    'internal_id'=> $row->item->internal_id ?? ($row->relation_item ? $row->relation_item->internal_id : ''),
                    'description' => $row->item->description,
                    'quantity'=> \App\Support\Exact\Decimal::represent($exact['quantity']),
                    'unit_value' => \App\Support\Exact\Decimal::represent($exact['unit_value']),
                    'unit_price' => \App\Support\Exact\Decimal::represent($exact['unit_price']),
                    'discount' => \App\Support\Exact\Decimal::represent($exact['total_discount']),
                    'total_value' => \App\Support\Exact\Decimal::represent($exact['total_value']),
                    'affectation_igv_type' => $row->affectation_igv_type_id,
                    'total_igv' => \App\Support\Exact\Decimal::represent($exact['total_igv']),
                    'total' => \App\Support\Exact\Decimal::represent($exact['total']),
                    'calculation_snapshot' => \App\Support\Exact\Decimal::snapshot($exact),
                    'exchange_rate_sale' => $purchase->exchange_rate_sale,
                    'warehouse_description' => $warehouse_description
                ];
            }
        });
    }

    private function getCollectionQuotations($records)
    {
        return $records->transform(function($row) {
            return [
                'date_of_issue' => $row->date_of_issue->format('Y-m-d'),
                'user_name' => $row->user->name,
                'customer_name' => $row->customer->name,
                'state' => $row->state_type->description,
                'number_full' => $row->number_full,
                'documents' => $row->documents->transform(function($doc) {
                    return [
                        'document_type_description' => $doc->document_type->description,
                        'number_full' => $doc->number_full,
                    ];
                }),
                'sale_notes' => $row->sale_notes->transform(function($row) {
                    return [
                    'number_full' => $row->number_full,
                ];
                }),
                'case' =>'',
                'currency' => $row->currency_type_id,
                'total_exportation' => \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::read($row, 'total_exportation')),
                'total_exonerated' => \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::read($row, 'total_exonerated')),
                'total_taxed' => \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::read($row, 'total_taxed')),
                'total_igv' => \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::read($row, 'total_igv')),
                'total' => \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::read($row, 'total')),
                'report_total_fractions' => \Modules\Report\Services\DocumentCurrencyTotals::fractions($row) + [
                    'total_exportation' => \App\Support\Exact\Decimal::fraction(\App\Support\Exact\Decimal::read($row, 'total_exportation')),
                ],
            ];
        });
    }

    public function getCollectionDocuments($records)
    {
        return $records->transform(function($row) {
            $document_type = $row->getDocumentType();
            $seller = \App\CoreFacturalo\Helpers\Template\ReportHelper::getSellerData($row);
            $customer = \App\Models\Tenant\Person::find($row->customer_id);
            $isNote = in_array($document_type->id,["07","08"]) && $row->note;
            $establishment = \App\CoreFacturalo\Helpers\Template\ReportHelper::getLocationData($row);
            $serie_affec = '';
            $payments = collect([]);
            if ($isNote) {
                $serie = ($row->note->affected_document) ? $row->note->affected_document->series : $row->note->data_affected_document->series;
                $number =  ($row->note->affected_document) ? $row->note->affected_document->number : $row->note->data_affected_document->number;
                $serie_affec = $serie.' - '.$number;
            }
            if(
                get_class($row) == Document::class ||
                get_class($row) == SaleNote::class
            ){
                $payments = TemplateHelper::getDetailedPayment($row);
            }
            $totals = $this->totalesDocuments($row, $document_type->id);

            return [
                'user' => $seller ? $seller->name : '',
                'document_type_id' =>  $document_type->id ,
                'number' => "{$row->series}-{$row->number}",
                'date_of_issue' => $row->date_of_issue->format('Y-m-d'),
                'date_of_due' => isset($row->invoice)?$row->invoice->date_of_due->format('Y-m-d'):'',
                'document_affected' => $serie_affec,
                'guides' => $row->guides ? $row->guides->transform(function($guide) {
                    return $guide->number;
                }) : [],
                'quotations' => ($row->quotation) ? $row->quotation->number_full : '',
                'district' => $establishment ? $establishment['district'] : '',
                'province' => $establishment ? $establishment['province'] : '',
                'department' => $establishment ? $establishment['department'] : '',
                'address_customer' => $row->customer->address,
                'document_type_customer' => optional($customer->person_type)->description,
                'name_customer' => $customer->name,
                'number_customer' => $customer->number,
                'status' => $row->state_type->description,
                'currency_type_id' => $row->currency_type_id,
                'sale_note' => $row->sale_note ? $row->sale_note->number_full : '',
                'date_of_issue_sale_note' => $row->sale_note ? $row->sale_note->date_of_issue->format('Y-m-d') : '',
                'payments' =>  $payments,
                'exchange_rate_sale' => $row->exchange_rate_sale,
                'total_charge' => $totals['total_charge'],
                'total_exonerated' => $totals['total_exonerated'],
                'total_taxed' => $totals['total_taxed'],
                'total_discount' => $totals['total_discount'],
                'total_igv' => $totals['total_igv'],
                'total' => $totals['total'],
                'report_signed_fractions' => $totals['fractions'],
            ];
        });
    }

    private function totalesDocuments($record, $document_type)
    {
        $totals = []; $fractions = [];
        foreach (['total_charge','total_exonerated','total_taxed','total_discount','total_igv','total'] as $key) {
            $value = in_array((string)$record->state_type_id, ['09','11'], true)
                ? \App\Support\Exact\Decimal::of(0) : \App\Support\Exact\Decimal::read($record, $key);
            if ((string)$document_type === '07') $value = $value->negated();
            $totals[$key] = \App\Support\Exact\Decimal::represent($value);
            $fractions[$key] = \App\Support\Exact\Decimal::fraction($value);
        }
        $totals['fractions'] = $fractions;
        return $totals;
    }

    private function parserRequest(Request &$request)
    {
        $user = auth()->user();
        [$range_start, $range_end] = $this->getDateRange();

        $response = [
            'success' => true,
            'message' => '',
        ];

        if ($request->input('item_name')) {
            $item = $this->findModel($request->input('item_name'), Item::class, 'description');
            if (!$item)
            {
                $response['success'] = false;
                $response['message'] = "No se encontro el item ".$request->input('item_name');
                return $response;

            }


            $request->merge(['item_id' => $item->id ?? null]);
        }

        if ($request->input('user_name')) {
            $user = $this->findModel($request->input('user_name'), User::class, 'name');

            if (!$user) {
                $response['success'] = false;
                $response['message'] = "No se encontro el usuario ".$request->input('user_name');
                return $response;
            }

            $request->merge(['user_id' => $user->id ?? null]);
        } else {
            $request->merge(['user_id' => $user->id]);
        }

        if ($request->input('person_name')) {
            $person = $this->findModel($request->input('person_name'), Person::class, 'name');
            Log::info('person found', ['person' => $person]);

            if (!$person) {
                $response['success'] = false;
                $response['message'] = "No se encontro la persona ".$request->input('person_name');
                return $response;
            }

            $request->merge(['person_id' => $person->id]);
            $request->merge(['type_person' => 'customers']);
        }

        if ($request->input('establishment_name')) {
            $establishment = $this->findModel($request->input('establishment_name'), Establishment::class, 'description');

            if (!$establishment) {
                $response['success'] = false;
                $response['message'] = "No se encontro el establecimiento ".$request->input('establishment_name');
                return $response;
            }
            $request->merge(['establishment_id' => $establishment->id ]);
        } else {
            $request->merge(['establishment_id' => null ]);
        }

        if ($request->input('date_start') == null) {
            $request->merge(['date_start' => $range_start]);
        }

        if ($request->input('date_end') == null) {
            $request->merge(['date_end' => $range_end]);
        }

        $request->merge(['period' => 'between_dates']);

        return $response;

    }

}
