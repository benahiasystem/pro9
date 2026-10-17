<?php

namespace Modules\Finance\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use App\Models\Tenant\Establishment; 
use Carbon\Carbon;
use Modules\Finance\Helpers\ToPay;

class ToPayAllExport implements FromView, \Maatwebsite\Excel\Concerns\WithEvents
{
    // ######## INICIO CALCULOS EXACTOS PRO9 ########
    protected $records;
    protected $company;
    protected $establishment;
    protected $params;
    // ######## FIN CALCULOS EXACTOS PRO9 ########


    public function view(): View
    { 

        $records = ToPay::getToPayNoFilter()->filter(fn($row)=>\App\Support\Exact\Decimal::readRequired($row, 'total_to_pay')->isPositive());
        $this->records = $records;
        
        $company = DB::connection('tenant')->table('companies')->select('name', 'number')->get();

        return view('finance::to_pay.reportall_excel',['records' => $records,
            'companies' => $company ]);
         
    }

    // ######## INICIO CALCULOS EXACTOS PRO9 ########
    public function registerEvents(): array
    {
        return [\Maatwebsite\Excel\Events\AfterSheet::class=>function ($event) {
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $this->records,
                ['total', 'total_payable', 'total_payment', 'total_to_pay', 'exact_balance', 'settlement_adjustment']);
        }];
    }
    // ######## FIN CALCULOS EXACTOS PRO9 ########

}
