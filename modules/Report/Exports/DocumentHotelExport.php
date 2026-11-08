<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class DocumentHotelExport implements  FromView, ShouldAutoSize, WithEvents
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
     
    
    public function view(): View {
        return view('report::document_hotels.report_excel', [
            'records'=> $this->records,
            'company' => $this->company,
        ]);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $rows = $this->records->map(function ($hotel) {
                $value = \App\Support\Exact\Decimal::readRequired($hotel->document, 'total');
                return ['report_key'=>'hotel:'.$hotel->id.':document:'.$hotel->document_id,
                    'total'=>\App\Support\Exact\Decimal::represent($value),
                    'calculation_snapshot'=>\App\Support\Exact\Decimal::snapshot(['total'=>$value])];
            });
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $rows, ['total']);
        }];
    }
}
