<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant\DownloadTray;
use Hyn\Tenancy\Environment;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Models\Tenant\Company;
use App\Models\Tenant\Establishment;
use Modules\Inventory\Exports\InventoryExport;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Models\Tenant\Cash;
use Modules\Finance\Traits\FinanceTrait;
use Modules\Finance\Models\GlobalPayment;
use Modules\Finance\Exports\MovementExport;


class ProcessMovementsReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StorageDocument, FinanceTrait;

    public $params;
    public $tray_id;
    public $website_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct( Object $params, int $tray_id, int $website_id ) {
        $this->params = $params;
        $this->tray_id = $tray_id;
        $this->website_id = $website_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::debug("ProcessMovementsReport Start WebsiteId => " . $this->website_id);

        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $tenantSelected = false;
        try {
            $website = Website::findOrFail($this->website_id);
            app(Environment::class)->tenant($website);
            $tenantSelected = true;

            $tray = DownloadTray::findOrFail($this->tray_id);

            $qery = $this->getRecords($this->params ,GlobalPayment::class);
            $records = $qery->get();

            // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
            $request = \Illuminate\Http\Request::create('/', 'GET', ['page' => 1]);
            $transformRecords = (new \Modules\Finance\Http\Resources\MovementCollection($records))->toArray($request);
            // ######## FIN MONEDA DEL MEDIO DE COBRO ########

            $company = Company::first();
            $establishment = Establishment::findOrFail($this->params->establishment_id);

            Log::debug("Render excel init");
            $MovementExport = new MovementExport();
            $MovementExport
            ->records($transformRecords)
            ->company($company)
            ->setNewFormat(true)
            ->establishment($establishment);

            Log::debug("Render excel finish");

            Log::debug("Upload excel init");

            $filename = 'FINANCES_Reporte_Movimientos__' . date('YmdHis') . '_' . $tray->id;

            if (!$MovementExport->store(DIRECTORY_SEPARATOR."download_tray_xlsx".DIRECTORY_SEPARATOR . $filename.'.xlsx', 'tenant')) {
                throw new \RuntimeException('No se pudo guardar el reporte de movimientos.');
            }

            $tray->file_name = $filename;
            $path = 'download_tray_xlsx';



            $tray->date_end = date('Y-m-d H:i:s');
            $tray->status = 'FINISHED';
            $tray->path = $path;
            $tray->save();

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
                    Log::error('No se pudo actualizar el estado del reporte de movimientos', ['exception' => $statusError]);
                }
            }
            throw $e;
        }
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

        Log::debug("ProcessMovementsReport Finish transaction");
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


    private function getRecords($request, $model){

        $data_of_period = $this->getDatesOfPeriod((array)$request);
        //$payment_type = $request->payment_type;
        //$destination_type = $request['destination_type'];
        $last_cash_opening = $request->last_cash_opening;

        $params = (object)[
            'date_start' => $data_of_period['d_start'],
            'date_end' => $data_of_period['d_end'],
            'user_id' => $request->user_id
        ];

        $records = GlobalPayment::WhereFilterPaymentType($params);

        if($last_cash_opening == 'true'){

            $cash =  Cash::where([['user_id', $request->user_id],['state',true]])->first();

            if($cash){

                return $records->whereDestinationType(Cash::class)
                                ->where('destination_id', $cash->id)->orderBy('id');
            }
        }
        return $records->orderBy('id');
    }


}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
