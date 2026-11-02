<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Exact fractions backing the displayed document and receipt values. */
final class PaymentFractionsExport implements FromView, WithTitle
{
    public function __construct(private $records) {}
    public function title(): string { return 'Fracciones exactas'; }
    public function view(): View
    {
        return view('tenant.reports.payments.fractions_excel', ['records'=>$this->records]);
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
