<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use App\Support\Exact\{Decimal, FractionSheet, LegacyKardexRow};

class KardexExport implements FromView, ShouldAutoSize, WithEvents
{
    use Exportable;
    
    public function balance($balance) {
        $this->balance = $balance;
        
        return $this;
    }
    
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
        return view('tenant.reports.kardex.report_excel', [
            'balance'=> $this->balance,
            'records'=> $this->records,
            'company' => $this->company,
            'establishment'=>$this->establishment
        ]);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function ($event) {
            $sheet=$event->sheet->getDelegate();
            FractionSheet::append($sheet, $this->records, ['quantity']);
            $balance=Decimal::of($this->balance); $entries=[];
            foreach ($this->records as $record) {
                $balance=$balance->plus(LegacyKardexRow::from($record)['delta']);
                $entries[]=['id'=>$record->id,'field'=>'balance','value'=>$balance];
            }
            FractionSheet::appendEntries($sheet,$entries);
        }];
    }
}
