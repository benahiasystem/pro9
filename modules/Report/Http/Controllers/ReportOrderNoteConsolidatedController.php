<?php

namespace Modules\Report\Http\Controllers;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Company;
use Carbon\Carbon;
use Modules\Report\Exports\OrderNoteConsolidatedTotalExport;
use Modules\Report\Http\Resources\OrderNoteConsolidatedCollection;
use Modules\Report\Traits\ReportTrait;
use Modules\Report\Traits\ConsolidatedReportTrayTrait;
use Modules\Order\Models\OrderNoteItem;


class ReportOrderNoteConsolidatedController extends Controller
{
    use ReportTrait;
    use ConsolidatedReportTrayTrait;

    public function filter() {


        $persons = $this->getPersons('customers');
        $date_range_types = $this->getDateRangeTypes();
        $order_state_types = $this->getOrderStateTypes();
        $sellers = $this->getSellers();

        return compact('persons', 'date_range_types', 'order_state_types', 'sellers');
    }


    public function index() {

        return view('report::order_notes_consolidated.index');
    }

    public function records(Request $request)
    {
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        return \App\Support\Exact\ReadSnapshot::run((new OrderNoteItem())->getConnection(), function () use ($request) {
            $records = $this->getRecordsOrderNotes($request->all(), OrderNoteItem::class)
                ->with('order_note.user')->paginate(config('tenant.items_per_page'));
            $amounts = \Modules\Report\Services\OrderNoteConsolidatedAmounts::rows($records->getCollection());
            return (new OrderNoteConsolidatedCollection($records))->withAmounts($amounts);
        });
        // ######## FIN CALCULOS EXACTOS PRO9 ########
    }


    /**
     * @param array                $request
     * @param OrderNoteItem::class $model
     *
     * @return $this|\Illuminate\Database\Query\Builder
     */
    public function getRecordsOrderNotes($request,  $model){

        // dd($request);

        $records = $this->dataOrderNotes($request, $model)->orderBy('order_note_items.id');

        return $records;

    }


    /**
     * @param array         $request
     * @param OrderNoteItem $model
     *
     * @return mixed
     */
    private function dataOrderNotes($request,  $model)
    {
        $order_state_type_id = $request['order_state_type_id'];
        switch ($order_state_type_id) {

            case 'pending':
                $data = $model::wherePendingState($request);
                break;

            case 'processed':
                $data = $model::whereProcessedState($request);
                break;

            default:
                $data = $model::whereDefaultState($request);
                break;
        }

        return $data;

    }


    public function pdf(Request $request) {

        if ($trayResponse = $this->dispatchConsolidatedReportToTray(
            $request,
            'pdf',
            'detail',
            'order_notes',
            'Consolidado de items de pedidos',
            $this->getRecordsOrderNotes($request->all(), OrderNoteItem::class)->count()
        )) {
            return $trayResponse;
        }

        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        [$records, $reportAmounts] = \App\Support\Exact\ReadSnapshot::run((new OrderNoteItem())->getConnection(), function () use ($request) {
            $records = $this->getRecordsOrderNotes($request->all(), OrderNoteItem::class)
                ->with('order_note.user')->get()->sortBy(fn($row)=>$row->order_note->user->name);
            return [$records, \Modules\Report\Services\OrderNoteConsolidatedAmounts::rows($records)];
        });
        // ######## FIN CALCULOS EXACTOS PRO9 ########
        $params = $request->all();

        $pdf = PDF::loadView('report::order_notes_consolidated.report_pdf', compact("records", "company", "establishment", "params", "reportAmounts"));

        $filename = 'Reporte_Consolidado_Items_'.date('YmdHis');

        return $pdf->download($filename.'.pdf');
    }


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Support\Collection
     */
    public function totalsByItem(Request $request) {
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        return \App\Support\Exact\ReadSnapshot::run((new OrderNoteItem())->getConnection(), function () use ($request) {
        // dd($request->all());
        //$records = $this->getRecordsSalesConsolidated($request->all())->get()->groupBy('item_id');
        $records = $this->getRecordsOrderNotes($request->all(), OrderNoteItem::class)->with('relation_item')->get()->groupBy('item_id');

        return $records->map(function ($row, $key) {
            /**
             * @var \Illuminate\Database\Eloquent\Collection $row
             * @var \Modules\Order\Models\OrderNoteItem $item
             */
            $item = $row->first();
            // ######## INICIO CALCULOS EXACTOS PRO9 ########
            $quantity = \Modules\Report\Services\OrderNoteConsolidatedAmounts::quantity($row);
            return [
                'item_id'           => $key,
                'report_key'        => 'item:'.$key,
                'item_internal_id'  => $item->relation_item->internal_id,
                'item_unit_type_id' => $item->relation_item->unit_type_id,
                'item_description'  => $item->item->description,
                'quantity'          => \App\Support\Exact\Decimal::represent($quantity),
                'calculation_snapshot' => \App\Support\Exact\Decimal::snapshot(['quantity'=>$quantity]),
            ];
            // ######## FIN CALCULOS EXACTOS PRO9 ########
        });
        });
        // ######## FIN CALCULOS EXACTOS PRO9 ########

    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function pdfTotals(Request $request) {

        if ($trayResponse = $this->dispatchConsolidatedReportToTray(
            $request,
            'pdf',
            'totals',
            'order_notes',
            'Consolidado de items de pedidos - totales',
            $this->totalsByItem($request)->count()
        )) {
            return $trayResponse;
        }

        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id)
            : auth()->user()->establishment;
        $records = $this->totalsByItem($request)->sortBy('item_id');
        $params = $request->all();
        $pdf = PDF::loadView('report::order_notes_consolidated.report_pdf_totals',
                             compact('records', 'company', 'establishment', 'params'));
        $filename = 'Reporte_Consolidado_Items_Pedidos_Totales_'.date('YmdHis');

        return $pdf->download($filename.'.pdf');
    }


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function excelTotals(Request $request) {

        if ($trayResponse = $this->dispatchConsolidatedReportToTray(
            $request,
            'xlsx',
            'totals',
            'order_notes',
            'Consolidado de items de pedidos - totales',
            $this->totalsByItem($request)->count()
        )) {
            return $trayResponse;
        }

        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id)
            : auth()->user()->establishment;
        $records = $this->totalsByItem($request)->sortBy('item_id');
        $params = $request->all();
        $filename = 'Reporte_Consolidado_Items_Pedidos_Totales_'.date('YmdHis');
        $SaleConsolidatedTotalExport = new OrderNoteConsolidatedTotalExport();
        $SaleConsolidatedTotalExport->setRecords($records)
                                    ->setCompany($company)
                                    ->setEstablishment($establishment)
                                    ->setParams($params);

        return $SaleConsolidatedTotalExport->download($filename.'.xlsx');

    }

    // public function excel(Request $request) {

    //     $company = Company::first();
    //     $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;

    //     $records = $this->getRecordsCustomers($request->all(), Document::class)->get();

    //     return (new CustomerExport)
    //             ->records($records)
    //             ->company($company)
    //             ->establishment($establishment)
    //             ->download('Reporte_Ventas_por_Cliente_'.Carbon::now().'.xlsx');

    // }

}
