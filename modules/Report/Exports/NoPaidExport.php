<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;

class NoPaidExport implements FromView, ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithEvents
{
    // ######## INICIO CALCULOS EXACTOS PRO9 ########
    protected $records;
    protected $company;
    public function registerEvents(): array
    {
        return [\Maatwebsite\Excel\Events\AfterSheet::class=>function ($event) {
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $this->records, \App\Support\Exact\Receivables::EXPORT_FIELDS);
        }];
    }
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
        return view('tenant.reports.no_paid.report_excel', [
            'records'=> $this->records,
            'reportSummary'=>\App\Support\Exact\OutstandingSummary::summarize($this->records),
            'company' => $this->company,
        ]);
    }
}
