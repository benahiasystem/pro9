<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ReportTipExport implements  FromView, ShouldAutoSize, WithEvents
{
    use Exportable;

    public function data($data)
    {
        $this->data = $data;
        return $this;
    }

    public function view(): View 
    {
        return view('report::tips.report_excel', $this->data);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $this->data['records'], ['total']);
        }];
    }

}
