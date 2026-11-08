<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class PendingAccountCommissionExport implements FromView, ShouldAutoSize, WithEvents
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
        return view('report::pending-account-commissions.report_excel', [
            'records'=> $this->records,
            'company' => $this->company,
            'establishment' => $this->establishment,
            'request' => $this->request
        ]);
    }
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet=$event->sheet->getDelegate();
            foreach (['A'=>24,'B'=>16,'C'=>22,'D'=>22,'E'=>22,'F'=>34] as $column=>$width)
                $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth($width);
            $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                ->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageSetup()->setFitToPage(true);
            for ($header=1; $header<=$sheet->getHighestRow(); $header++) {
                if ($sheet->getCell('A'.$header)->getValue() !== 'Usuario') continue;
                $sheet->getStyle('A'.$header.':F'.$sheet->getHighestRow())->getAlignment()
                    ->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
                $sheet->getRowDimension($header)->setRowHeight(30);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($header,$header);
                break;
            }
            $rows=collect($this->records)->flatMap(fn ($user)=>$user['currency_rows']);
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(),$rows,
                ['total_out_of_range','total_within_range','total_completed']);
            $entries=[];
            foreach ($rows as $row) if ($row['commission'] !== null)
                $entries[]=['id'=>$row['report_key'],'field'=>'commission','value'=>\App\Support\Exact\Decimal::readRequired($row,'commission')];
            \App\Support\Exact\FractionSheet::appendEntries($event->sheet->getDelegate(),$entries);
        }];
    }

}
