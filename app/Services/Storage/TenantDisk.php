<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

/** Immutable namespace. Deliberately has no path(): objects are not local files. */
final class TenantDisk
{
    private $backend;
    private string $prefix;
    private bool $catalog;

    public function __construct($backend, string $prefix, bool $catalog = false)
    {
        $this->backend = $backend;
        $this->prefix = $prefix;
        $this->catalog = $catalog;
    }

    public static function relative(string $path, bool $directory = false): string
    {
        if ($path === '' && $directory) return '';
        if ($path === '' || preg_match('/[\\\\\x00-\x1f\x7f]/', $path)
            || $path[0] === '/' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)
            || str_contains($path, '%') || str_contains($path, '?') || str_contains($path, '#')) {
            throw new \InvalidArgumentException('Referencia de archivo inválida.');
        }
        // Convert only the exact physical prefixes used by the old upload helpers.
        foreach (['public/uploads/', 'storage/uploads/'] as $legacy) {
            if (str_starts_with($path, $legacy)) $path = 'uploads/'.substr($path, strlen($legacy));
        }
        $path = $directory ? rtrim($path, '/') : $path;
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') throw new \InvalidArgumentException('Referencia de archivo inválida.');
        }
        return $path;
    }

    private function key(string $path, bool $directory = false): string
    {
        $relative = self::relative($path, $directory);
        return $this->prefix.($relative === '' ? '' : '/'.$relative);
    }

    private function object(string $path)
    {
        return $this->catalog ? app(ObjectCatalog::class)->resolve($this->prefix,self::relative($path)) : null;
    }

    private function provider(callable $operation)
    {
        try {
            return $operation();
        } catch (\League\Flysystem\FilesystemException | \Aws\Exception\AwsException $error) {
            throw $this->providerError($error);
        }
    }

    private function providerError(\Throwable $error): \Symfony\Component\HttpKernel\Exception\HttpException
    {
        do {
            if ($error instanceof \Aws\Exception\AwsException) {
                return match ($error->getStatusCode()) {
                    404 => new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('El archivo no está disponible.'),
                    403 => new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('No se puede acceder al archivo.'),
                    default => new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException(null,'El almacenamiento no está disponible temporalmente.'),
                };
            }
        } while ($error = $error->getPrevious());

        return new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException(null,'El almacenamiento no está disponible temporalmente.');
    }

    private function resolvedKey(string $path): string
    {
        if (!$this->catalog) return $this->key($path);
        $row=$this->object($path);
        if (!$row) throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('El archivo no está disponible.');
        return $this->key($row->object_key);
    }

    public function exists($path): bool
    {
        $object = $this->object($path);
        if ($this->catalog && !$object) return false;
        $key = $this->key($object?->object_key ?? $path);
        return $this->provider(fn()=>$this->backend->exists($key));
    }
    public function missing($path): bool { return !$this->exists($path); }
    public function get($path): string { return $this->provider(fn()=>$this->backend->get($this->resolvedKey($path))); }
    public function readStream($path) { return $this->provider(fn()=>$this->backend->readStream($this->resolvedKey($path))); }
    public function size($path): int { return $this->catalog ? $this->object($path)->size : $this->backend->size($this->key($path)); }
    public function mimeType($path): string { return $this->catalog ? $this->object($path)->mime : $this->backend->mimeType($this->key($path)); }
    public function lastModified($path): int { return $this->catalog ? $this->object($path)->updated_at->timestamp : $this->backend->lastModified($this->key($path)); }

    public function put($path, $contents, $options = []): bool
    {
        $options = is_array($options) ? $options : [];
        unset($options['visibility'], $options['ACL']);
        $options['CacheControl'] = 'private, no-store';
        $write=function($physical,$bytes,$settings){
            $key = $this->key($physical);
            $size = $this->catalog ? fstat($bytes)['size'] : null;
            if (!$this->provider(fn()=>$this->backend->put($key,$bytes,$settings))) {
                throw new \RuntimeException('No se pudo guardar el archivo privado.');
            }
            if ($size !== null && $this->provider(fn()=>$this->backend->size($key)) !== $size) {
                throw new \RuntimeException('El tamaño guardado no coincide con el archivo preparado.');
            }
        };
        if ($this->catalog) app(ObjectCatalog::class)->put($this->prefix,self::relative($path),$contents,$options,$write);
        else $write(self::relative($path),$contents,$options);
        return true;
    }

    public function writeStream($path, $resource, array $options = []): bool { return $this->put($path, $resource, $options); }

    public function copy($from, $to): bool
    {
        if ($this->key($from) === $this->key($to)) return true;
        // Flysystem's S3 copy may request GetObjectAcl, which Supabase does not support.
        $stream = $this->readStream($from);
        if (!is_resource($stream)) throw new \RuntimeException('No se pudo leer el archivo privado.');
        try { return $this->put($to, $stream); }
        finally { fclose($stream); }
    }

    public function delete($paths): bool
    {
        if ($this->catalog) {
            foreach ((array)$paths as $path) app(ObjectCatalog::class)->delete($this->prefix,self::relative($path));
            return true;
        }
        $keys = array_map(fn ($path) => $this->key($path), (array) $paths);
        if (!$this->provider(fn()=>$this->backend->delete($keys))) throw new \RuntimeException('No se pudo eliminar el archivo privado.');
        return true;
    }

    public function move($from, $to): bool
    {
        if ($this->key($from) === $this->key($to)) return true;
        $this->copy($from, $to);
        return $this->delete($from);
    }
    public function makeDirectory($directory): bool { $this->key($directory, true); return true; }

    public function files($directory = '', $recursive = false): array
    {
        $directory=self::relative($directory ?? '',true);
        if($this->catalog) return app(ObjectCatalog::class)->paths($this->prefix,$directory,$recursive);
        $prefix = $this->prefix.'/';
        $result = [];
        foreach ($this->backend->files($this->key($directory ?? '', true), $recursive) as $key) {
            if (!str_starts_with($key, $prefix)) throw new \RuntimeException('Respuesta de almacenamiento fuera de la empresa.');
            $result[] = substr($key, strlen($prefix));
        }
        return $result;
    }

    public function listing($directory = '',bool $recursive = true): \Generator
    {
        $directory=self::relative($directory,true);
        if($this->catalog){yield from app(ObjectCatalog::class)->listing($this->prefix,$directory,$recursive);return;}
        $prefix=$this->prefix.'/';
        try {
        foreach($this->backend->getDriver()->listContents($this->key($directory,true),$recursive) as $entry){
            if(!$entry->isFile())continue;
            $key=$entry->path();
            if(!str_starts_with($key,$prefix))throw new \RuntimeException('Listado fuera de la empresa.');
            yield ['path'=>substr($key,strlen($prefix)),'size'=>(int)$entry->fileSize(),'last_modified'=>$entry->lastModified()];
        }
        } catch (\League\Flysystem\FilesystemException | \Aws\Exception\AwsException $error) {
            throw $this->providerError($error);
        }
    }

    public function allFiles($directory = ''): array { return $this->files($directory, true); }
    public function deleteDirectory($directory): bool
    {
        foreach ($this->allFiles($directory) as $path) $this->delete($path);
        return true;
    }

    public function temporaryUrl($path, $expiration = null, array $options = []): string
    {
        $now = \Carbon\CarbonImmutable::now();
        $ttl = \Illuminate\Container\Container::getInstance()->bound('config') ? (int) config('tenant_storage.signed_url_ttl', 300) : 300;
        $limit = $now->addSeconds(max(1, min(3600, $ttl)));
        $expiration = $expiration ?? $limit;
        if ($expiration <= $now || $expiration > $limit) throw new \InvalidArgumentException('Vencimiento del enlace inválido.');
        return $this->provider(fn()=>$this->backend->temporaryUrl($this->resolvedKey($path), $expiration, $options));
    }

    /** Resolve a list of authorized catalog paths without HEAD requests or per-image SQL. */
    public function temporaryUrls(array $paths): array
    {
        if (!$this->catalog || app(TenantStorage::class)->uuid() !== $this->prefix) {
            throw new \RuntimeException('No se pueden firmar archivos fuera de la empresa activa.');
        }
        $urls = [];
        $expires = \Carbon\CarbonImmutable::now()->addSeconds(max(1,min(3600,(int)config('tenant_storage.signed_url_ttl',300))));
        foreach (array_chunk(array_unique(array_map(fn($path)=>self::relative($path),$paths)),300) as $chunk) {
            $objects = \App\Models\Storage\StorageObject::whereIn('path',$chunk)->get(['path','object_key']);
            foreach ($objects as $object) {
                $key = $this->key($object->object_key);
                $urls[$object->path] = $this->provider(fn()=>$this->backend->temporaryUrl($key,$expires));
            }
        }
        return ['urls'=>$urls,'expires_at'=>$expires->toIso8601String()];
    }

    public function url($path): string { return $this->temporaryUrl($path); }

    public function download($path, $name = null, array $headers = [], $disposition = 'attachment')
    {
        $headers = array_merge($headers, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        $snapshot=$this->object($path);
        $key=$this->catalog ? ($snapshot ? $this->key($snapshot->object_key) : null) : $this->key($path);
        if($key===null)abort(404,'El archivo no está disponible.');
        $stream=$this->provider(fn()=>$this->backend->readStream($key));
        if(!is_resource($stream)) throw new \RuntimeException('No se pudo abrir el archivo privado.');
        try {
            $headers += ['Content-Type'=>$snapshot ? $snapshot->mime : $this->backend->mimeType($key),'Content-Length'=>$snapshot ? $snapshot->size : $this->backend->size($key)];
            return response()->streamDownload(function()use($stream){
                try { if(fpassthru($stream)===false) throw new \RuntimeException('Descarga interrumpida.'); }
                finally { if(is_resource($stream))fclose($stream); }
            },$name ?? basename($path),$headers,$disposition);
        } catch(\Throwable $error) { fclose($stream);throw $error; }
    }

    public function putFileAs($directory, $file, $name, $options = []): string
    {
        $path = self::relative(trim($directory, '/').'/'.$name);
        $stream = fopen($file->getRealPath(), 'rb');
        if (!$stream) throw new \RuntimeException('No se pudo leer la carga.');
        try { $this->put($path, $stream, $options); }
        finally { fclose($stream); }
        return $path;
    }

    /** A local file exists only for this callback, including when it throws. */
    public function withTemporaryFile(string $path, callable $callback)
    {
        $temporary = tempnam(sys_get_temp_dir(), 'sab-object-');
        if ($temporary === false) throw new \RuntimeException('No se pudo preparar el archivo temporal.');
        $input = null; $output = null;
        try {
            chmod($temporary, 0600);
            $input = $this->readStream($path);
            $output = fopen($temporary, 'wb');
            if (!is_resource($input) || !$output || stream_copy_to_stream($input, $output) === false) throw new \RuntimeException('No se pudo leer el archivo privado.');
            fclose($output); $output = null;
            return $callback($temporary);
        } finally {
            if (is_resource($input)) fclose($input);
            if (is_resource($output)) fclose($output);
            if (is_file($temporary)) unlink($temporary);
        }
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
