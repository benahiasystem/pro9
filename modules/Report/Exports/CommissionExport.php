<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class CommissionExport implements FromView, ShouldAutoSize, WithEvents
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
    
    public function request($request) {
        $this->request = $request;
        
        return $this;
    }

    public function view(): View {
        return view('report::commissions.report_excel', [
            'records'=> $this->records,
            'company' => $this->company,
            'establishment'=>$this->establishment,
            'request'=>$this->request
        ]);
    }
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $rows=collect($this->records)->flatMap(fn($user)=>\Modules\Report\Services\SalesCommissionReport::forUser($user,$this->request)['currency_rows']);
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(),$rows,['acum_sales']);
            $entries=[];
            foreach ($rows as $row) if ($row['total_commision'] !== null)
                $entries[]=['id'=>$row['report_key'],'field'=>'total_commision','value'=>\App\Support\Exact\Decimal::readRequired($row,'total_commision')];
            \App\Support\Exact\FractionSheet::appendEntries($event->sheet->getDelegate(),$entries);
        }];
    }
}
