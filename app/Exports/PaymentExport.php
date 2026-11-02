<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
class PaymentExport implements FromView, ShouldAutoSize, WithMultipleSheets, WithTitle
{
    use Exportable;

    private $records;
    private int $payment_count = 0;

    public function records($records) { $this->records = $records; return $this; }
    public function payment_count($value) { $this->payment_count = $value; return $this; }
    public function title(): string { return 'Pagos'; }
    public function sheets(): array { return [$this, new PaymentFractionsExport($this->records)]; }
    public function view(): View
    {
        return view('tenant.reports.payments.report_excel', [
            'records'=>$this->records, 'payment_count'=>$this->payment_count,
        ]);
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
