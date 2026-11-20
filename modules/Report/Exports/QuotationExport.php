<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class QuotationExport implements  FromView, ShouldAutoSize, WithEvents
{
    // Explicit properties preserve the public export contract on PHP 8.2.
    public $company;
    public $establishment;
    public $filters;
    public $records;

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
    
    public function filters($filters) {
        $this->filters = $filters;
        
        return $this;
    }

    public function view(): View {
        return view('report::quotations.report_excel', [
            'records'=> $this->records,
            'company' => $this->company,
            'establishment'=>$this->establishment,
            'filters'=>$this->filters
        ]);
    }
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $rows = collect($this->records)->map(fn ($row) => \Modules\Report\Services\QuotationReportAmounts::row($row));
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $rows,
                \Modules\Report\Services\QuotationReportAmounts::FIELDS);
        }];
    }

}
