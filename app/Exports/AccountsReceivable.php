<?php

namespace App\Exports;

use App\Support\Exact\{Receivables, FractionSheet};
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\{FromView, WithEvents};

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class AccountsReceivable implements FromView, WithEvents
{
    private $records;

    public function view(): View
    {
        $this->records = Receivables::dataset([], true);
        return view('tenant.reports.no_paid.reportall_excel', ['records'=>$this->records, 'reportSummary'=>\App\Support\Exact\OutstandingSummary::summarize($this->records),
            'companies'=>DB::connection('tenant')->table('companies')->get(['name','number'])]);
    }

    public function registerEvents(): array
    {
        return [\Maatwebsite\Excel\Events\AfterSheet::class=>function ($event) {
            FractionSheet::append($event->sheet->getDelegate(), $this->records, Receivables::EXPORT_FIELDS);
        }];
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
