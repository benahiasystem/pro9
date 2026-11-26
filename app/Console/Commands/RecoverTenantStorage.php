<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Console\Commands;

use App\Jobs\StorageOperationJob;
use App\Models\Storage\{StorageOperation,StorageOutbox,StorageUpload};
use App\Services\Storage\{StorageAdministration,StorageLock,TenantContext,TenantStorage,TransactionalFiles};
use App\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverTenantStorage extends Command
{
    protected $signature='storage:recover {--tenant= : UUID específico} {--dry-run : Mostrar pendientes sin modificarlos}';
    protected $description='Reconcilia candidatos, cargas vencidas, outbox y operaciones administrativas privadas';

    public function handle()
    {
        $uuid=$this->option('tenant');if($uuid)TenantStorage::validateUuid($uuid);
        $failed=false;
        $operations=StorageOperation::query();if($uuid)$operations->where('tenant_uuid',$uuid);
        foreach($operations->where(function($q){$q->where(function($q){$q->where('status','running')->where('updated_at','<',now()->subMinutes(30));})->orWhereIn('status',['pending','prepared','failed','cleanup_pending'])->orWhere(function($q){$q->where('status','staged')->where('created_at','<',now()->subDay());});})->lazyById(200) as $operation){
            $this->line($operation->id.' '.$operation->action.' '.$operation->status);
            if($this->option('dry-run'))continue;
            try {
                if($operation->action==='candidate')app(StorageAdministration::class)->recoverCandidate($operation);
                elseif($operation->status!=='prepared' || !Website::where('uuid',$operation->tenant_uuid)->exists())StorageOperationJob::dispatch($operation->id);
            }catch(\Throwable $error){$failed=true;$this->error('Pendiente de reintento: '.$operation->id);}
        }
        $websites=Website::query();if($uuid)$websites->where('uuid',$uuid);
        foreach($websites->cursor() as $website){
            try{app(TenantContext::class)->run($website->uuid,function()use($website){
                foreach(StorageUpload::where('expires_at','<',now())->lazyById(200) as $upload){
                    $this->line('Carga vencida: '.$upload->id);
                    if($this->option('dry-run'))continue;
                    app(StorageLock::class)->run('tenant:'.$website->uuid,fn()=>DB::connection('tenant')->transaction(function()use($upload){
                        $row=StorageUpload::whereKey($upload->id)->lockForUpdate()->first();
                        if(!$row || !$row->expires_at->isPast())return;
                        app(TenantStorage::class)->disk()->delete($row->path);$row->delete();
                    }));
                }
                $disk = app(TenantStorage::class)->disk();
                foreach ($disk->listing('item-import-validation') as $report) {
                    if ($report['last_modified'] >= now()->subDay()->timestamp) continue;
                    $this->line('Validación Excel vencida: '.$report['path']);
                    if (!$this->option('dry-run')) $disk->delete($report['path']);
                }
                foreach(StorageOutbox::where('status','pending')->lazyById(200) as $row){
                    $this->line('Outbox: '.$website->uuid.' '.$row->id);
                    if(!$this->option('dry-run'))app(TransactionalFiles::class)->process($row->id,$website->uuid);
                }
            });}catch(\Throwable $error){$failed=true;$this->error('Empresa pendiente de reintento: '.$website->uuid);}
        }
        return $failed?self::FAILURE:self::SUCCESS;
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
