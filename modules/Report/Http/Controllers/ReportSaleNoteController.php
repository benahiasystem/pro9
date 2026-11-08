<?php

namespace Modules\Report\Http\Controllers;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Modules\Item\Models\WebPlatform;
use Modules\Report\Exports\SaleNoteExport;
use Illuminate\Http\Request;
use Modules\Report\Traits\ReportTrait;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\Company;
use Carbon\Carbon;
use Modules\Report\Http\Resources\SaleNoteReportCollection;
use Modules\Report\Services\SaleNoteReportAmounts;
use App\Support\Exact\ReadSnapshot;

class ReportSaleNoteController extends Controller
{
    use ReportTrait;


    public function filter() {

        $document_types = [];

        $establishments = Establishment::all()->transform(function($row) {
            return [
                'id' => $row->id,
                'name' => $row->description
            ];
        });

        $sellers = $this->getSellers();
        $web_platforms = WebPlatform::get();
        $users = $this->getUsers();


        return compact(
            'users',
            'document_types',
            'establishments',
            'sellers',
            'web_platforms'
        );
    }


    public function index() {

        return view('report::sale_notes.index');
    }

    /**
     * @param Request $request
     * @return SaleNoteCollection
     */
    public function records(Request $request)
    {
        $request->validate(['page'=>'sometimes|integer|min:1']);
        return ReadSnapshot::run((new SaleNote)->getConnection(), function () use ($request) {
            $records = $this->getRecords($request->all(), SaleNote::class)->orderByDesc('id');
            $page = (clone $records)->paginate(max(1, min(500, (int) config('tenant.items_per_page', 50))), ['*'], 'page', (int) $request->input('page', 1));
            $fields = array_merge(['id','currency_type_id','state_type_id','calculation_snapshot'], SaleNoteReportAmounts::FIELDS);
            $all = (clone $records)->withoutEagerLoads()->select(array_unique($fields))->lazy(500);
            $display = SaleNoteReportAmounts::displayForPage($all, $page->getCollection()->pluck('id'));
            return (new SaleNoteReportCollection($page, $display))->response($request);
        });
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function pdf(Request $request) {
        return ReadSnapshot::run((new SaleNote)->getConnection(), function () use ($request) {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        $records = $this->getRecords($request->all(), SaleNote::class)->orderByDesc('id')->get();
        $filters = $request->all();
        $pdf = PDF::loadView('report::sale_notes.report_pdf', compact("records", "company", "establishment", "filters"))->setPaper('a4', 'landscape');
        $filename = 'Reporte_Nota_Ventas_'.date('YmdHis');
        return $pdf->download($filename.'.pdf');
        });
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function excel(Request $request)
    {
        return ReadSnapshot::run((new SaleNote)->getConnection(), function () use ($request) {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;

        $records = $this->getRecords($request->all(), SaleNote::class)->orderByDesc('id')->get();
        $filters = $request->all();
        $SaleNoteExport = new SaleNoteExport();

        $SaleNoteExport
            ->records($records)
            ->company($company)
            ->establishment($establishment)
            ->filters($filters);

        return $SaleNoteExport->download('Reporte_Nota_Ventas_' . Carbon::now() . '.xlsx');
        });

    }
}
