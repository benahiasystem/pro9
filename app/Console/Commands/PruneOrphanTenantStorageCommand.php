<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Console\Commands;

use App\Models\Storage\StorageOperation;
use App\Services\Storage\{StorageAdministration,TenantStorage};
use App\Tenancy\Models\Website;
use Illuminate\Console\Command;

class PruneOrphanTenantStorageCommand extends Command
{
    protected $signature='storage:prune-tenants {--dry-run : Solo lista prefijos huérfanos, sin borrar} {--force : No pedir confirmación antes de borrar}';
    protected $description='Elimina objetos privados de tenants sin website; conserva _system/backups';
    public function handle()
    {
        $registered=Website::pluck('uuid');
        if($registered->isEmpty()){$this->error('La tabla websites no devolvió registros. Se aborta para no borrar todo.');return self::FAILURE;}
        $orphans=[];
        foreach(app(TenantStorage::class)->tenantPrefixes() as $uuid){
            if($registered->contains($uuid))continue;
            // Every new business upload registers its owning UUID centrally. Unknown roots need manual investigation.
            if(!StorageOperation::where('tenant_uuid',$uuid)->exists()){$this->warn('Prefijo sin registro de propiedad, omitido: '.$uuid);continue;}
            $orphans[]=$uuid;$this->line($uuid);
        }
        if($this->option('dry-run')){$this->info('[DRY-RUN] Sin eliminaciones.');return self::SUCCESS;}
        if(!$orphans || (!$this->option('force')&&!$this->confirm('¿Eliminar '.count($orphans).' prefijo(s) huérfano(s)?',false)))return self::SUCCESS;
        foreach($orphans as $uuid){
            if(Website::where('uuid',$uuid)->exists()){$this->warn('La empresa existe; omitida: '.$uuid);continue;}
            $operation=app(StorageAdministration::class)->start($uuid,'delete_tenant');
            $this->info('Operación en cola: '.$operation->id);
        }
        return self::SUCCESS;
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
