<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Finance\Models\GlobalPayment;
use App\Models\Tenant\Cash;
use App\Models\Tenant\User;
use App\Http\Resources\Tenant\UserCollection;
use App\Models\Tenant\BankAccount;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Tenant\Company;
use Modules\Finance\Traits\FinanceTrait;
use Modules\Finance\Http\Resources\GlobalPaymentCollection;
use Modules\Finance\Exports\ToPayAllExport;
use Modules\Finance\Exports\ToPayExport;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Models\Tenant\Establishment;
use Carbon\Carbon;
use App\Models\Tenant\Person;
use Modules\Dashboard\Helpers\DashboardView;
use Modules\Finance\Helpers\ToPay;
// ######## INICIO CALCULOS EXACTOS PRO9 ########
use App\Support\Exact\{Decimal, Payables, ReportProjection};
// ######## FIN CALCULOS EXACTOS PRO9 ########
use Modules\Finance\Exports\ToPaymentMethodDayExport;


class ToPayController extends Controller
{

    use FinanceTrait;

    public function index(){

        return view('finance::to_pay.index');
    }


    public function filter(){

        $supplier_temp = Person::whereType('suppliers')->orderBy('name')->take(100)->get()->transform(function($row) {
            return [
                'id' => $row->id,
                'description' => $row->number.' - '.$row->name,
                'name' => $row->name,
                'number' => $row->number,
                'identity_document_type_id' => $row->identity_document_type_id,
            ];
        });
        $supplier= [];
        $supplier[]=[
            'id' => null,
            'description' => 'Todos',
            'name' => 'Todos',
            'number' => '',
            'identity_document_type_id' => '',
        ];
        $suppliers = array_merge($supplier,$supplier_temp->toArray());

        $query_users = User::all();
        if(auth()->user()->type === 'admin') {
            $newUser = new User(['id' => 0, 'name' => 'Seleccionar Todos']);
            $query_users = $query_users->add($newUser)->sortBy('id');
        }
        $users = new UserCollection($query_users);
        $establishments= [];
        $establishments[] = [
            'id' => 0,
            'name' => 'Todos',
        ];
        $establishments = collect($establishments);
        Establishment::all()->transform(function($row)  use(&$establishments){
            $establishments[]  = [
                'id' => $row->id,
                'name' => $row->description
            ]; }
        );

        return compact('suppliers', 'establishments', 'users');
    }


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function records(Request $request)
    {
        $data =$request->all();
        if($request->establishment_id === 0){
            $data['withBankLoan'] = 1;
            $data['stablishmentTopaidAll'] = 1; // Lista todos los establecimients
        }

        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        $request->validate(['page'=>'sometimes|integer|min:1', 'per_page'=>'sometimes|integer|between:1,500']);
        $dataset = ToPay::getToPay($data)->filter(fn($row)=>Decimal::readRequired($row, 'total_to_pay')->isPositive());
        $summary = Payables::summary($dataset);
        $page = $dataset->paginate((int)$request->input('per_page', 50), (int)$request->input('page', 1));
        return ['records'=>$page->items(), 'summary'=>$summary,
            'meta'=>['current_page'=>$page->currentPage(), 'per_page'=>$page->perPage(), 'total'=>$page->total()]];
        // ######## FIN CALCULOS EXACTOS PRO9 ########
    }

    /**
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function toPayAll()
    {

        return Excel::download(new ToPayAllExport, 'TCuentasPorPagar.xlsx');

    }


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function toPay(Request $request) {

        $company = Company::first();
        $export = new ToPayExport();
        $records = ToPay::getToPay($request->all());
        $export ->company($company)
                ->records($records);
        return $export ->download('Reporte_Cuentas_Por_Pagar'.Carbon::now().'.xlsx');

    }


    public function reportPaymentMethodDays(Request $request)
    {
        // 'records' => (new ToPay())->getToPay($request->all())

        $all_records = (new ToPay())->getToPay($request->all());

        $records = $all_records->filter(fn($row)=>Decimal::readRequired($row, 'total_to_pay')->isPositive() && $row['type'] === 'purchase')->map(function($row){
            $row['difference_days'] = Carbon::parse($row['date_of_issue'])->diffInDays($row['date_of_due']);
            return $row;
        });

        $company = Company::first();

        return (new ToPaymentMethodDayExport)
                ->company($company)
                ->records($records)
                ->download('Reporte_C_Pagar_F_Pago'.Carbon::now().'.xlsx');

    }


    public function pdf(Request $request) {

        $records = (new ToPay())->getToPay($request->all());

        $records = $records->filter(fn($row)=>Decimal::readRequired($row, 'total_to_pay')->isPositive());
        $records = ReportProjection::money($records, ['total', 'total_to_pay']);

        $company = Company::first();

        $pdf = PDF::loadView('finance::to_pay.report_pdf', compact("records", "company"));

        $filename = 'Reporte_Cuentas_Por_Pagar_'.date('YmdHis');

        return $pdf->download($filename.'.pdf');

    }


}
