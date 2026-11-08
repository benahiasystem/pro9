<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Report\Services\PosCashDocumentsReport;
use App\Support\Exact\FractionSheet;

final class PosCashDocumentsExport implements FromView, ShouldAutoSize, WithEvents
{
    use Exportable;
    public function __construct(private array $report, private $company) {}
    public function view(): View { return view('report::cash.documents_report',['report'=>$this->report,'company'=>$this->company]); }
    public function registerEvents(): array
    {
        return [AfterSheet::class=>function (AfterSheet $event) {
            FractionSheet::append($event->sheet->getDelegate(),$this->report['rows'],PosCashDocumentsReport::FIELDS);
        }];
    }
}
