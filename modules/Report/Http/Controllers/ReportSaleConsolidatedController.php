<?php

namespace Modules\Report\Http\Controllers;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Company;
use App\Models\Tenant\Item;
use Carbon\Carbon;
use Modules\Report\Http\Resources\SaleConsolidatedCollection;
use Modules\Report\Traits\ReportTrait;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\DocumentItem;
use Illuminate\Support\Facades\DB;
use Modules\Report\Exports\SaleConsolidatedExport;
use Modules\Report\Exports\SaleConsolidatedTotalExport;
use Modules\Report\Traits\ConsolidatedReportTrayTrait;
// ######## INICIO CALCULOS EXACTOS PRO9 ########
use App\Support\Exact\SalesConsolidated;
use App\Support\Exact\ReportDataset;
// ######## FIN CALCULOS EXACTOS PRO9 ########


class ReportSaleConsolidatedController extends Controller
{
    use ReportTrait;
    use ConsolidatedReportTrayTrait;

    public function filter()
    {


        $persons = $this->getPersons('customers');
        $date_range_types = $this->getDateRangeTypes(true);
        $order_state_types = [];
        $sellers = $this->getSellers();
        $document_types = $this->getCIDocumentTypes();
        $establishment_id = $this->getEstablishment();
        $series = $this->getSeries($document_types);
        $users = $this->getUsers();

        return compact(
             'users',
             'persons',
             'date_range_types',
             'order_state_types',
             'sellers',
             'document_types',
             'series',
             'establishment_id'
        );
    }


    public function index() {

        return view('report::sales_consolidated.index');
    }

    // ######## INICIO CALCULOS EXACTOS PRO9 ########
    public function records(Request $request)
    {
        $request->validate(['page'=>'sometimes|integer|min:1']);
        $query = $this->getRecordsSalesConsolidated($request->all());
        return \App\Support\Exact\ReadSnapshot::run($query->getModel()->getConnection(), function () use ($query, $request) {
            $perPage = max(1, min(500, (int)config('tenant.items_per_page', 50)));
            $page = $query->reorder()->orderByDesc('id')->orderBy('source_type')->withoutEagerLoads()
                ->with(['relation_item'=>fn($q)=>$q->withoutEagerLoads()->select(['id','internal_id','unit_type_id','brand_id','category_id'])->with(['brand:id,name','category:id,name'])])
                ->paginate($perPage, ['*'], 'page', (int)$request->input('page', 1));
            $rows = collect(iterator_to_array((new \App\Support\Exact\ConsolidatedLines($query->getModel()->getConnection()))->rows($page->getCollection()), false));
            $page->setCollection($rows);
            return (new SaleConsolidatedCollection($page))->additional(['summary'=>SalesConsolidated::summary($rows)]);
        });
    }

    private function sourceLines($query): \Generator
    {
        $source = (clone $query)->withoutEagerLoads()->with(['relation_item'=>fn($q)=>$q->withoutEagerLoads()->select(['id','internal_id','unit_type_id','brand_id','category_id'])->with(['brand:id,name','category:id,name'])])->reorder()
            ->orderBy('source_type')->orderBy('id');
        $reader = new \App\Support\Exact\ConsolidatedLines($source->getModel()->getConnection());
        $batch = [];
        foreach ($source->lazy(500) as $record) {
            $batch[] = $record;
            if (count($batch) === 500) { yield from $reader->rows($batch); $batch = []; }
        }
        if ($batch) yield from $reader->rows($batch);
    }

    public function detailRecords(array $params)
    {
        $query = $this->getRecordsSalesConsolidated($params);
        return \App\Support\Exact\ReadSnapshot::run($query->getModel()->getConnection(), fn()=>new ReportDataset($this->sourceLines($query)));
    }

    public function totalsByItem(Request $request)
    {
        $query = $this->getRecordsSalesConsolidated($request->all());
        return \App\Support\Exact\ReadSnapshot::run($query->getModel()->getConnection(), fn()=>SalesConsolidated::groupedDataset($this->sourceLines($query)));
    }
    // ######## FIN CALCULOS EXACTOS PRO9 ########


    /**
     *
     * Agrupar items por item_id y unit_type_id, para ventas individuales y por presentaciones
     *
     * @param  array $records
     * @return array
     */
    public function groupTotalsByItem($records)
    {
        return $records->groupBy(function($row){

                    $item_unit_type_id = $row->item->unit_type_id ?? false;
                    $group_key = $row->item_id;

                    if($item_unit_type_id)
                    {
                        $group_key .= "-".$item_unit_type_id;
                    }

                    return $group_key;
                });
    }


    public function getRecordsSalesConsolidated($request){

        // dd($request);
        $records = $this->dataSalesConsolidated($request);

        return $records;

    }


    private function dataSalesConsolidated($request)
    {
        $request['date_range_type_id'] ??= 'date_of_issue';
        if (!in_array($request['date_range_type_id'],array_column($this->getDateRangeTypes(true),'id'),true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['date_range_type_id'=>'Seleccione un campo de fecha permitido para el consolidado.']);
        }

        $document_types = isset($request['document_type_id']) ? json_decode($request['document_type_id']) : [];
        $document_items = DocumentItem::whereDefaultDocumentType($request);
        if (!empty($document_types)) {
            $nota_venta = null;
            $document_items = $document_items->wherein('document_type_id', $document_types);
            if (in_array('80', $document_types)) {
                $request['document_type_id'] = '80';
                $nota_venta = SaleNoteItem::whereDefaultDocumentType($request);
            }
            $data = ($nota_venta != null) ? $document_items->union($nota_venta) : $document_items;
        } else {
            $sale_note_items = SaleNoteItem::whereDefaultDocumentType($request);
            $data = $document_items->union($sale_note_items);
        }


        return $data;
    }


    private function consolidatedDetailCount(Request $request): int
    {
        return $this->getRecordsSalesConsolidated($request->all())->count();
    }

    private function consolidatedTotalsCount(Request $request): int
    {
        return $this->totalsByItem($request)->count();
    }

    private function consolidatedCompanyAndEstablishment(Request $request): array
    {
        $company = Company::first();
        $establishment = ($request->establishment_id)
            ? Establishment::findOrFail($request->establishment_id)
            : auth()->user()->establishment;

        return [$company, $establishment];
    }


    public function pdf(Request $request)
    {
        if ($trayResponse = $this->dispatchConsolidatedReportToTray(
            $request,
            'pdf',
            'detail',
            'sales',
            'Consolidado de items de ventas',
            $this->consolidatedDetailCount($request)
        )) {
            return $trayResponse;
        }

        [$company, $establishment] = $this->consolidatedCompanyAndEstablishment($request);
        $records = $this->detailRecords($request->all());
        $params = $request->all();
        $reportSummary = SalesConsolidated::summary($records);
        $records = \App\Support\Exact\ReportProjection::money($records, ['total_sale', 'total_ves', 'total_usd']);

        $pdf = PDF::loadView('report::sales_consolidated.report_pdf', compact('records', 'company', 'establishment', 'params', 'reportSummary'));
        $filename = 'Reporte_Consolidado_Items_Ventas_'.date('YmdHis');

        return $pdf->stream($filename.'.pdf');
    }


    public function excel(Request $request) {
        if ($trayResponse = $this->dispatchConsolidatedReportToTray(
            $request,
            'xlsx',
            'detail',
            'sales',
            'Consolidado de items de ventas',
            $this->consolidatedDetailCount($request)
        )) {
            return $trayResponse;
        }

        [$company, $establishment] = $this->consolidatedCompanyAndEstablishment($request);
        $records = $this->detailRecords($request->all());
        $params = $request->all();
        $reportSummary = SalesConsolidated::summary($records);
        $filename = 'Reporte_Consolidado_Items_Ventas_'.date('YmdHis');

        $saleConsolidatedExport = new SaleConsolidatedExport();
        $saleConsolidatedExport
            ->records($records)
            ->company($company)
            ->establishment($establishment)
            ->params($params);

        return $saleConsolidatedExport->download($filename.'.xlsx');
    }


    public function pdfTotals(Request $request) {
        if ($trayResponse = $this->dispatchConsolidatedReportToTray(
            $request,
            'pdf',
            'totals',
            'sales',
            'Consolidado de items de ventas - totales',
            $this->consolidatedTotalsCount($request)
        )) {
            return $trayResponse;
        }

        [$company, $establishment] = $this->consolidatedCompanyAndEstablishment($request);
        $records = $this->totalsByItem($request)->sortBy('item_id');
        $params = $request->all();
        $reportSummary = SalesConsolidated::summary($records);
        $records = \App\Support\Exact\ReportProjection::money($records, ['total_sale', 'total_ves', 'total_usd']);

        $pdf = PDF::loadView('report::sales_consolidated.report_pdf_totals', compact("records", "company", "establishment", "params", "reportSummary"));
        $filename = 'Reporte_Consolidado_Items_Ventas_Totales_'.date('YmdHis');

        return $pdf->stream($filename.'.pdf');
    }


    public function pdfTicketsTotal(Request $request) {
        if ($trayResponse = $this->dispatchConsolidatedReportToTray(
            $request,
            'pdf',
            'ticket',
            'sales',
            'Consolidado de items de ventas - totales ticket',
            $this->consolidatedTotalsCount($request)
        )) {
            return $trayResponse;
        }

        [$company, $establishment] = $this->consolidatedCompanyAndEstablishment($request);
        $records = $this->totalsByItem($request)->sortBy('item_id');
        $params = $request->all();
        $reportSummary = SalesConsolidated::summary($records);
        $records = \App\Support\Exact\ReportProjection::money($records, ['total_sale', 'total_ves', 'total_usd']);
        $height = (5.8 / 2.54) * 72;
        $customPaper = [0, 0, $height, 1440];
        $pdf = PDF::loadView('report::sales_consolidated.report_pdf_totals_ticket', compact("records", "company", "establishment", "params", "reportSummary"))
            ->setPaper($customPaper);
        $filename = 'Reporte_Consolidado_Items_Ventas_Totales_'.date('YmdHis');

        return $pdf->stream($filename.'.pdf');
    }

    public function pdfTicketsTotal80(Request $request) {
        if ($trayResponse = $this->dispatchConsolidatedReportToTray(
            $request,
            'pdf',
            'ticket80',
            'sales',
            'Consolidado de items de ventas - totales ticket 80',
            $this->consolidatedTotalsCount($request)
        )) {
            return $trayResponse;
        }

        [$company, $establishment] = $this->consolidatedCompanyAndEstablishment($request);
        $records = $this->totalsByItem($request)->sortBy('item_id');
        $params = $request->all();
        $reportSummary = SalesConsolidated::summary($records);
        $records = \App\Support\Exact\ReportProjection::money($records, ['total_sale', 'total_ves', 'total_usd']);
        $height = (8 / 2.54) * 72;
        $customPaper = [0, 0, $height, 1440];
        $pdf = PDF::loadView('report::sales_consolidated.report_pdf_totals_ticket_80', compact("records", "company", "establishment", "params", "reportSummary"))
            ->setPaper($customPaper);
        $filename = 'Reporte_Consolidado_Items_Ventas_Totales_'.date('YmdHis');

        return $pdf->stream($filename.'.pdf');
    }


    public function excelTotals(Request $request) {
        if ($trayResponse = $this->dispatchConsolidatedReportToTray(
            $request,
            'xlsx',
            'totals',
            'sales',
            'Consolidado de items de ventas - totales',
            $this->consolidatedTotalsCount($request)
        )) {
            return $trayResponse;
        }

        [$company, $establishment] = $this->consolidatedCompanyAndEstablishment($request);
        $records = $this->totalsByItem($request)->sortBy('item_id');
        $params = $request->all();
        $reportSummary = SalesConsolidated::summary($records);
        $filename = 'Reporte_Consolidado_Items_Ventas_Totales_'.date('YmdHis');

        return (new SaleConsolidatedTotalExport)
                ->records($records)
                ->company($company)
                ->establishment($establishment)
                ->params($params)
                ->download($filename.'.xlsx');
    }

}
