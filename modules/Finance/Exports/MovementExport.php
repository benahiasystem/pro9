<?php

namespace Modules\Finance\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;

class MovementExport implements FromView, ShouldAutoSize, WithTitle, \Maatwebsite\Excel\Concerns\WithEvents
{
    use Exportable;

    public function title(): string
    {
        return substr('Mov. de ingresos y egresos', 0, 30);
    }

    public function records($records)
    {
        $this->records = $records;

        return $this;
    }

    public function company($company)
    {
        $this->company = $company;

        return $this;
    }

    public function establishment($establishment) {
        $this->establishment = $establishment;

        return $this;
    }

    /**
     * @return bool
     */
    public function isNewFormat(){
        if(!property_exists($this,'newFormat')){
            $this->newFormat = false;
        }
        return (bool) $this->newFormat;
    }

    /**
     * @param bool $newFormat
     *
     * @return MovementExport
     */
    public function setNewFormat(bool $newFormat)
    : MovementExport {
        $this->newFormat = $newFormat;
        return $this;
    }

    public function view(): View {
        if($this->isNewFormat()){
            return view('finance::movements.new_report_excel', [
                'records'=> $this->records,
                'company' => $this->company,
                'establishment'=>$this->establishment
            ]);
        }
        return view('finance::movements.report_excel', [
            'records'=> $this->records,
            'company' => $this->company,
            'establishment'=>$this->establishment
        ]);
    }

    // ######## INICIO CALCULOS EXACTOS PRO9 ########
    public function registerEvents(): array
    {
        return [\Maatwebsite\Excel\Events\AfterSheet::class => function ($event) {
            $rows = collect($this->records)->filter(function ($row) {
                return isset($row['calculation_snapshot']['values']['amount']) || isset($row['payments']);
            })->map(function ($row) {
                // Los registros históricos sin importe recuperable no se inventan como cero.
                $row['amount'] = $row['payments'] ?? null;
                return $row;
            });
            if ($rows->isNotEmpty()) {
                \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $rows, ['amount']);
            }
        }];
    }
    // ######## FIN CALCULOS EXACTOS PRO9 ########
}
