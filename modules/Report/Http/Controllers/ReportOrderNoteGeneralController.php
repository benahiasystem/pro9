<?php

namespace Modules\Report\Http\Controllers;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Company;
use Carbon\Carbon;
use Modules\Report\Http\Resources\OrderNoteGeneralCollection;
use Modules\Report\Traits\ReportTrait;
use Modules\Order\Models\OrderNote;
use Modules\Report\Exports\ReportOrderGeneralExport;


class ReportOrderNoteGeneralController extends Controller
{
    use ReportTrait;

    public function filter() {


        $persons = $this->getPersons('customers'); 
        $date_range_types = $this->getDateRangeTypes();
        $order_state_types = $this->getOrderStateTypes();
        $sellers = $this->getSellers();

        return compact('persons', 'date_range_types', 'order_state_types', 'sellers');
    }


    public function index() {

        return view('report::order_notes_general.index');
    }

    public function records(Request $request)
    {
        return \App\Support\Exact\ReadSnapshot::run((new OrderNote)->getConnection(), function () use ($request) {
            $records = $this->getRecordsOrderNotes($request->all(), OrderNote::class);
            return (new OrderNoteGeneralCollection($records->paginate(config('tenant.items_per_page'))))->response($request);
        });
    }



    public function getRecordsOrderNotes($request, $model){
        $request['date_range_type_id'] = $request['date_range_type_id'] ?? 'date_of_issue';
        if (!in_array($request['date_range_type_id'], array_column($this->getDateRangeTypes(),'id'), true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['date_range_type_id'=>'Seleccione un campo de fecha permitido.']);
        }
        $request += ['order_state_type_id'=>null,'person_id'=>null,'seller_id'=>null];

        $records = $this->dataOrderNotes($request, $model);

        return $records;

    }

    private function dataOrderNotes($request, $model)
    {
        $order_state_type_id = $request['order_state_type_id'];

        switch ($order_state_type_id) {
            case '01':
                $data = $model::whereToDeliverState($request);
                break;
            case '03':
                $data = $model::whereEnviadoState($request);
                break;
            case '05':
                $data = $model::whereDeliveredState($request);
                break;
            case '07':
                $data = $model::whereObservadoState($request);
                break;
            case '09':
                $data = $model::whereRejectedState($request);
                break;
            case '13':
                $data = $model::wherePorAnularState($request);
                break;
            case '11':
                $data = $model::whereVoidedState($request);
                break;
            default:
                $data = $model::whereDefaultState($request);
                break;
        }

        return $data->whereTypeUser()->latest();
    }


    public function pdf(Request $request) {
        return \App\Support\Exact\ReadSnapshot::run((new OrderNote)->getConnection(), function () use ($request) {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        $records = $this->getRecordsOrderNotes($request->all(), OrderNote::class)->get();
        $params = $request->all() + ['person_id'=>null];

        $pdf = PDF::loadView('report::order_notes_general.report_pdf', compact("records", "company", "establishment", "params"))->setPaper('a4','landscape');

        $filename = 'Reporte_General_Pedidos_'.date('YmdHis');

        return $pdf->download($filename.'.pdf');
        });
    }

    public function excel(Request $request) {
        return \App\Support\Exact\ReadSnapshot::run((new OrderNote)->getConnection(), function () use ($request) {
        $company = Company::first();
        $records = $this->getRecordsOrderNotes($request->all(), OrderNote::class)->get();
        /* dd($records); */
        $documentHotelExport = new ReportOrderGeneralExport();
        $documentHotelExport
            ->records($records)
            ->company($company);

        return $documentHotelExport->download('Reporte_General_Pedidos' . Carbon::now() . '.xlsx');
        });
    }

}
