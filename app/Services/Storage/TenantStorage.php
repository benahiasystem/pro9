<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

use App\Tenancy\Environment;
use App\Tenancy\Contracts\Tenant;
use Illuminate\Support\Facades\Storage;

/** Business objects only. Hyn's Directory and tenant overrides stay local. */
final class TenantStorage
{
    public function uuid(): string
    {
        $website = app(Environment::class)->tenant();
        if (!$website) throw new \RuntimeException('No hay una empresa identificada para el archivo.');
        return self::validateUuid((string) $website->uuid);
    }

    public static function validateUuid(string $uuid): string
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]{1,200}\z/D', $uuid) || $uuid === '_system') {
            throw new \InvalidArgumentException('Identificador de empresa inválido.');
        }
        return $uuid;
    }

    public function disk(): TenantDisk { return $this->forUuid($this->uuid()); }
    public function forWebsite(Tenant $website): TenantDisk { return $this->forUuid((string) $website->uuid); }

    /** Only trusted server-side jobs/admin actions may supply a UUID. */
    public function forUuid(string $uuid): TenantDisk
    {
        return new TenantDisk($this->backend(), self::validateUuid($uuid), true);
    }

    public function physicalForUuid(string $uuid): TenantDisk
    {
        return new TenantDisk($this->backend(), self::validateUuid($uuid));
    }

    /** Only directory prefixes at the bucket root; administrative traversal excludes backups. */
    public function tenantPrefixes(): \Generator
    {
        foreach($this->backend()->getDriver()->listContents('',false) as $entry){
            if(!$entry->isDir())continue;
            $prefix=trim($entry->path(),'/');
            if($prefix==='_system')continue;
            try {yield self::validateUuid($prefix);}catch(\InvalidArgumentException $ignored){}
        }
    }

    public function systemMedia(): TenantDisk
    {
        return new TenantDisk($this->backend(), '_system/media');
    }

    public function backups(): TenantDisk
    {
        return new TenantDisk($this->backend(), '_system/backups');
    }

    public function storeExcel($export, string $path): bool
    {
        $path=TenantDisk::relative(ltrim($path,'/'));
        $directory=sys_get_temp_dir().'/sab-excel-'.bin2hex(random_bytes(12));
        if(!mkdir($directory,0700))throw new \RuntimeException('No se pudo preparar el reporte.');
        $name='storage-export-'.bin2hex(random_bytes(8));
        config(['filesystems.disks.'.$name=>['driver'=>'local','root'=>$directory,'throw'=>true]]);
        $stream=null;
        try {
            if(!$export->store(basename($path),$name))throw new \RuntimeException('No se pudo generar el reporte.');
            $stream=fopen($directory.'/'.basename($path),'rb');
            if(!$stream)throw new \RuntimeException('No se pudo leer el reporte.');
            return $this->disk()->put($path,$stream);
        } finally {
            if(is_resource($stream))fclose($stream);
            if(is_file($directory.'/'.basename($path)))unlink($directory.'/'.basename($path));
            rmdir($directory);
            Storage::forgetDisk($name);
            config(['filesystems.disks'=>\Illuminate\Support\Arr::except(config('filesystems.disks',[]),[$name])]);
        }
    }

    private function backend()
    {
        $name = config('tenant_storage.disk', 'supabase-private');
        if ($name !== 'supabase-private') {
            if (app()->isProduction()) throw new \RuntimeException('El almacenamiento de empresa debe ser privado en Supabase.');
            if ($name === 'tenant-storage-local') {
                return Storage::build(['driver' => 'local', 'root' => storage_path('app/tenancy/tenants'), 'throw' => true]);
            }
            return Storage::disk($name);
        }
        $connection = config('tenant_storage.connection', []);
        foreach (['key', 'secret', 'region', 'bucket', 'endpoint'] as $field) {
            if (empty($connection[$field])) throw new \RuntimeException('Falta configurar Supabase Storage en el servidor.');
        }
        $url = parse_url($connection['endpoint']);
        if (($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass'])) {
            throw new \RuntimeException('El endpoint de Supabase Storage debe utilizar HTTPS.');
        }
        return Storage::build($connection);
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
