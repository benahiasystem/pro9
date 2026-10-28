<?php

namespace Modules\Finance\Http\Controllers;

use App\Models\Tenant\Company;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\PaymentMethodType;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Expense\Models\ExpenseMethodType;
use Modules\Finance\Exports\PaymentMethodTypeExport;
use Modules\Finance\Traits\FinanceTrait;


class PaymentMethodTypeController extends Controller
{

    use FinanceTrait;

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\View\View
     */
    public function index(){
      $configuration = Configuration::first();
      return view('finance::payment_method_types.index', compact('configuration'));
    }


    public function filter(){

        $payment_types = [];
        $destination_types = [];

        return compact('payment_types', 'destination_types');
    }


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function records(Request $request) {
        return $this->getRecords($request->all());
    }

    /**
     * @param array $request
     *
     * @return array
     */
    public function getRecords($request = []) {
        return \App\Support\Exact\ReadSnapshot::run(\Illuminate\Support\Facades\DB::connection('tenant'),fn()=>$this->readRecords($request));
    }

    private function readRecords($request)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $dates=$this->getDatesOfPeriod($request);
        return [
            'records'=>collect(),'totals'=>[],
            'classified'=>\App\Services\Payments\FinancialReport::groups($dates['d_start'],$dates['d_end'],auth()->user()->type === 'seller' ? auth()->id() : null),
        ];
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function pdf(Request $request) {

        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        $records = $this->getRecords($request->all());
        $pdf = PDF::loadView('finance::payment_method_types.report_pdf', compact("records", "company", "establishment"));
        $filename = 'Metodos_de_pago_'.date('YmdHis');
        return $pdf->download($filename.'.pdf');
    }


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function excel(Request $request) {

        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        $records = $this->getRecords($request->all());

        $payment = new PaymentMethodTypeExport();
        $payment->records($records)
                ->company($company)
                ->establishment($establishment);
        return $payment->download('Metodos_de_pago_'.Carbon::now().'.xlsx');

    }

    public function active(Request $request)
    {
        $id = $request->input('id');
        $unit_type = PaymentMethodType::findOrFail($id);
        $unit_type->is_active = $request->boolean('is_active') ? 1 : 0;
        $unit_type->save();

        return [
            'success' => true,
            'message' => 'Estado actualizado con éxito',
        ];
    }
}
