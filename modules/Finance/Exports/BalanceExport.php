<?php

namespace Modules\Finance\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;

class BalanceExport implements  FromView, ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithEvents
{
    use Exportable;

    // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
    protected $records;
    protected $data = [];
    protected $company;
    protected $establishment;

    public function registerEvents(): array
    {
        return [\Maatwebsite\Excel\Events\AfterSheet::class=>function($event) {
            $rows=$this->data['classified'] ?? [];
            if (count($rows)) \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(),$rows,['opening','income','egress','balance']);
        }];
    }
    // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

    public function records($records) {
        $this->records = $records;

        return $this;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setData($data= []) {
        $this->data = $data;

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
        return view('finance::balance.report_excel', [
            'records'=> $this->records,
            'data'=> $this->data,
            'company' => $this->company,
            'establishment'=>$this->establishment
        ]);
    }
}
