<?php

namespace Modules\Report\Http\Controllers;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Modules\Report\Exports\UserCommissionExport;
use Illuminate\Http\Request;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\User;
use App\Models\Tenant\Document;
use App\Models\Tenant\Company;
use Carbon\Carbon;
use Modules\Report\Http\Resources\ReportUserCommissionCollection;

class ReportUserCommissionController extends Controller
{


    public function filter() {

        $document_types = [];

        $establishments = Establishment::all()->transform(function($row) {
            return [
                'id' => $row->id,
                'name' => $row->description
            ];
        });

        return compact('document_types','establishments');
    }


    public function index() {

        return view('report::user_commissions.index');
    }

    public function records(Request $request)
    {
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        return \App\Support\Exact\ReadSnapshot::run((new User())->getConnection(), function () use ($request) {
            $records = $this->getRecords($request->all(), User::class)->paginate(config('tenant.items_per_page'));
            $amounts = \Modules\Report\Services\EstimatedUserCommission::users($records->getCollection());
            return (new ReportUserCommissionCollection($records))->withAmounts($amounts);
        });
        // ######## FIN CALCULOS EXACTOS PRO9 ########
    }



    public function getRecords($request, $model){

        $document_type_id = $request['document_type_id'];
        $establishment_id = $request['establishment_id'];
        $period = $request['period'];
        $date_start = $request['date_start'];
        $date_end = $request['date_end'];
        $month_start = $request['month_start'];
        $month_end = $request['month_end'];

        $d_start = null;
        $d_end = null;
        /** @todo: Eliminar periodo, fechas y cambiar por

        $date_start = $request['date_start'];
        $date_end = $request['date_end'];
        \App\CoreFacturalo\Helpers\Functions\FunctionsHelper\FunctionsHelper::setDateInPeriod($request, $date_start, $date_end);
         */

        switch ($period) {
            case 'month':
                $d_start = Carbon::parse($month_start.'-01')->format('Y-m-d');
                $d_end = Carbon::parse($month_start.'-01')->endOfMonth()->format('Y-m-d');
                break;
            case 'between_months':
                $d_start = Carbon::parse($month_start.'-01')->format('Y-m-d');
                $d_end = Carbon::parse($month_end.'-01')->endOfMonth()->format('Y-m-d');
                break;
            case 'date':
                $d_start = $date_start;
                $d_end = $date_start;
                break;
            case 'between_dates':
                $d_start = $date_start;
                $d_end = $date_end;
                break;
        }

        $records = $this->data($document_type_id, $establishment_id, $d_start, $d_end, $model);

        return $records;

    }


    private function data($document_type_id, $establishment_id, $date_start, $date_end, $model)
    {
        return $model::whereHas('user_commission')
            ->with(['documents'=>function ($query) use ($date_start, $date_end, $establishment_id) {
                $query->whereStateTypeAccepted()->whereBetween('date_of_issue', [$date_start, $date_end]);
                if ($establishment_id) $query->where('establishment_id', $establishment_id);
            }, 'sale_notes'=>function ($query) use ($date_start, $date_end, $establishment_id) {
                $query->whereStateTypeAccepted()->where(fn ($notes) => $notes->where('changed',false)->orWhereNotNull('document_id'))
                    ->whereBetween('date_of_issue', [$date_start, $date_end]);
                if ($establishment_id) $query->where('establishment_id', $establishment_id);
                $invoices=Document::withoutEagerLoads()->select('id')->where('document_type_id','01')
                    ->whereBetween('date_of_issue',[$date_start,$date_end])->whereStateTypeAccepted();
                if ($establishment_id) $invoices->where('establishment_id', $establishment_id);
                $query->where(fn ($notes) => $notes->whereNull('document_id')->orWhereNotIn('document_id', $invoices));
            }])->latest()->whereTypeUser();
    }


    public function pdf(Request $request) {

        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        [$records, $amounts] = \App\Support\Exact\ReadSnapshot::run((new User())->getConnection(), function () use ($request) {
            $records = $this->getRecords($request->all(), User::class)->get();
            return [$records, \Modules\Report\Services\EstimatedUserCommission::users($records)];
        });
        // ######## FIN CALCULOS EXACTOS PRO9 ########

        $pdf = PDF::loadView('report::user_commissions.report_pdf', compact("records", "company", "establishment", "amounts"));

        $filename = 'Reporte_Comision_utilidades_Vendedor_'.date('YmdHis');

        return $pdf->download($filename.'.pdf');
    }




    public function excel(Request $request) {

        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;

        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        [$records, $amounts] = \App\Support\Exact\ReadSnapshot::run((new User())->getConnection(), function () use ($request) {
            $records = $this->getRecords($request->all(), User::class)->get();
            return [$records, \Modules\Report\Services\EstimatedUserCommission::users($records)];
        });
        // ######## FIN CALCULOS EXACTOS PRO9 ########

        return (new UserCommissionExport)
                ->records($records)
                ->amounts($amounts)
                ->company($company)
                ->establishment($establishment)
                ->download('Reporte_Comision_utilidades_Vendedor'.Carbon::now().'.xlsx');

    }
}
