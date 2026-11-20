<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class UserCommissionExport implements FromView, ShouldAutoSize, WithEvents
{
    // Explicit properties preserve the public export contract on PHP 8.2.
    public $company;
    public $establishment;
    public $records;

    use Exportable;

    private array $amounts = [];

    public function amounts(array $amounts) {
        $this->amounts = $amounts;
        return $this;
    }
    
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
    
    public function view(): View {
        return view('report::user_commissions.report_excel', [
            'records'=> $this->records,
            'amounts'=> $this->amounts,
            'company' => $this->company,
            'establishment'=>$this->establishment
        ]);
    }

    // ######## INICIO CALCULOS EXACTOS SAB ########
    public function registerEvents(): array {
        return [AfterSheet::class=>function (AfterSheet $event) {
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $this->amounts, ['total_utility','commission']);
        }];
    }
    // ######## FIN CALCULOS EXACTOS SAB ########
}
