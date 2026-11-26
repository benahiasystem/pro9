<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

use App\Models\Storage\{StorageObject,StorageOperation,StorageOperationFile};
use App\Tenancy\Models\Website;
use Illuminate\Support\Facades\{Cache,DB};
use Illuminate\Support\Str;

final class StorageAdministration
{
    public const PACKAGES=['pdf','sale_note','purchase','purchase_order','quotation','income'];

    public function usage(string $uuid, string $package = ''): array
    {
        TenantStorage::validateUuid($uuid);
        if($package!=='' && !in_array($package,self::PACKAGES,true))throw new \InvalidArgumentException('Carpeta no permitida.');
        $key='storage-usage:'.hash('sha256',(string)config('tenant_storage.connection.bucket').':'.$uuid.':'.$package);
        return Cache::remember($key,config('tenant_storage.usage_ttl',300),function()use($uuid,$package){
            $result=['files'=>0,'space'=>0,'measured_at'=>now()->toIso8601String()];
            foreach(app(TenantStorage::class)->physicalForUuid($uuid)->listing($package) as $file){$result['files']++;$result['space']+=$file['size'];}
            return $result;
        });
    }

    public function start(string $uuid,string $action,array $input=[],?string $dedup=null,bool $prepared=false): StorageOperation
    {
        TenantStorage::validateUuid($uuid);
        if(!in_array($action,['clean_packages','clean_pdfs','delete_tenant','backup','backup_database','backup_all','backup_all_database'],true))throw new \InvalidArgumentException('Operación no permitida.');
        if($action==='clean_packages'){
            $packages=array_values(array_unique($input['packages']??[]));
            if(!$packages || array_diff($packages,self::PACKAGES))throw new \InvalidArgumentException('Carpetas no permitidas.');
            $input['packages']=$packages;
        }
        $values=['id'=>(string)Str::uuid(),'tenant_uuid'=>$uuid,'action'=>$action,'status'=>$prepared?'prepared':'pending','input'=>$input];
        if($dedup!==null){
            $values['dedup_key']=hash('sha256',$dedup);
            $operation=StorageOperation::firstOrCreate(['dedup_key'=>$values['dedup_key']],$values);
        }else $operation=StorageOperation::create($values);
        if(!$prepared && $operation->status!=='completed')\App\Jobs\StorageOperationJob::dispatch($operation->id);
        return $operation;
    }

    public function run(string $id): void
    {
        app(StorageLock::class)->run('operation:'.$id,function()use($id){
            $operation=StorageOperation::findOrFail($id);
            if (str_starts_with($operation->action, 'backup')) {
                $this->execute($id);
                return;
            }
            $uuid = $operation->tenant_uuid;
            $locks = app(StorageLock::class);
            $locks->run('storage-gc:'.$uuid, fn() => $locks->run('tenant:'.$uuid, fn() => $this->execute($id)));
        });
    }

    private function execute(string $id): void
    {
        $operation=StorageOperation::findOrFail($id);
        if(in_array($operation->status,['completed','cancelled'],true))return;
        if($operation->action==='candidate')return;
        if($operation->status==='prepared' && Website::where('uuid',$operation->tenant_uuid)->exists())return;
        $uuid=TenantStorage::validateUuid($operation->tenant_uuid);
        if($operation->action==='delete_tenant' && Website::where('uuid',$uuid)->exists())throw new \RuntimeException('La empresa todavía existe; no se autoriza su baja de archivos.');
        $attempt = $operation->attempts + 1;
        $operation->update(['status'=>'running','attempts'=>$attempt,'diagnostic'=>null]);
        $current = fn() => StorageOperation::whereKey($id)->where('status','running')->where('attempts',$attempt);
        try {
            if(in_array($operation->action,['backup','backup_database','backup_all','backup_all_database'],true)){
                $all=str_starts_with($operation->action,'backup_all');
                $files=!str_ends_with($operation->action,'database');
                $result=$all?app(StorageBackup::class)->createAll($operation->id,$files):app(StorageBackup::class)->create($uuid,$operation->id,$files);
            }else{
                $this->manifest($operation);
                $disk=app(TenantStorage::class)->physicalForUuid($uuid);
                foreach(StorageOperationFile::where('operation_id',$id)->where('deleted',false)->lazyById(200) as $file){
                    $delete=function()use($disk,$file){
                        $candidate=$this->candidate($file->path);
                        if($candidate && $candidate->tenant_uuid!==app(TenantStorage::class)->uuid())throw new \RuntimeException('Candidato fuera de la empresa.');
                        if($candidate){
                            $logical=$candidate->input['path'];
                            $row=StorageObject::whereKey($logical)->lockForUpdate()->first();
                            // The publisher acquires this same key lock before writing its version.
                            if($row && $row->object_key===$file->path)app(ObjectCatalog::class)->delete($candidate->tenant_uuid,$row->path);
                            $candidate->update(['status'=>'cleanup_pending']);
                        }else{
                            $row=StorageObject::where('object_key',$file->path)->lockForUpdate()->first();
                            if($row)app(ObjectCatalog::class)->delete(app(TenantStorage::class)->uuid(),$row->path);
                        }
                    };
                    $website=Website::where('uuid',$uuid)->first();
                    if($website){
                        if($operation->action==='delete_tenant')throw new \RuntimeException('La empresa existe; baja de archivos detenida.');
                        app(TenantContext::class)->run($uuid,fn()=>DB::connection('tenant')->transaction($delete));
                    }elseif($operation->action!=='delete_tenant')throw new \RuntimeException('La empresa de la limpieza no está disponible.');
                    // Remove bytes only after the logical deletion and outbox have committed.
                    app(StorageLock::class)->assertOwned('tenant:'.$uuid);
                    app(StorageLock::class)->assertOwned('storage-gc:'.$uuid);
                    $disk->delete($file->path);
                    $candidate=$this->candidate($file->path);
                    if($candidate)$candidate->update(['status'=>'cleaned']);
                    $file->update(['deleted'=>true]);
                }
                $result=['deleted_files'=>StorageOperationFile::where('operation_id',$id)->where('deleted',true)->count(),
                    'freed_space'=>(int)StorageOperationFile::where('operation_id',$id)->where('deleted',true)->sum('size')];
            }
            app(StorageLock::class)->assertOwned('operation:'.$id);
            $system = config('tenancy.db.system-connection-name','system');
            DB::connection($system)->transaction(function () use ($current,$result,$operation) {
                if (!$current()->update(['status'=>'completed','result'=>json_encode($result,JSON_THROW_ON_ERROR),'diagnostic'=>null])) {
                    throw new \RuntimeException('Un nuevo trabajador retomó la operación.');
                }
                if (!empty($operation->input['configuration_id'])) {
                    $due = $operation->input['due_at'];
                    \App\Models\System\StorageCleanupConfiguration::whereKey($operation->input['configuration_id'])
                        ->where(fn($query)=>$query->whereNull('last_run_at')->orWhere('last_run_at','<=',$due))
                        ->update(['last_run_at'=>$due]);
                }
            });
        } catch (\Throwable $error) {
            $failure = ['status'=>'failed','diagnostic'=>'La operación quedó incompleta; puede reintentarse con el mismo identificador.'];
            if (!str_starts_with($operation->action,'backup')) {
                $removed = StorageOperationFile::where('operation_id',$id)->where('deleted',true);
                $failure['result'] = json_encode(array_replace($operation->result ?? [], [
                    'deleted_files'=>$removed->count(),'freed_space'=>(int)$removed->sum('size'),
                ]),JSON_THROW_ON_ERROR);
            }
            $current()->update($failure);
            throw new \RuntimeException('Operación de almacenamiento incompleta.');
        }

        try {
            foreach (array_merge([''],self::PACKAGES) as $package) {
                Cache::forget('storage-usage:'.hash('sha256',(string)config('tenant_storage.connection.bucket').':'.$uuid.':'.$package));
            }
        } catch (\Throwable $ignored) {
            // The measurements expire after five minutes if cache invalidation is unavailable.
        }
    }

    private function manifest(StorageOperation $operation): void
    {
        if(!empty($operation->result['manifest_ready']))return;
        $disk=app(TenantStorage::class)->physicalForUuid($operation->tenant_uuid);
        $directories=$operation->action==='clean_packages'?$operation->input['packages']:($operation->action==='clean_pdfs'?['pdf']:['']);
        foreach($directories as $directory){
            foreach($disk->listing($directory,$operation->action!=='clean_pdfs') as $file){
                if($operation->action==='clean_pdfs' && strtolower(pathinfo($file['path'],PATHINFO_EXTENSION))!=='pdf')continue;
                StorageOperationFile::firstOrCreate(['operation_id'=>$operation->id,'path'=>$file['path']],['size'=>$file['size']]);
            }
        }
        $operation->update(['result'=>['manifest_ready'=>true]]);
    }

    private function candidate(string $physical): ?StorageOperation
    {
        if(!preg_match('/-([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})(?:\.[^\/]*)?$/i',basename($physical),$match))return null;
        $candidate=StorageOperation::whereKey($match[1])->where('action','candidate')->first();
        return $candidate && ($candidate->input['object_key']??null)===$physical?$candidate:null;
    }

    public function recoverCandidate(StorageOperation $candidate): void
    {
        $uuid = $candidate->tenant_uuid;
        $locks = app(StorageLock::class);
        $locks->run('storage-gc:'.$uuid, fn() => $locks->run('tenant:'.$uuid,fn()=>$this->recover($candidate)));
    }

    private function recover(StorageOperation $candidate): void
    {
        $candidate->refresh();
        if($candidate->action!=='candidate' || !in_array($candidate->status,['staged','cleanup_pending'],true) || $candidate->created_at->greaterThan(now()->subDay()))return;
        if(!Website::where('uuid',$candidate->tenant_uuid)->exists()){
            app(StorageLock::class)->run('tenant:'.$candidate->tenant_uuid,function()use($candidate){
                $candidate->update(['status'=>'cleanup_pending']);
                app(TenantStorage::class)->physicalForUuid($candidate->tenant_uuid)->delete($candidate->input['object_key']);
                $candidate->update(['status'=>'cleaned']);
            });
            return;
        }
        app(TenantContext::class)->run($candidate->tenant_uuid,function()use($candidate){
            DB::connection('tenant')->transaction(function()use($candidate){
                $row=StorageObject::whereKey($candidate->input['path'])->lockForUpdate()->first();
                $candidate->refresh();
                if(!in_array($candidate->status,['staged','cleanup_pending'],true))return;
                if($row && $row->version===$candidate->id){$candidate->update(['status'=>'completed']);return;}
                $candidate->update(['status'=>'cleanup_pending']);
                app(TenantStorage::class)->physicalForUuid($candidate->tenant_uuid)->delete($candidate->input['object_key']);
                $candidate->update(['status'=>'cleaned']);
            });
        });
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
