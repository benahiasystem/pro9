<?php

namespace Modules\Finance\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;

class PaymentMethodTypeExport implements  FromView, ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithEvents
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
    
    public function view(): View {
        return view('finance::payment_method_types.report_excel', [
            'records'=> $this->records,
            'company' => $this->company,
            'establishment'=>$this->establishment
        ]);
    }

    // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
    public function registerEvents(): array
    {
        return [\Maatwebsite\Excel\Events\AfterSheet::class=>function($event) {
            $rows=$this->records['classified'] ?? [];
            if (count($rows)) \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(),$rows,['opening','income','egress','balance']);
        }];
    }
    // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
}
