<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ItemExport implements  FromView, ShouldAutoSize, WithEvents
{
    // Explicit properties preserve the public export contract on PHP 8.2.
    public $company;
    public $establishment;
    public $records;
    public $type;


    use Exportable;
    protected $withExtraData;
    /**
     * @return bool
     */
    public function isWithExtraData(): bool
    {
        return (bool)$this->withExtraData;
    }

    /**
     * @param bool $withExtraData
     *
     * @return ItemExport
     */
    public function setWithExtraData(bool $withExtraData = false): ItemExport
    {
        $this->withExtraData = (bool) $withExtraData;
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

    /**
     * @return string
     */
    public function getType()
    : string {
        return empty($this->type)?'':$this->type;
    }

    /**
     * @param string $type
     *
     * @return ItemExport
     */
    public function setType(string $type)
    : ItemExport {
        $this->type = $type;
        return $this;
    }


    public function view(): View {
        $view = "report_excel";
        if($this->isWithExtraData()){
            $view = "report_excel_extra_data";
        }
        return view('report::items.'.$view, [
            'records'=> $this->records,
            'company' => $this->company,
            'type' => $this->getType(),
            'establishment'=>$this->establishment
        ]);
    }
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet=$event->sheet->getDelegate();
            $last=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
            for ($index=1; $index<=$last; $index++) {
                $column=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
                $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth(in_array($column,['F','G','H'],true) ? 25 : 16);
            }
            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                ->setFitToWidth(2)->setFitToHeight(0);
            $sheet->getPageSetup()->setFitToPage(true);
            $sheet->getPageSetup()->setColumnsToRepeatAtLeftByStartAndEnd('A','D');
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
            $prepared=\Modules\Report\Services\ItemReportAmounts::presentation($this->records);
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(),$prepared['rows'],['quantity','total']);
            $entries=[];
            foreach ($prepared['amounts'] as $currency=>$amount) $entries[]=['id'=>'total:'.$currency,'field'=>'total','value'=>$amount];
            foreach ($prepared['quantities'] as $unit=>$quantity) $entries[]=['id'=>'unit:'.$unit,'field'=>'quantity','value'=>$quantity];
            \App\Support\Exact\FractionSheet::appendEntries($event->sheet->getDelegate(),$entries);
        }];
    }

}
