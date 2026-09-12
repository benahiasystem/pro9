<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use App\Models\Tenant\Establishment;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Report\Exports\StockRunningOutExport;
use Modules\Report\Http\Resources\StockRunningOutCollection;

class ReportStockRunningOutController extends Controller
{

    public function index()
    {
        return view('report::stock_running_out.index');
    }

    public function filter()
    {
        $establishments = Establishment::all()->transform(function ($row) {
            return [
                'id' => $row->id,
                'name' => $row->description
            ];
        });

        return compact('establishments');
    }

    public function records(Request $request)
    {
        $records = $this->getStockRecords($request);

        return new StockRunningOutCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function pdf(Request $request)
    {
        set_time_limit(300);

        $company = Company::first();
        $establishment = ($request->establishment_id)
            ? Establishment::findOrFail($request->establishment_id)
            : auth()->user()->establishment;

        $records = $this->getStockRecords($request)->get();
        $filters = $request->all();

        $pdf = PDF::loadView('report::stock_running_out.report_pdf', compact('records', 'company', 'establishment', 'filters'))
            ->setPaper('a4', 'landscape');

        $filename = 'Reporte_Productos_Por_Agotarse_' . date('YmdHis');

        return $pdf->download($filename . '.pdf');
    }

    public function excel(Request $request)
    {
        $company = Company::first();
        $establishment = ($request->establishment_id)
            ? Establishment::findOrFail($request->establishment_id)
            : auth()->user()->establishment;

        $records = $this->getStockRecords($request)->get();
        $filters = $request->all();

        return (new StockRunningOutExport)
            ->records($records)
            ->company($company)
            ->establishment($establishment)
            ->filters($filters)
            ->download('Reporte_Productos_Por_Agotarse_' . Carbon::now()->format('YmdHis') . '.xlsx');
    }

    /**
     * Obtener registros de stock por agotarse
     */
    private function getStockRecords(Request $request)
    {
        $establishment_id = $request->establishment_id;

        if (!$establishment_id) {
            $establishment_id = auth()->user()->establishment_id
                ?? Establishment::select('id')->first()->id;
        }

        $date_start = $request->date_start;
        $date_end = $request->date_end;

        $query = ItemWarehouse::with(['item', 'warehouse', 'warehouse.establishment'])
            ->whereHas('item', function ($q) {
                $q->whereNotIsSet();
                $q->where('status', true);
                $q->where('unit_type_id', '!=', 'ZZ');
            })
            ->whereHas('warehouse', function ($q) use ($establishment_id) {
                $q->where('establishment_id', $establishment_id);
            })
            ->where('stock', '<=', 20)
            ->orderBy('stock');

        if ($date_start) {
            $query->whereDate('item_warehouse.created_at', '>=', $date_start);
        }

        if ($date_end) {
            $query->whereDate('item_warehouse.created_at', '<=', $date_end);
        }

        return $query;
    }
}