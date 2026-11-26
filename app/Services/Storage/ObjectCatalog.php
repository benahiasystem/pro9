<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

use App\Models\Storage\{StorageObject,StorageOutbox,StorageOperation};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Logical names stay stable; each physical version is immutable. */
final class ObjectCatalog
{
    private function assertTenant(string $uuid): void
    {
        if (app(TenantStorage::class)->uuid()!==$uuid) throw new \RuntimeException('La empresa del archivo cambió.');
    }

    public function resolve(string $uuid,string $path): ?StorageObject
    {
        $this->assertTenant($uuid);
        return StorageObject::find($path);
    }

    public function put(string $uuid,string $path,$contents,array $options,callable $write): void
    {
        app(StorageLock::class)->run('tenant:'.$uuid,function()use($uuid,$path,$contents,$options,$write){
            if(StorageOperation::where('tenant_uuid',$uuid)->where('action','delete_tenant')->where('status','!=','cancelled')->exists())throw new \RuntimeException('La empresa está en proceso de baja.');
            $this->publish($uuid,$path,$contents,$options,$write);
        },10,DB::connection('tenant'));
    }

    private function publish(string $uuid,string $path,$contents,array $options,callable $write): void
    {
        $this->assertTenant($uuid);
        $version=(string)Str::uuid();
        $info=pathinfo($path);
        $physical=($info['dirname']==='.'?'':$info['dirname'].'/').$info['filename'].'-'.$version.(isset($info['extension'])?'.'.$info['extension']:'');
        if (strlen($physical)>255) throw new \InvalidArgumentException('El nombre del archivo es demasiado largo.');
        // A central candidate survives rollback of the tenant transaction.
        $candidate=StorageOperation::create(['id'=>$version,'tenant_uuid'=>$uuid,'action'=>'candidate','status'=>'staged','input'=>['path'=>$path,'object_key'=>$physical]]);
        $temporary=tempnam(sys_get_temp_dir(),'sab-publish-');
        if ($temporary===false) throw new \RuntimeException('No se pudo preparar el archivo.');
        $output=null;$input=null;
        try {
            chmod($temporary,0600);
            $output=fopen($temporary,'wb');
            if (!$output) throw new \RuntimeException('No se pudo preparar el archivo.');
            if (is_resource($contents)) {
                $meta=stream_get_meta_data($contents);$stat=fstat($contents);$position=ftell($contents);
                $copied=stream_copy_to_stream($contents,$output);
                $expected=($meta['seekable'] ?? false) && $stat!==false && $position!==false ? $stat['size']-$position : null;
                if($copied===false || ($expected!==null && $copied!==$expected))throw new \RuntimeException('La lectura del archivo quedó incompleta.');
            } elseif (fwrite($output,(string)$contents)!==strlen((string)$contents)) throw new \RuntimeException('No se pudo preparar el archivo.');
            fclose($output);$output=null;
            $hash=hash_file('sha256',$temporary);$size=filesize($temporary);$mime=mime_content_type($temporary)?:'application/octet-stream';
            $input=fopen($temporary,'rb');
            DB::connection('tenant')->transaction(function()use($uuid,$path,$physical,$version,$hash,$size,$mime,$write,$input,$options){
                $old=StorageObject::whereKey($path)->lockForUpdate()->first();
                // GC takes this same logical-key lock; a cancelled candidate can never publish later.
                if(StorageOperation::whereKey($version)->value('status')!=='staged')throw new \RuntimeException('La publicación fue cancelada.');
                $write($physical,$input,array_merge($options,['ContentType'=>$mime]));
                app(StorageLock::class)->assertOwned('tenant:'.$uuid);
                StorageObject::updateOrCreate(['path'=>$path],['object_key'=>$physical,'version'=>$version,'sha256'=>$hash,'size'=>$size,'mime'=>$mime]);
                if($old && $old->object_key!==$physical) $this->deferDelete($old->object_key);
                app(TenantUploads::class)->recordTarget($path);
                DB::connection('tenant')->afterCommit(function()use($version){
                    try { StorageOperation::whereKey($version)->update(['status'=>'completed']); }
                    catch(\Throwable $ignored) { \Illuminate\Support\Facades\Log::warning('Candidato privado pendiente de conciliación.',['operation'=>$version]); }
                });
            });
        } finally {
            if(is_resource($input))fclose($input);
            if(is_resource($output))fclose($output);
            if(is_file($temporary))unlink($temporary);
        }
    }

    public function delete(string $uuid,string $path): void
    {
        $this->assertTenant($uuid);
        $connection=DB::connection('tenant');
        app(StorageLock::class)->run('tenant:'.$uuid,fn()=>$connection->transaction(function()use($path){
            $row=StorageObject::whereKey($path)->lockForUpdate()->first();
            if(!$row)return;
            $this->deferDelete($row->object_key);
            $row->delete();
        }),10,$connection);
    }

    private function deferDelete(string $physical): void
    {
        StorageOutbox::create(['operation_key'=>(string)Str::uuid(),'action'=>'delete_version','path'=>$physical]);
    }

    public function listing(string $uuid,string $directory,bool $recursive): \Generator
    {
        $this->assertTenant($uuid);
        $prefix=$directory===''?'':$directory.'/';
        $query=StorageObject::query();
        if($prefix!=='')$query->whereRaw("path LIKE ? ESCAPE '!'",[strtr($prefix,['!'=>'!!','%'=>'!%','_'=>'!_']).'%']);
        foreach($query->lazyById(500,'path') as $row){
            if(!$recursive && str_contains(substr($row->path,strlen($prefix)),'/'))continue;
            yield ['path'=>$row->path,'size'=>(int)$row->size,'last_modified'=>$row->updated_at->timestamp];
        }
    }

    public function paths(string $uuid,string $directory,bool $recursive): array
    {
        $paths=[];
        foreach($this->listing($uuid,$directory,$recursive) as $entry)$paths[]=$entry['path'];
        return $paths;
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
