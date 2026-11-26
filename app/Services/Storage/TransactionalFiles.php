<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

use App\Models\Storage\{StorageObject,StorageOutbox};

/** Versioned catalog references participate in the consumer's tenant transaction. */
final class TransactionalFiles
{
    public function put(string $path,$contents): void { app(TenantStorage::class)->disk()->put($path,$contents); }
    public function read(string $path): string { return app(TenantStorage::class)->disk()->get($path); }
    public function deleteAfterCommit(string $path): void { app(TenantStorage::class)->disk()->delete($path); }

    public function process(int $id,string $uuid): void
    {
        app(StorageLock::class)->run('storage-gc:'.$uuid,fn()=>$this->removeVersion($id,$uuid));
    }

    private function removeVersion(int $id,string $uuid): void
    {
        if(app(TenantStorage::class)->uuid()!==$uuid)throw new \RuntimeException('La empresa de la operación cambió.');
        $row=StorageOutbox::findOrFail($id);
        if($row->status==='completed')return;
        if($row->action!=='delete_version')throw new \RuntimeException('Operación de archivo desconocida.');
        $row->increment('attempts');
        // Physical keys are immutable. Never remove a version that is still referenced.
        if(StorageObject::where('object_key',$row->path)->exists())return;
        try {
            app(StorageLock::class)->assertOwned('storage-gc:'.$uuid);
            app(TenantStorage::class)->physicalForUuid($uuid)->delete($row->path);
            $row->update(['status'=>'completed','diagnostic'=>null]);
        } catch(\Throwable $error) {
            $row->update(['status'=>'pending','diagnostic'=>'Eliminación pendiente de reintento.']);
            throw new \RuntimeException('Eliminación pendiente de reintento.');
        }
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
