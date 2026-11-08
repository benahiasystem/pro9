<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class StatusClientExport implements  FromView, ShouldAutoSize, WithEvents
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

    public function filters($filters) {
        $this->filters = $filters;

        return $this;
    }

    public function categories($categories) {
        $this->categories = $categories;

        return $this;
    }

    public function categories_services($categories_services) {
        $this->categories_services = $categories_services;

        return $this;
    }

    public function view(): View {
        return view('report::state_account.report_excel', [
            'records'=> $this->records,
            'company' => $this->company,
            'establishment'=>$this->establishment,
            'filters'=>$this->filters,
            'categories'=>$this->categories,
            'categories_services'=>$this->categories_services,
        ]);
    }
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
            for ($index = 1; $index <= $lastColumn; $index++) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
                $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth(in_array($column, ['B','C','D','E','F','G','H','I','N','O','W','X'], true) ? 23 : 14);
            }
            $sheet->getColumnDimension('A')->setWidth(18);
            $sheet->getColumnDimension('I')->setWidth(30);
            $sheet->getColumnDimension('J')->setWidth(18);
            $sheet->getPageSetup()->setColumnsToRepeatAtLeftByStartAndEnd('A', 'D');
            for ($header = 1; $header <= $sheet->getHighestRow(); $header++) {
                if ($sheet->getCell('A'.$header)->getValue() !== '#') continue;
                $sheet->getStyle('A'.$header.':'.$sheet->getHighestColumn().$header)->getAlignment()->setWrapText(true);
                $sheet->getRowDimension($header)->setRowHeight(30);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($header, $header);
                $sheet->getStyle('A'.($header + 1).':'.$sheet->getHighestColumn().$sheet->getHighestRow())
                    ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
                foreach (['I','N','O','W','X'] as $column) {
                    $sheet->getStyle($column.($header + 1).':'.$column.$sheet->getHighestRow())->getAlignment()
                        ->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
                }
                for ($row = $header + 1; $row <= $sheet->getHighestRow(); $row++) {
                    if (is_numeric($sheet->getCell('A'.$row)->getValue())) $sheet->getRowDimension($row)->setRowHeight(60);
                }
                break;
            }
            $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                ->setFitToWidth(2)->setFitToHeight(0);
            $sheet->getPageSetup()->setFitToPage(true);
            $rows = \Modules\Report\Services\StateAccountAmounts::rows($this->records);
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), $rows,
                \Modules\Report\Services\StateAccountAmounts::FIELDS);
            $entries = []; $totals = [];
            foreach ($rows as $line) {
                $model = $line['model']; $id = $line['report_key'];
                if ($line['included_in_totals']) foreach (\Modules\Report\Services\StateAccountAmounts::FIELDS as $field) {
                    $currency = $line['currency_type_id'];
                    $totals[$currency][$field] = ($totals[$currency][$field] ?? \App\Support\Exact\Decimal::of(0))
                        ->plus(\App\Support\Exact\Decimal::readRequired($line, $field));
                }
                $units = [];
                foreach ($model->items as $item) {
                    $unit = $item->item->unit_type_id ?? '';
                    if ($unit !== '') $units[$unit] = ($units[$unit] ?? \App\Support\Exact\Decimal::of(0))
                        ->plus(\App\Support\Exact\Decimal::read($item, 'quantity'));
                }
                foreach ($units as $unit=>$amount) $entries[] = ['id'=>$id, 'field'=>'quantity:'.$unit, 'value'=>$amount];
                foreach ([['categories', 'amount'], ['categories_services', 'quantity']] as [$property, $kind]) {
                    foreach ($this->$property as $category) {
                        $amount = \App\Support\Exact\Decimal::of(0);
                        foreach ($model->items as $item) {
                            if (optional($item->relation_item)->category_id != $category->id) continue;
                            if ($kind === 'quantity' && ($item->item->unit_type_id ?? '') !== 'SERV') continue;
                            $amount = $amount->plus(\App\Support\Exact\Decimal::read($item, $kind === 'amount' ? 'total' : 'quantity'));
                        }
                        if (in_array($model->state_type_id, ['09','11'], true)) $amount = \App\Support\Exact\Decimal::of(0);
                        elseif ($line['document_type_id'] === '07') $amount = $amount->negated();
                        $entries[] = ['id'=>$id, 'field'=>'category_'.$kind.':'.$category->id, 'value'=>$amount];
                    }
                }
            }
            foreach ($totals as $currency=>$amounts) foreach ($amounts as $field=>$value)
                $entries[] = ['id'=>'total:'.$currency, 'field'=>$field, 'value'=>$value];
            \App\Support\Exact\FractionSheet::appendEntries($event->sheet->getDelegate(), $entries);
        }];
    }

}
