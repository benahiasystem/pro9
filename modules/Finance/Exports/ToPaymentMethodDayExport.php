<?php

namespace Modules\Finance\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;

class ToPaymentMethodDayExport implements FromView, ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithEvents
{
    // ######## INICIO CALCULOS EXACTOS PRO9 ########
    protected $records;
    protected $company;
    protected $establishment;
    protected $params;
    // ######## FIN CALCULOS EXACTOS PRO9 ########

    use Exportable;
    
    public function records($records) {
        $this->records = $records;
        
        return $this;
    }
    
    public function company($company) {
        $this->company = $company;
        
        return $this;
    }
     
    
    public function view(): View {
        return view('finance::to_pay.reports.payment_method_days_excel', [
            'records'=> $this->records,
            'reportSummary'=>\App\Support\Exact\OutstandingSummary::summarize($this->records),
            'company' => $this->company,
        ]);
    }
    // ######## INICIO CALCULOS EXACTOS PRO9 ########
    public function registerEvents(): array
    {
        return [\Maatwebsite\Excel\Events\AfterSheet::class=>function ($event) {
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $this->records,
                ['total', 'total_payable', 'total_payment', 'total_to_pay', 'exact_balance', 'settlement_adjustment', 'total_ves_equivalent', 'balance_ves_equivalent']);
        }];
    }
    // ######## FIN CALCULOS EXACTOS PRO9 ########

}
