<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Console\Commands;

use App\Services\Storage\{StorageAdministration,TenantStorage};
use App\Tenancy\Models\Website;
use Illuminate\Console\Command;

class CleanTenantPdfsCommand extends Command
{
    protected $signature='tenants:clean-pdfs {--dry-run : Solo muestra qué se eliminaría, sin borrar} {--tenant= : Limpiar solo un tenant (uuid)}';
    protected $description='Elimina únicamente PDF inmediatos de la carpeta pdf de cada tenant en Storage privado';

    public function handle()
    {
        $query=Website::query();
        if($uuid=$this->option('tenant'))$query->where('uuid',TenantStorage::validateUuid($uuid));
        if(!$query->exists()){$this->error('No se encontraron empresas.');return self::FAILURE;}
        $count=0;
        foreach($query->cursor() as $website){
            if($this->option('dry-run')){
                foreach(app(TenantStorage::class)->physicalForUuid($website->uuid)->listing('pdf',false) as $file){
                    if(strtolower(pathinfo($file['path'],PATHINFO_EXTENSION))==='pdf'){$count++;$this->line($website->uuid.'/'.$file['path']);}
                }
            }else{
                $operation=app(StorageAdministration::class)->start($website->uuid,'clean_pdfs');
                $this->line('Operación en cola: '.$operation->id);
            }
        }
        if($this->option('dry-run'))$this->info('[DRY-RUN] PDF a eliminar: '.$count);
        return self::SUCCESS;
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
