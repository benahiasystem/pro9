<?php

namespace Modules\Report\Http\Controllers;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Modules\Report\Exports\FixedAssetPurchaseExport;
use Illuminate\Http\Request;
use Modules\Report\Traits\ReportTrait;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Purchase;
use App\Models\Tenant\Company;
use Carbon\Carbon;
use Modules\Report\Http\Resources\FixedAssetPurchaseCollection;
use Modules\Purchase\Models\FixedAssetPurchase;

class ReportFixedAssetPurchaseController extends Controller
{

    use ReportTrait;

    public function filter() {

        $document_types = DocumentType::whereIn('id', ['01', '03','GU75', 'NE76'])->get();

        $persons = $this->getPersons('suppliers');
        $sellers = $this->getSellers();

        $establishments = Establishment::all()->transform(function($row) {
            return [
                'id' => $row->id,
                'name' => $row->description
            ];
        });

        return compact('document_types','establishments', 'persons', 'sellers');
    }


    public function index() {

        return view('report::fixed-asset-purchases.index');
    }

    public function records(Request $request)
    {
        $request->validate(['page'=>'sometimes|integer|min:1']);
        return \App\Support\Exact\ReadSnapshot::run((new FixedAssetPurchase)->getConnection(), function () use ($request) {
            $records = $this->getRecords($request->all(), FixedAssetPurchase::class)->orderByDesc('id');
            $page = (clone $records)->paginate(max(1, min(500, (int) config('tenant.items_per_page', 50))), ['*'], 'page', (int) $request->input('page', 1));
            $fields = array_merge(['id','currency_type_id','exchange_rate_sale','state_type_id','calculation_snapshot'], \Modules\Report\Services\PurchaseReportAmounts::FIELDS);
            $all = (clone $records)->withoutEagerLoads()->select(array_unique($fields))->lazy(500);
            $display = \Modules\Report\Services\PurchaseReportAmounts::displayForPage($all, $page->getCollection()->pluck('id'), false, 'fixed_asset_purchase');
            return (new FixedAssetPurchaseCollection($page, $display))->response($request);
        });
    }



    public function pdf(Request $request) {
        return \App\Support\Exact\ReadSnapshot::run((new FixedAssetPurchase)->getConnection(), function () use ($request) {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        $records = $this->getRecords($request->all(), FixedAssetPurchase::class)->orderByDesc('id')->get();
        $filters = $request->all();

        $pdf = PDF::loadView('report::fixed-asset-purchases.report_pdf', compact("records", "company", "establishment", "filters"))->setPaper('a4','landscape');

        $filename = 'Reporte_A_Fijos_Compras_'.date('YmdHis');

        return $pdf->download($filename.'.pdf');
        });
    }




    public function excel(Request $request) {
        return \App\Support\Exact\ReadSnapshot::run((new FixedAssetPurchase)->getConnection(), function () use ($request) {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        $records = $this->getRecords($request->all(), FixedAssetPurchase::class)->orderByDesc('id')->get();
        $filters = $request->all();

        return (new FixedAssetPurchaseExport)
                ->records($records)
                ->company($company)
                ->establishment($establishment)
                ->filters($filters)
                ->download('Reporte_A_Fijos_Compras_'.Carbon::now().'.xlsx');
        });

    }
}
