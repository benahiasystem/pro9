<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Cash;
use App\Models\Tenant\Company;
use App\Models\Tenant\User;
use App\Support\Exact\ReadSnapshot;
use App\Support\Exact\ReportDataset;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Modules\Report\Exports\PosCashDocumentsExport;
use Modules\Report\Http\Resources\CashCollection;
use Modules\Report\Services\PosCashDocumentsReport;

/** Documents associated with authorized cash shifts; these are not receipt totals. */
class ReportCashController extends Controller
{
    public function filter()
    {
        $viewer=auth()->user();
        $users=User::query()->when($viewer->type !== 'admin',fn ($query)=>$query->where('id',$viewer->id))->get(['id','name']);
        return ['document_types'=>[
            ['id'=>'01','description'=>'Todos los documentos'],
            ['id'=>'02','description'=>'Facturas y notas fiscales'],
            ['id'=>'03','description'=>'Notas de venta'],
        ],'users'=>$users];
    }

    public function index() { return view('report::cash.index'); }

    private function report(Request $request): array
    {
        $validated=$request->validate(['document_type_id'=>'nullable|in:01,02,03','user_id'=>'nullable|integer|min:1','page'=>'sometimes|integer|min:1']);
        return PosCashDocumentsReport::build($validated['document_type_id'] ?? '01',$validated['user_id'] ?? null,auth()->user());
    }

    public function records(Request $request)
    {
        return ReadSnapshot::run((new Cash)->getConnection(),function () use ($request) {
            $report=$this->report($request);
            $dataset=new ReportDataset($report['rows']);
            $page=$dataset->paginate(max(1,min(500,(int)config('tenant.items_per_page',50))),(int)$request->input('page',1),['path'=>$request->url()]);
            return (new CashCollection($page))->response($request);
        });
    }

    public function pdf(Request $request)
    {
        return ReadSnapshot::run((new Cash)->getConnection(),function () use ($request) {
            $report=$this->report($request);$company=Company::first();
            return PDF::loadView('report::cash.documents_report',compact('report','company'))->setPaper('a4','landscape')
                ->download('Reporte_Documentos_Caja_'.date('YmdHis').'.pdf');
        });
    }

    public function excel(Request $request)
    {
        return ReadSnapshot::run((new Cash)->getConnection(),function () use ($request) {
            $report=$this->report($request);$company=Company::first();
            return (new PosCashDocumentsExport($report,$company))->download('Reporte_Documentos_Caja_'.date('YmdHis').'.xlsx');
        });
    }
}
