<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ReportHotelExport implements  FromView, ShouldAutoSize, WithEvents
{
    // Explicit properties preserve the public export contract on PHP 8.2.
    public $company;
    public $records;
    public $rooms;

    use Exportable;

    public function records($records) {
        $this->records = $records;

        return $this;
    }

    public function company($company) {
        $this->company = $company;

        return $this;
    }

    public function rooms($rooms) {
        $this->rooms = $rooms;

        return $this;
    }


    public function view(): View {
        $report = \Modules\Report\Services\HotelOccupancyReport::prepare($this->records, $this->rooms);
        return view('report::report_hotels.report_excel', [
            'company' => $this->company,
            'report' => $report,
        ]);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $report = \Modules\Report\Services\HotelOccupancyReport::prepare($this->records, $this->rooms);
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(), [], []);
            $entries = [];
            foreach ($report['stays'] as $stay) if ($stay['rate_exact'] !== null)
                $entries[] = ['id'=>'hotel_rent:'.$stay['id'], 'field'=>'room_unit_price', 'value'=>$stay['rate_exact']];
            \App\Support\Exact\FractionSheet::appendEntries($event->sheet->getDelegate(), $entries);
        }];
    }
}
