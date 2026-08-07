<?php

namespace Modules\Report\Jobs;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Traits\JobReportTrait;
use Hyn\Tenancy\Environment;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Report\Traits\MassiveDownloadTrait;

class ProcessReportMassiveDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MassiveDownloadTrait, StorageDocument, JobReportTrait;

    public $tray_id;
    public $website_id;
    public $document_types;
    public $params;

    public $timeout = 1800;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($tray_id, $website_id, $document_types, $params)
    {
        $this->tray_id = $tray_id;
        $this->website_id = $website_id;
        $this->document_types = $document_types;
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        ini_set('memory_limit', '1024M');
        ini_set('pcre.backtrack_limit', '5000000');
        set_time_limit(0);

        Log::debug('ProcessReportMassiveDocuments Start WebsiteId => ' . $this->website_id);

        try {
            $website = $this->findWebsite($this->website_id);
            $tenancy = app(Environment::class);
            $tenancy->tenant($website);

            $tray = $this->findDownloadTray($this->tray_id);
            if (empty($tray)) {
                Log::info('ProcessReportMassiveDocuments: Tray not found. TrayId: ' . $this->tray_id);
                return;
            }

            $this->login($tray->user_id);
            $this->params->user_id = $tray->user_id;

            $document_types = $this->document_types;
            if (empty($document_types)) {
                $document_types = ['all'];
            }

            $data = $this->getData($document_types, $this->params);
            $height = isset($this->params->height) ? $this->params->height : 'a4';
            $view = $this->createPdf($data, $height, (array) $this->params);

            $filename = 'massive_documents_' . date('YmdHis') . '-' . $tray->user_id;
            $path = 'download_tray_pdf';
            Storage::disk('tenant')->makeDirectory($path);
            $this->uploadStorage($filename, $view, $path);
            $this->finishedDownloadTray($tray, $filename, $path);

            Log::debug('ProcessReportMassiveDocuments End WebsiteId => ' . $this->website_id);
        } catch (\Throwable $th) {
            Log::error('ProcessReportMassiveDocuments Error: ', [
                'mensaje' => $th->getMessage(),
                'archivo' => $th->getFile(),
                'linea'   => $th->getLine(),
            ]);

            try {
                $website = $this->findWebsite($this->website_id);
                if ($website) {
                    app(Environment::class)->tenant($website);
                }
                $tray = $this->findDownloadTray($this->tray_id);
                if ($tray) {
                    $tray->date_end = date('Y-m-d H:i:s');
                    $tray->status = 'FAILED';
                    $tray->save();
                }
            } catch (\Throwable $statusError) {
                Log::error('ProcessReportMassiveDocuments: unable to mark tray FAILED', [
                    'mensaje' => $statusError->getMessage(),
                ]);
            }

            throw $th;
        }
    }
}
