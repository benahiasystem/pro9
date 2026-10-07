<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use App\Models\Tenant\Document;
use App\Models\Tenant\Purchase;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Account\Exports\ReportFormatPurchaseExport;
use Modules\Account\Exports\ReportFormatSummaryReportExport;
use App\Models\Tenant\Series;
use App\Models\Tenant\Catalogs\DocumentType;

class SummaryReportController extends Controller
{


    public function index()
    {
        return view('account::summary_report.index');
    }

    public function records(Request $request)
    {
        $request->validate([
            'date_start' => 'required',
            'date_end' => 'required',
        ]);
        // dd($request->all());
        return $this->getRecords($request);

    }

    private function getRecords($request){

        $accepted_documents = $this->getAcceptedDocuments($request);
        $voided_documents = $this->getVoidedDocuments($request);

        $totals_accepted_documents = $this->getTotalsAcceptedDocuments($accepted_documents);
        $totals_voided_documents = $this->getTotalsVoidedDocuments($voided_documents);

        return [
            'accepted_documents' => $accepted_documents,
            'voided_documents' => $voided_documents,
            'totals_accepted_documents' => $totals_accepted_documents,
            'totals_voided_documents' => $totals_voided_documents,
        ];

    }


    public function download(Request $request)
    {

        $filename = 'Reporte_Resumido_Ventas_'.date('YmdHis');

        $data = [
            'records' => $this->getRecords($request)
        ];

        return (new ReportFormatSummaryReportExport())
            ->data($data)
            ->download($filename.'.xlsx');

    }


    private function getTotalsAcceptedDocuments($accepted_documents){

        $general_total_igv = 0;
        $general_total_value = 0;
        $general_total = 0;

        $general_total_igv +=  number_format($accepted_documents->sum('total_igv'), 2, ".", "");
        $general_total_value +=  number_format($accepted_documents->sum('total_value'), 2, ".", "");
        $general_total +=  number_format($accepted_documents->sum('total'), 2, ".", "");

        return [
            'general_total_igv' => $general_total_igv,
            'general_total_value' => $general_total_value,
            'general_total' => $general_total,
        ];

    }

    private function getTotalsVoidedDocuments($voided_documents){

        $general_total = 0;
        $general_total +=  number_format($voided_documents->sum('total'), 2, ".", "");

        return [
            'general_total' => $general_total,
        ];

    }

    private function getAcceptedDocuments($request){

        $total_igv = 0;
        $total_value = 0;
        $total = 0;

        $accepted_documents = Series::query()
                    ->select('number', 'document_type_id')
                    ->whereIn('document_type_id', ['01'])
                    ->whereHas('documents')
                    ->with(['documents' => function($query) use($request) {
                            $query->whereBetween('date_of_issue', [$request->date_start, $request->date_end])
                                  ->where('state_type_id', '05')
                                  ->select('series', 'number', 'state_type_id', 'total_igv', 'total_value', 'total', 'currency_type_id', 'exchange_rate_sale');
                    }])
                    ->get()
                    ->map(function($series) use($total_igv, $total_value, $total){

                        $quantity = count($series->documents);
                        $start_number = $series->documents->min('number') ?? 0;
                        $end_number = $series->documents->max('number') ?? 0;

                        $total_igv = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($total_igv)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($series->documents->where('currency_type_id', 'VES')->sum('total_igv')))->toBigDecimal();

                        $doc_dollar = collect($series->documents->where('currency_type_id', 'USD'));
                        foreach ($doc_dollar as $doc) {
                            $total_igv = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($total_igv)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($doc->total_igv)->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($doc->exchange_rate_sale)))->toBigDecimal();
                        }



                        $total_value = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($total_value)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($series->documents->where('currency_type_id', 'VES')->sum('total_value')))->toBigDecimal();
                        foreach ($doc_dollar as $doc) {
                            $total_value = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($total_value)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($doc->total_value)->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($doc->exchange_rate_sale)))->toBigDecimal();
                        }

                        $total = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($total)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($series->documents->where('currency_type_id', 'VES')->sum('total')))->toBigDecimal();
                        foreach ($doc_dollar as $doc) {
                            $total = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($total)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($doc->total)->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($doc->exchange_rate_sale)))->toBigDecimal();
                        }

                        return [
                            'document_type_description' => ($series->document_type_id == '01') ? 'FAC':'BV',
                            // 'series' => $series,
                            'series' => $series->number,
                            'start_number' => $start_number,
                            'end_number' => $end_number,
                            'total_igv' => number_format((float) \App\Services\ExchangeRates\ExchangeRateMath::finalAmount($total_igv, 2), 2, ".", ""),
                            'total_value' => number_format( (float) \App\Services\ExchangeRates\ExchangeRateMath::finalAmount($total_value, 2), 2, ".", ""),
                            'total' => number_format( (float) \App\Services\ExchangeRates\ExchangeRateMath::finalAmount($total, 2), 2, ".", ""),
                        ];
                    });


        return $accepted_documents;

    }


    private function getVoidedDocuments($request)
    {
        $total = 0;

        $voided_documents = Series::query()
            ->select('number', 'document_type_id')
            ->whereIn('document_type_id', ['01'])
            ->whereHas('documents', function($query) use($request) {
                    $query->whereBetween('date_of_issue', [$request->date_start, $request->date_end])
                            ->where('state_type_id', '11');
            })
            ->with(['documents' => function($query) use($request) {
                    $query->whereBetween('date_of_issue', [$request->date_start, $request->date_end])
                            ->where('state_type_id', '11')
                            ->select('series', 'number', 'state_type_id', 'total_igv', 'total_value', 'total', 'currency_type_id');
            }])
            ->get()
            ->map(function($series) use($total){
                // Estas variables no se usan
                // $start_number = $series->documents->min('number') ?? 0;
                // $end_number = $series->documents->max('number') ?? 0;
                // Eliminando esta linea porque esta volviendo a llamar a la base de datos y no esta filtrando por fechas
                // $voided = (count($series->documents) > 0) ? $series->documents()->where('state_type_id', '11')->pluck('number')->toArray() : [];
                $voided = $series->documents->pluck('number')->toArray();
                $total = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($total)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($series->documents->where('currency_type_id', 'VES')->sum('total')))->toBigDecimal();
                $doc_dollar = collect($series->documents->where('currency_type_id', 'USD'));
                foreach ($doc_dollar as $doc) {
                    $total = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($total)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($doc->total)->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($doc->exchange_rate_sale)))->toBigDecimal();
                }

                return [
                    'document_type_description' => ($series->document_type_id == '01') ? 'FAC':'BV',
                    'series' => $series->number,
                    'voided' => join('; ', $voided),
                    'total' => number_format((float) \App\Services\ExchangeRates\ExchangeRateMath::finalAmount($total, 2), 2, ".", ""),
                ];
            });
        return $voided_documents;
    }


}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
