<?php

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenant\DownloadTray;
use Hyn\Tenancy\Environment;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Models\Tenant\Company;
use App\Models\Tenant\Establishment;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Modules\Finance\Models\GlobalPayment;
use Modules\Finance\Exports\GlobalPaymentExport;
use Modules\Finance\Http\Controllers\GlobalPaymentController;
use App\Traits\JobReportTrait;


class ProcessGlobalPaymentReport implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StorageDocument, JobReportTrait;

    public $params;
    public $tray_id;
    public $website_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $params, int $tray_id, int $website_id) 
    {
        $this->params = $params;
        $this->tray_id = $tray_id;
        $this->website_id = $website_id;

        // Log::info("tray_id => " . $this->tray_id);
        // Log::info("website_id => " . $this->website_id);
        // Log::info("data => " . json_encode($this->params));
        // Log::info("data 1 => " . $this->params['establishment_id']);

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->showLogInfo("ProcessGlobalPaymentReport Start WebsiteId => {$this->website_id}");

        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $tenantSelected = false;
        try {
            $website = Website::findOrFail($this->website_id);
            app(Environment::class)->tenant($website);
            $tenantSelected = true;
            $download_tray = DownloadTray::findOrFail($this->tray_id);
            $format = $download_tray->format;
            $this->showLogInfo("Init process format: {$format}");

            $path = $this->getReportPath($format);
            $filename = $this->getReportFilename($download_tray->module, 'Reporte_Pagos').'_'.$download_tray->id;

            // data
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            [$records,$company,$establishment]=\App\Support\Exact\ReadSnapshot::run(DB::connection('tenant'),function() {
                $records=app(GlobalPaymentController::class)->getRecords($this->params,GlobalPayment::class)->get();
                \App\Services\Payments\Movement::preload($records);
                return [$records,$this->getDataCompany(),$this->getDataEstablishment($this->params['establishment_id'])];
            });
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########


            // procesar reporte
            if($format === 'pdf')
            {
                // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
                $pdf = PDF::loadView('finance::global_payments.report_pdf', compact('records', 'company', 'establishment'))->setPaper('a4', 'landscape');
                if (!Storage::disk('tenant')->put($path.DIRECTORY_SEPARATOR.$filename.'.pdf', $pdf->output())) {
                    throw new \RuntimeException('No se pudo guardar el reporte de pagos.');
                }
                // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
            }
            else
            {
                $this->showLogInfo('Render excel init');
                $global_payment_export = new GlobalPaymentExport();
                $global_payment_export->records($records)->company($company)->establishment($establishment);
                $this->showLogInfo('Render excel finish');
    
                $this->showLogInfo('Upload excel init');
                if (!$global_payment_export->store(DIRECTORY_SEPARATOR.$path.DIRECTORY_SEPARATOR . $filename.'.'.$format, 'tenant')) {
                    throw new \RuntimeException('No se pudo guardar el reporte de pagos.');
                }
                $this->showLogInfo('Upload excel finish');
            }

            $this->finishedDownloadTray($download_tray, $filename, $path);

        } catch (\Throwable $e) {
            if ($tenantSelected) {
                try {
                    $tray = DownloadTray::find($this->tray_id);
                    if ($tray) {
                        $tray->status = 'FAILED';
                        $tray->date_end = date('Y-m-d H:i:s');
                        $tray->save();
                    }
                } catch (\Throwable $statusError) {
                    Log::error('No se pudo actualizar el estado del reporte de pagos', ['exception' => $statusError]);
                }
            }
            throw $e;
        }
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

        $this->showLogInfo('ProcessGlobalPaymentReport Finish transaction');
    }


    /**
     * The job failed to process.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error($exception->getMessage());
    }


}
