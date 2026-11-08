<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class PurchaseExport implements  FromView, ShouldAutoSize, WithEvents
{
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
        return view('report::purchases.report_excel', [
            'records'=> $this->records,
            'company' => $this->company,
            'establishment'=>$this->establishment,
            'filters'=>$this->filters
        ]);
    }
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $prepared = \Modules\Report\Services\PurchaseReportAmounts::presentation($this->records,
                \Modules\Report\Services\PurchaseReportAmounts::converted($this->filters['apply_conversion_to_pen'] ?? false));
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $prepared['rows'],
                \Modules\Report\Services\PurchaseReportAmounts::FIELDS);
            $entries=[];
            foreach ($prepared['totals'] as $currency=>$totals) foreach ($totals as $field=>$value)
                $entries[]=['id'=>'total:'.$currency,'field'=>$field,'value'=>$value];
            \App\Support\Exact\FractionSheet::appendEntries($event->sheet->getDelegate(),$entries);
        }];
    }

}
