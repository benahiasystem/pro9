<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class GuidesConsolidatedTotalExport implements FromView, ShouldAutoSize, WithEvents
{
    // Explicit properties preserve the public export contract on PHP 8.2.
    public $company;
    public $establishment;
    public $params;
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

    public function params($params) {
        $this->params = $params;

        return $this;
    }

    public function view(): View {
        return view('report::guides.report_excel_totals', [
            'records'=> $this->records,
            'company' => $this->company,
            'establishment'=>$this->establishment,
            'params'=>$this->params
        ]);
    }

    // ######## INICIO CALCULOS EXACTOS SAB ########
    public function registerEvents(): array {
        return [AfterSheet::class=>function (AfterSheet $event) {
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $this->records, ['quantity']);
        }];
    }
    // ######## FIN CALCULOS EXACTOS SAB ########

}
