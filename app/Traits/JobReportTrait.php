<?php

namespace App\Traits;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Models\System\JobBatchingTray;
use Illuminate\Database\Eloquent\Builder;
use App\Tenancy\Models\Website;
use App\Tenancy\Environment;
use App\Models\Tenant\{
    User,
    DownloadTray,
    Company,
    Establishment,
};
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

trait JobReportTrait
{
    use StorageDocument;
    
                
    /**
     * 
     * Obtener registro asociado al tenant desde la tabla website de system
     * 
     * @return Website
     */
    public function getTenantWebsite()
    {
        return app(Environment::class)->tenant();
    }


    /**
     * 
     * Buscar website
     * 
     * @return Website
     */
    public function findWebsite($website_id)
    {
        return Website::find($website_id);
    }

        
    /**
     * 
     * Retorna usuario autenticado
     *
     * @return User
     */
    public function getCurrentUser()
    {
        return auth()->user();
    }
    

    /**
     *
     * @param  string $message
     * @return void
     */
    public function showLogInfo($message)
    {
        Log::info($message);
    }
    
    
    /**
     * 
     * Registrar reporte en cola
     *
     * @param  int $user_id
     * @param  string $module
     * @param  string $format
     * @param  string $type
     * @return DownloadTray
     */
    public function createDownloadTray($user_id, $module, $format, $type)
    {
        return DownloadTray::create([
            'user_id' => $user_id,
            'module' => $module,
            'format' => $format,
            'date_init' => date('Y-m-d H:i:s'),
            'type' => $type
        ]);
    }

    
    /**
     * 
     * Actualizar registro cuando culmina el proceso del reporte
     *
     * @param  DownloadTray $download_tray
     * @param  string $filename
     * @param  string $path
     * @return void
     */
    public function finishedDownloadTray(DownloadTray &$download_tray, $filename, $path)
    {
        $download_tray->file_name = $filename;
        $download_tray->date_end = date('Y-m-d H:i:s');
        $download_tray->status = 'FINISHED';
        $download_tray->path = $path;
        $download_tray->save();
    }

    
    /**
     * 
     * Buscar DownloadTray
     * 
     * @return DownloadTray
     */
    public function findDownloadTray($id)
    {
        return DownloadTray::find($id);
    }

        
    /**
     * @return Company
     */
    public function getDataCompany()
    {
        return Company::active();
    }

    
    /**
     *
     * @param  int $establishment_id
     * @return Establishment
     */
    public function getDataEstablishment($establishment_id)
    {
        return Establishment::findOrFail($establishment_id);
    }

    

    
    /**
     *
     * @param  string $format
     * @return string
     */
    public function getReportPath($format)
    {
        return "download_tray_{$format}";
    }

    
    /**
     *
     * @param  string $module
     * @param  string $title
     * @return string
     */
    public function getReportFilename($module, $title)
    {
        return $module."_{$title}_". date('YmdHis');
    }


    /**
     * 
     * Parámetros
     *
     * @param  array $request
     * @param  array $additional_params
     * @return array
     */
    public function getNewParams($request, $additional_params)
    {
        return array_merge($request, $additional_params);
    }

        
    /**
     * 
     * Mensaje
     *
     * @return array
     */
    public function getJobResponse($success = null, $message = null)
    {
        return [
            'success' => $success ?? true,
            'message' => $message ?? 'El reporte se esta procesando, puede ver el proceso en la bandeja de descargas.'
        ];
    }

    public function login($user_id)
    {
        Auth::loginUsingId($user_id);
    }

    public function logout()
    {
        Auth::logout();
    }

    public function jobBatchFinished(Batch $batch, int $trayId, int $website_id, $filename, $format)
    {
        $website = $this->findWebsite($website_id);
        $tenancy = app(Environment::class);
        $tenancy->tenant($website);
        // $type = $request['type'];
        // $type_prefix = ($type == 'sale') ? 'ventas_' : 'compras_';
        $path = $this->getReportPath('zip');
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $disk = app(\App\Services\Storage\TenantStorage::class)->disk();
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $tray = $this->findDownloadTray($trayId);


        // $filename = $type_prefix . 'report_general_items_'.date('YmdHis').'-' .$tray->user_id;

        $files = JobBatchingTray::where('job_batch_id', $batch->id);

        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $temporary=tempnam(sys_get_temp_dir(),'sab-report-');
        if($temporary===false)throw new \RuntimeException('No se pudo preparar el ZIP.');
        $localFiles=[];$zip=new ZipArchive();$stream=null;
        try {
            if($zip->open($temporary,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('No se pudo abrir el ZIP.');
            $entryNames=[];
            foreach($files->get() as $file){
                $source=$this->getReportPath($format).'/'.$file->generated_filename;
                $local=tempnam(sys_get_temp_dir(),'sab-entry-');
                if($local===false)throw new \RuntimeException('No se pudo preparar una entrada del ZIP.');
                $localFiles[]=$local;
                $input=$disk->readStream($source);$output=fopen($local,'wb');
                try {
                    if(!is_resource($input)||!$output||stream_copy_to_stream($input,$output)===false)throw new \RuntimeException('No se pudo leer una entrada del ZIP.');
                } finally {if(is_resource($input))fclose($input);if(is_resource($output))fclose($output);}
                $entry=\App\Services\Fiscal\DocumentFileName::archiveEntry($file->generated_filename,$entryNames);
                if(!$zip->addFile($local,$entry))throw new \RuntimeException('No se pudo agregar una entrada al ZIP.');
            }
            if(!$zip->close())throw new \RuntimeException('No se pudo cerrar el ZIP.');
            $stream=fopen($temporary,'rb');
            if(!$stream)throw new \RuntimeException('No se pudo leer el ZIP.');
            $disk->put($path.'/'.$filename.'.zip',$stream);
        } finally {
            if(is_resource($stream))fclose($stream);
            foreach($localFiles as $local)if(is_file($local))unlink($local);
            if(is_file($temporary))unlink($temporary);
        }
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

        $tray->file_name = $filename;
        $tray->date_end = date('Y-m-d H:i:s');
        $tray->status = 'FINISHED';
        $tray->format = 'zip';
        $tray->path = $path;
        $tray->save();
        $files->delete();

    }

    public function jobBatchFailed($batch_id, $tray_id)
    {
        DB::table('job_batches')->where('id', $batch_id)->delete();

        // Borrar los jobs pendientes del batch (si usas base de datos)
        DB::table('jobs')->where('payload', 'like', "%{$batch_id}%")->delete();

        // Borrar los jobs fallidos asociados
        DB::table('failed_jobs')->where('payload', 'like', "%{$batch_id}%")->delete();

        $tray = $this->findDownloadTray($tray_id);
        $tray->date_end = date('Y-m-d H:i:s');
        $tray->status = 'FAILED';
        $tray->save();

    }
}
