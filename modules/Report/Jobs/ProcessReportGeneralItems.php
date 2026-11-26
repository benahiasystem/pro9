<?php

namespace Modules\Report\Jobs;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Models\System\JobBatchingTray;
use App\Traits\JobReportTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Bus\Dispatchable;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Tenancy\Environment;
use Illuminate\Support\Facades\Log;
use Modules\Report\Exports\GeneralItemExport;

class ProcessReportGeneralItems implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StorageDocument, JobReportTrait;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $tray_id;
    public $website_id;
    public $request;
    public $user_id;
    public $offset;
    public $limit;
    public $is_batching;

    public function __construct($tray_id, $website_id,$request, $user_id , $offset, $limit, $is_batching)
    {
        $this->tray_id = $tray_id;
        $this->website_id = $website_id;
        $this->request = $request;
        $this->user_id = $user_id;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->is_batching = $is_batching;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    public function middleware(): array { return [new \App\Jobs\TenantStorageJobMiddleware()]; }
    // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

    public function handle()
    {
        ini_set("pcre.backtrack_limit", "5000000");
        $tenantSelected = false;
        try {
        $batchId = $this->batchId;
        $website = $this->findWebsite($this->website_id);
        if (!$website) throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(\App\Tenancy\Models\Website::class, [$this->website_id]);
        $tenancy = app(Environment::class);
        $tenancy->tenant($website);
        $tenantSelected = true;

        $this->login($this->user_id);

        $document_type_id = $this->request['document_type_id'];
        $request_apply_conversion_to_pen = $this->request['apply_conversion_to_pen'] ?? 'false';
        $type = $this->request['type'];
        $type_prefix = ($type == 'sale') ? 'ventas_' : 'compras_';


        $tray = $this->findDownloadTray($this->tray_id);
        $path = $this->getReportPath($this->request['format']);
        $format = $this->request['format'];
        if (!$tray) throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(\App\Models\Tenant\DownloadTray::class, [$this->tray_id]);
        $filename = $type_prefix . 'report_general_items_'.date('YmdHis').'-'.$tray->user_id.'-'.$tray->id.'-'.$this->offset;

        [$html,$generalItemExport]=\App\Support\Exact\ReadSnapshot::run(\DB::connection('tenant'), function () use ($format,$type,$document_type_id,$request_apply_conversion_to_pen) {
            $controller = new \Modules\Report\Http\Controllers\ReportGeneralItemController();
            $records = $controller->getRecordsItems($this->request)->offset($this->offset)->limit($this->limit)->latest('id')->get();
            if ($format === 'pdf') {
                return [view('report::general_items.report_pdf', compact('records','type','document_type_id','request_apply_conversion_to_pen'))->render(), null];
            }
            if ($format === 'xlsx') {
                $export=(new GeneralItemExport())->records($records)->type($type)->document_type_id($document_type_id)
                    ->request_apply_conversion_to_pen($request_apply_conversion_to_pen)->freeze();
                return [null, $export];
            }
            throw new \InvalidArgumentException('Formato de reporte de artículos no admitido.');
        });

        if ($format == 'pdf') {
            $pdf = PDF::loadHTML($html)->setPaper('a4', 'landscape');
            // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            if (!app(\App\Services\Storage\TenantStorage::class)->disk()->put($path.DIRECTORY_SEPARATOR.$filename.'.pdf', $pdf->output())) {
            // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                throw new \RuntimeException('No se pudo guardar el PDF del reporte de artículos.');
            }

            unset($pdf);
        } else if ($format== 'xlsx') {
            if (!app(\App\Services\Storage\TenantStorage::class)->storeExcel($generalItemExport,DIRECTORY_SEPARATOR.$path.DIRECTORY_SEPARATOR.$filename.'.'.$format)) {
                throw new \RuntimeException('No se pudo guardar el Excel del reporte de artículos.');
            }
            
            unset($generalItemExport);
        }

        if ($this->is_batching) {
            JobBatchingTray::create([
                'job_batch_id' => $batchId,
                'generated_filename' => $filename.'.'.$format
            ]);
        } else {
            $tray->file_name = $filename;
            $tray->date_end = date('Y-m-d H:i:s');
            $tray->status = 'FINISHED';
            $tray->path = $path;
            $tray->save();
        }



        } catch (\Throwable $th) {
            if ($this->batch()) {
                $this->batch()->cancel();
            }
            if ($tenantSelected && !$this->is_batching) {
                try {
                    $tray = $this->findDownloadTray($this->tray_id);
                    if ($tray) {
                        $tray->status = 'FAILED';
                        $tray->date_end = date('Y-m-d H:i:s');
                        $tray->save();
                    }
                } catch (\Throwable $statusError) {
                    Log::error('No se pudo actualizar la bandeja del reporte de artículos', ['exception'=>$statusError]);
                }
            }
            throw $th;
        }
    }
}
