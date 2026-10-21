<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;

class SaleConsolidatedTotalExport implements FromView, ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithEvents
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
    
    public function establishment($establishment) {
        $this->establishment = $establishment;
        
        return $this;
    }
    
    public function params($params) {
        $this->params = $params;
        
        return $this;
    }

    public function view(): View {
        return view('report::sales_consolidated.report_excel_totals', [
            'records'=> $this->records,
            'reportSummary'=>\App\Support\Exact\SalesConsolidated::summary($this->records),
            'company' => $this->company,
            'establishment'=>$this->establishment,
            'params'=>$this->params
        ]);
    }

    // ######## INICIO CALCULOS EXACTOS PRO9 ########
    public function registerEvents(): array
    {
        return [\Maatwebsite\Excel\Events\AfterSheet::class=>function ($event) {
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $this->records,
                array_merge(['quantity', 'total_sale', 'total_ves', 'total_usd'], \App\Support\Exact\HeaderAllocation::FIELDS));
        }];
    }
    // ######## FIN CALCULOS EXACTOS PRO9 ########

}
