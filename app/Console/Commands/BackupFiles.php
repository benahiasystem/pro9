<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Console\Commands;
use App\Services\Storage\StorageAdministration;
use App\Tenancy\Models\Website;
use Illuminate\Console\Command;
class BackupFiles extends Command
{
    protected $signature='bk:files {type?} {folder?}';
    protected $description='Encola un respaldo privado verificado en Supabase Storage';
    public function handle()
    {
        $type=$this->argument('type')?:'todos';
        if(!in_array($type,['todos','individual'],true)){$this->error('Tipo de respaldo inválido.');return self::FAILURE;}
        $uuid=$type==='individual'?Website::where('uuid',$this->argument('folder'))->firstOrFail()->uuid:'all';
        $action=$type==='individual'?'backup':'backup_all';
        $operation=app(StorageAdministration::class)->start($uuid,$action);
        $this->info('Operación en cola: '.$operation->id);
        return self::SUCCESS;
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
