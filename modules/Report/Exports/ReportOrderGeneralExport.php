<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ReportOrderGeneralExport implements  FromView, ShouldAutoSize, WithEvents
{
    // Explicit properties preserve the public export contract on PHP 8.2.
    public $company;
    public $records;

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
        return view('report::order_notes_general.report_general_excel', [
            'records'=> $this->records,
            'company' => $this->company,
        ]);
    }
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet=$event->sheet->getDelegate();
            $last=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
            for ($index=1; $index<=$last; $index++) {
                $column=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
                $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth(in_array($column,['B','D','O','P','Q','R'],true) ? 25 : 16);
            }
            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                ->setFitToWidth(2)->setFitToHeight(0);
            $sheet->getPageSetup()->setFitToPage(true);
            $sheet->getPageSetup()->setColumnsToRepeatAtLeftByStartAndEnd('A','E');
            for ($header=1; $header<=$sheet->getHighestRow(); $header++) {
                if ($sheet->getCell('A'.$header)->getValue() !== '#') continue;
                $sheet->getStyle('A'.$header.':'.$sheet->getHighestColumn().$sheet->getHighestRow())->getAlignment()
                    ->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
                $sheet->getRowDimension($header)->setRowHeight(30);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($header,$header);
                for ($row=$header+1; $row<=$sheet->getHighestRow(); $row++)
                    if (is_numeric($sheet->getCell('A'.$row)->getValue())) $sheet->getRowDimension($row)->setRowHeight(45);
                break;
            }
            $rows=collect($this->records)->map(fn($order)=>\Modules\Report\Services\OrderGeneralAmounts::row($order));
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(),$rows,\Modules\Report\Services\OrderGeneralAmounts::FIELDS);
            $entries=[]; foreach ($rows as $row) foreach ($row['unit_fractions'] as $entry) $entries[]=$entry;
            \App\Support\Exact\FractionSheet::appendEntries($event->sheet->getDelegate(),$entries);
        }];
    }

}
