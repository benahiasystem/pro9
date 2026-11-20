<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Report\Services\DocumentReportLines;

class DocumentExport implements FromView, ShouldAutoSize, WithEvents
{
    use Exportable;
    public $records;
    public $company;
    public $establishment;
    public $filters;
    public $categories;
    public $categories_services;
    public $columns;

    public function records($records) {
        \App\Services\Fiscal\FiscalIdentity::preload($records);
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

    public function columns($columns) {
        $this->columns = $columns;

        return $this;
    }

    public function view(): View {
        return view('report::documents.report_excel', [
            'records'=> $this->records,
            'company' => $this->company,
            'establishment'=>$this->establishment,
            'filters'=>$this->filters,
            'categories'=>$this->categories,
            'categories_services'=>$this->categories_services,
            'columns'=>$this->columns,
        ]);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class=>function (AfterSheet $event) {
            $rows=collect($this->records)->map(fn ($row)=>DocumentReportLines::monetaryRow($row));
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(),$rows,DocumentReportLines::MONETARY_FIELDS);
            \App\Support\Exact\FractionSheet::appendEntries($event->sheet->getDelegate(),
                DocumentReportLines::fractionEntries($this->records,$this->categories ?? [],$this->categories_services ?? []));
        }];
    }
}
