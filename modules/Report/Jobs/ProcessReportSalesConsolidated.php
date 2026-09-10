<?php

namespace Modules\Report\Jobs;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Models\Tenant\Company;
use App\Models\Tenant\Establishment;
use App\Traits\JobReportTrait;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Hyn\Tenancy\Environment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\OrderNoteItem;
use Modules\Report\Exports\GuidesConsolidatedExport;
use Modules\Report\Exports\GuidesConsolidatedTotalExport;
use Modules\Report\Exports\OrderNoteConsolidatedTotalExport;
use Modules\Report\Exports\SaleConsolidatedExport;
use Modules\Report\Exports\SaleConsolidatedTotalExport;
use Modules\Report\Http\Controllers\ReportGuideController;
use Modules\Report\Http\Controllers\ReportOrderNoteConsolidatedController;
use Modules\Report\Http\Controllers\ReportSaleConsolidatedController;

class ProcessReportSalesConsolidated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StorageDocument, JobReportTrait;

    public $tray_id;
    public $website_id;
    public $request;
    public $user_id;
    public $export_mode;
    public $format;
    public $report_source;

    public $timeout = 1800;

    public function __construct(
        $tray_id,
        $website_id,
        $request,
        $user_id,
        $export_mode = 'detail',
        $format = 'pdf',
        $report_source = 'sales'
    ) {
        $this->tray_id = $tray_id;
        $this->website_id = $website_id;
        $this->request = $request;
        $this->user_id = $user_id;
        $this->export_mode = $export_mode;
        $this->format = $format;
        $this->report_source = $report_source;
    }

    public function handle()
    {
        ini_set('memory_limit', '1024M');
        ini_set('pcre.backtrack_limit', '5000000');
        set_time_limit(0);

        try {
            $website = $this->findWebsite($this->website_id);
            $tenancy = app(Environment::class);
            $tenancy->tenant($website);
            $this->login($this->user_id);

            $company = Company::first();
            $establishment = (!empty($this->request['establishment_id']))
                ? Establishment::findOrFail($this->request['establishment_id'])
                : auth()->user()->establishment;
            $params = $this->request;

            $tray = $this->findDownloadTray($this->tray_id);
            $storageFormat = $this->format === 'xlsx' ? 'xlsx' : 'pdf';
            $path = $this->getReportPath($storageFormat);
            $filename = $this->buildFilename($tray->user_id);

            if ($storageFormat === 'xlsx') {
                $export = $this->buildExcelExport($company, $establishment, $params);
                $export->store(DIRECTORY_SEPARATOR.$path.DIRECTORY_SEPARATOR.$filename.'.xlsx', 'tenant');
                unset($export);
            } else {
                $pdf = $this->buildPdf($company, $establishment, $params);
                $this->uploadStorage($filename, $pdf->output(), $path);
                unset($pdf);
            }

            $this->finishedDownloadTray($tray, $filename, $path);
        } catch (\Throwable $th) {
            $tray = $this->findDownloadTray($this->tray_id);
            if ($tray) {
                $tray->date_end = date('Y-m-d H:i:s');
                $tray->status = 'FAILED';
                $tray->save();
            }
            $this->fail($th);
        }
    }

    private function buildFilename($user_id): string
    {
        $prefixes = [
            'sales' => 'Reporte_Consolidado_Items_Ventas',
            'order_notes' => 'Reporte_Consolidado_Items_Pedidos',
            'guides' => 'Reporte_Consolidado_Items_Guias',
        ];

        $prefix = $prefixes[$this->report_source] ?? 'Reporte_Consolidado_Items';

        if (in_array($this->export_mode, ['totals', 'ticket', 'ticket80'], true)) {
            $prefix .= '_Totales';
        }

        return $prefix.'_'.date('YmdHis').'-'.$user_id;
    }

    private function buildPdf($company, $establishment, $params)
    {
        $records = $this->resolveRecords($params);

        switch ($this->report_source) {
            case 'order_notes':
                $view = $this->resolveOrderNotesPdfView();
                break;
            case 'guides':
                $params = $this->normalizeGuidesParams($params);
                $view = $this->resolveGuidesPdfView();
                break;
            default:
                $view = $this->resolveSalesPdfView();
                break;
        }

        $pdf = PDF::loadView($view, compact('records', 'company', 'establishment', 'params'));

        if ($this->export_mode === 'ticket') {
            $height = (5.8 / 2.54) * 72;
            $pdf->setPaper([0, 0, $height, 1440]);
        }

        if ($this->export_mode === 'ticket80') {
            $height = (8 / 2.54) * 72;
            $pdf->setPaper([0, 0, $height, 1440]);
        }

        return $pdf;
    }

    private function buildExcelExport($company, $establishment, $params)
    {
        $records = $this->resolveRecords($params);

        if ($this->report_source === 'guides') {
            $params = $this->normalizeGuidesParams($params);

            if ($this->export_mode === 'totals') {
                return (new GuidesConsolidatedTotalExport())
                    ->records($records)
                    ->company($company)
                    ->establishment($establishment)
                    ->params($params);
            }

            return (new GuidesConsolidatedExport())
                ->records($records)
                ->company($company)
                ->establishment($establishment)
                ->params($params);
        }

        if ($this->report_source === 'order_notes') {
            $export = new OrderNoteConsolidatedTotalExport();

            return $export->setRecords($records)
                ->setCompany($company)
                ->setEstablishment($establishment)
                ->setParams($params);
        }

        if ($this->export_mode === 'totals') {
            return (new SaleConsolidatedTotalExport())
                ->records($records)
                ->company($company)
                ->establishment($establishment)
                ->params($params);
        }

        return (new SaleConsolidatedExport())
            ->records($records)
            ->company($company)
            ->establishment($establishment)
            ->params($params);
    }

    private function resolveRecords($params)
    {
        $request = Request::create('/', 'GET', $params);

        switch ($this->report_source) {
            case 'order_notes':
                $controller = new ReportOrderNoteConsolidatedController();

                if ($this->export_mode === 'totals') {
                    return $controller->totalsByItem($request)->sortBy('item_id');
                }

                return $controller->getRecordsOrderNotes($params, OrderNoteItem::class)
                    ->get()
                    ->sortBy(function ($row) {
                        return $row->order_note->user->name;
                    });

            case 'guides':
                $controller = new ReportGuideController();
                $params = $this->normalizeGuidesParams($params);
                $request = Request::create('/', 'GET', $params);

                if ($this->export_mode === 'totals') {
                    return $controller->totalsByItem($request)->sortBy('item_id');
                }

                return $controller->getRecordsDispachesItem($params)->get();

            default:
                $controller = new ReportSaleConsolidatedController();

                if (in_array($this->export_mode, ['totals', 'ticket', 'ticket80'], true)) {
                    return $controller->totalsByItem($request)->sortBy('item_id');
                }

                return $controller->getRecordsSalesConsolidated($params)->get();
        }
    }

    private function normalizeGuidesParams(array $params): array
    {
        if (isset($params['user_id']) && !empty($params['user_id'])) {
            $params['seller_id'] = (int) $params['user_id'];
        }

        if (isset($params['customer_id']) && !empty($params['customer_id'])) {
            $params['person_id'] = (int) $params['customer_id'];
        }

        return $params;
    }

    private function resolveSalesPdfView(): string
    {
        if (in_array($this->export_mode, ['totals', 'ticket', 'ticket80'], true)) {
            if ($this->export_mode === 'ticket') {
                return 'report::sales_consolidated.report_pdf_totals_ticket';
            }

            if ($this->export_mode === 'ticket80') {
                return 'report::sales_consolidated.report_pdf_totals_ticket_80';
            }

            return 'report::sales_consolidated.report_pdf_totals';
        }

        return 'report::sales_consolidated.report_pdf';
    }

    private function resolveOrderNotesPdfView(): string
    {
        return $this->export_mode === 'totals'
            ? 'report::order_notes_consolidated.report_pdf_totals'
            : 'report::order_notes_consolidated.report_pdf';
    }

    private function resolveGuidesPdfView(): string
    {
        return $this->export_mode === 'totals'
            ? 'report::guides.report_pdf_totals'
            : 'report::guides.report_pdf';
    }
}
