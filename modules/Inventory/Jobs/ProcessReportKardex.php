<?php

namespace Modules\Inventory\Jobs;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Models\System\JobBatchingTray;
use App\Models\Tenant\DownloadTray;
use App\Traits\JobReportTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Inventory\Http\Controllers\ReportKardexController;

class ProcessReportKardex implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable, JobReportTrait, StorageDocument;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $tray_id;
    public $website_id;
    public $warehouse_id;
    public $request;
    public $user_id;
    public $offset;
    public $limit;
    public $is_batching;

    public function __construct(
        int $tray_id,
        array $request,
        int $website_id,
        $warehouse_id,
        int $user_id,
        int $offset = 0,
        int $limit = 0,
        bool $is_batching = false
    )
    {
        $this->tray_id = $tray_id;
        $this->website_id = $website_id;
        $this->request = $request;
        $this->warehouse_id = $warehouse_id;
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
    public function handle()
    {
        Log::info(" WEBSITE ID $this->website_id");

        $website = Website::find($this->website_id);
        $batchId = $this->batchId;
        $tenancy = app(Environment::class);
        $tenancy->tenant($website);

        $this->login($this->user_id);
        // Buscar en el payload del JobBatchingTray, el balance para realizar la suma total y calcular respecto a ese balance

        
        // El balance acumulado de los lotes previos es el del último JobBatchingTray
        // creado para este job_batch_id. Cada tray guarda el acumulado (previo + el
        // suyo), por lo que se toma el más reciente (latest por id) en vez de sumar
        // todos, lo que duplicaría el conteo.
        $job_batch_latest = JobBatchingTray::query()
                ->where('job_batch_id', $batchId)
                ->latest('id')
                ->first();

        // Acumulado de los lotes previos, guardado en payload->balance del último tray.
        // data_get es null-safe (en el primer lote no hay tray) y funciona con el cast
        // 'object' (payload->balance) o 'array' (payload['balance']).
        $balance = data_get($job_batch_latest, 'payload.balance', 0);

        $tray = DownloadTray::find($this->tray_id);
        $path = $this->getReportPath("pdf");
        $website_id = $this->website_id;
        $tray_id = $this->tray_id;
        $warehouse_id = $this->warehouse_id;

        $request = new Request();
        $request->merge($this->request);
        $data = app(ReportKardexController::class)->getData($request);

        /** @var \Illuminate\Database\Query\Builder */
        $builder = $data['records'];

        $records = $builder->offset($this->offset)->limit($this->limit)->latest('id');

        $data['records'] = $records->get();

        // Suma de movimientos de esta página (ya respeta offset/limit al pasar la
        // colección). Se acumula sobre el balance de los lotes previos.
        $after_balance = app(ReportKardexController::class)->getFirstBalanceKardex($data['records']);


        // El saldo inicial que se muestra en el PDF es el acumulado de lotes previos.
        $data['balance'] = $balance;

        $pdf = Pdf::loadView('inventory::reports.kardex.report_pdf', $data);


        $filename = 'Reporte_Kardex' . date('YmdHis');

        $this->uploadStorage($filename, $pdf->output(), $path);

        if ($this->is_batching) {
            JobBatchingTray::create([
                'job_batch_id' => $batchId,
                'generated_filename' => "$filename.pdf",
                'payload' => [
                    'balance' => $balance + $after_balance
                ]
            ]);
        } else {
            $tray->file_name = $filename;
            $tray->date_end = date('Y-m-d H:i:s');
            $tray->status = 'FINISHED';
            $tray->path = $path;
            $tray->save();
        }

    }
}
