<?php

namespace App\Tenancy;

use App\Database\PostgresBackup;
use App\Database\PostgresBackupManager;
use App\Database\PostgresBootstrap;
use App\Database\SchemaInspector;
use App\Database\SequenceSynchronizer;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
final class TenantRestore
{
    /** Validate a package and restore separately. Activation requires a stopped installation. */
    public function restore(string $database, string $package, string $source, bool $activate = false): array
    {
        PostgresBootstrap::identifier($database); PostgresBootstrap::identifier($source);
        $backup = new PostgresBackup(); $manifest = $backup->verify($package);
        $entry = 'databases/'.$source.'.dump';
        if (!isset($manifest['files'][$entry])) throw new \InvalidArgumentException('El paquete no contiene la empresa solicitada.');
        if ($activate && !app()->isDownForMaintenance()) throw new \LogicException('La activación requiere mantenimiento y trabajos detenidos.');
        $original = app(PostgresBackupManager::class)->configuration($database);
        $stage = substr($database, 0, 35).'_restore_'.bin2hex(random_bytes(6));
        $config = array_merge($original, ['database' => $stage, 'username' => $stage, 'password' => bin2hex(random_bytes(24))]);
        $temp = sys_get_temp_dir().'/sab-restore-'.bin2hex(random_bytes(8)); mkdir($temp, 0700);
        $service=app(TenantProvisioning::class); $created=false; $activated=false;
        $files=storage_path('app/sab-restores/'.$stage);
        try {
            $backup->extract($package,'databases/',$temp.'/dumps');
            $dump=$temp.'/dumps/'.$source.'.dump';
            $service->create($config, null, function () use ($backup, $config, $dump) {
                $backup->restore($config, $dump);
                config(['database.connections.sab_restore_validation' => $config]); DB::purge('sab_restore_validation');
                try {
                    $db = DB::connection('sab_restore_validation');
                    if (!in_array('companies', SchemaInspector::tables($db), true)) throw new \RuntimeException('El respaldo no contiene una empresa Sab.');
                    if (!$db->table('companies')->exists()) throw new \RuntimeException('La empresa restaurada no está inicializada.');
                    SequenceSynchronizer::sync($db);
                } finally { DB::purge('sab_restore_validation'); }
            }, false); $created=true;
            $backup->extract($package,'files/'.$source.'/',$files);
            $backup->extract($package,'shared/uploads/',$files.'/uploads');
            if (!$activate) return ['database'=>$stage,'activated'=>false,'files'=>$files];
            DB::purge('tenant');
            $control=DB::connection('provisioner');
            // The role is visible to the provisioner for a session even when backend_type is hidden.
            if ($control->selectOne('SELECT count(*) AS n FROM pg_stat_activity WHERE datname=? AND usesysid IS NOT NULL',[$database])->n > 0) throw new \LogicException('Cierre las conexiones HTTP, workers y scheduler antes de activar.');
            $previous = substr($database, 0, 35).'_previous_'.bin2hex(random_bytes(5));
            $oldConfig = array_merge($original, ['database'=>$previous,'username'=>$previous,'password'=>bin2hex(random_bytes(24))]);
            $hadOriginal=(bool)$control->selectOne('SELECT oid FROM pg_database WHERE datname=?',[$database]);
            if (!$hadOriginal && $control->selectOne('SELECT oid FROM pg_roles WHERE rolname=?',[$database])) throw new \LogicException('Existe un rol de destino sin base; revise su propiedad antes de recuperar.');
            $oldFiles=storage_path('app/tenancy/tenants/'.$database);
            $preserved=storage_path('app/tenancy/tenants/'.$previous);
            $oldMoved=false; $newMoved=false; $dbMoved=false; $stageMoved=false; $uploads=[];
            try {
                if ($hadOriginal) { $service->rename($database,$oldConfig,$original['password'],false); $dbMoved=true; }
                $service->rename($stage,$original,$config['password'],false); $stageMoved=true;
                if (is_dir($oldFiles)) { self::move($oldFiles,$preserved); $oldMoved=true; }
                if (is_dir($files.'/tenant')) { self::move($files.'/tenant',$oldFiles); $newMoved=true;self::permissions($oldFiles,true); }
                // Merge the declared shared uploads; preserve every overwritten file for rollback.
                if (is_dir($files.'/uploads')) foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($files.'/uploads',\FilesystemIterator::SKIP_DOTS)) as $file) {
                    if (!$file->isFile()) continue;
                    $relative=substr($file->getPathname(),strlen($files.'/uploads/'));
                    $target=storage_path('app/public/uploads/'.$relative); $saved=$files.'/previous-uploads/'.$relative;
                    if (is_link($target)) throw new \LogicException('No se sobrescriben enlaces simbólicos.');
                    $hadFile=is_file($target); $uploads[]=[$file->getPathname(),$target,$saved,$hadFile];
                    if ($hadFile) self::move($target,$saved);
                    self::move($file->getPathname(),$target);self::permissions($target,false);
                }
                $activated=true;
            } catch (\Throwable $error) {
                foreach (array_reverse($uploads) as [$staged,$target,$saved,$hadFile]) {
                    if (is_file($target) && (!$hadFile || is_file($saved))) self::move($target,$staged);
                    if (is_file($saved)) self::move($saved,$target);
                }
                if ($newMoved) self::move($oldFiles,$files.'/tenant');
                if ($oldMoved) self::move($preserved,$oldFiles);
                if ($stageMoved) $service->rename($database,$config,$original['password'],false);
                if ($dbMoved) $service->rename($previous,$original,$oldConfig['password'],false);
                throw $error;
            }
            foreach (\App\Tenancy\Models\Hostname::whereHas('website', static fn ($q) => $q->where('uuid',$database))->pluck('fqdn') as $fqdn) app('cache')->forget('tenancy.hostname.'.$fqdn);
            app('cache')->forget('tenancy.website.'.$database);
            return ['database'=>$database,'activated'=>true,'previous_database'=>$hadOriginal ? $previous : null,'previous_files'=>$preserved,'shared_files_recovery'=>$files.'/previous-uploads'];
        } catch (\Throwable $error) {
            if ($created && !$activated) $service->delete($stage);
            if (!$activated) self::remove($files);
            throw $error;
        } finally {
            DB::purge('sab_restore_validation'); self::remove($temp);
        }
    }
    private static function move(string $source,string $target): void
    {
        if (!is_dir(dirname($target)) && !mkdir(dirname($target),0700,true)) throw new \RuntimeException('No se pudo preparar el directorio de recuperación.');
        if (!rename($source,$target)) throw new \RuntimeException('No se pudo activar o preservar un archivo.');
    }
    private static function permissions(string $path,bool $private): void
    {
        $root=$private ? storage_path('app/tenancy') : storage_path('app/public/uploads');
        $directory=is_dir($path) ? $path : dirname($path);
        for ($parent=$directory;str_starts_with($parent,$root);$parent=dirname($parent)) {
            chmod($parent,$private ? 0750 : 0755);
            if (function_exists('posix_geteuid') && posix_geteuid()===0) {chown($parent,'www-data');chgrp($parent,'www-data');}
            if ($parent===$root) break;
        }
        $paths=[$path];
        if (is_dir($path)) foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST) as $entry) $paths[]=$entry->getPathname();
        foreach ($paths as $file) {
            if (is_link($file)) throw new \LogicException('No se activan enlaces simbólicos.');
            if (!chmod($file,is_dir($file) ? ($private ? 0750 : 0755) : ($private ? 0600 : 0644))) throw new \RuntimeException('No se pudieron aplicar permisos a los archivos restaurados.');
            if (function_exists('posix_geteuid') && posix_geteuid()===0 && (!chown($file,'www-data') || !chgrp($file,'www-data'))) throw new \RuntimeException('No se pudo asignar el archivo al usuario PHP.');
        }
    }
    private static function remove(string $path): void
    {
        if (!is_dir($path)) return;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname()); else unlink($entry->getPathname());
        }
        rmdir($path);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
