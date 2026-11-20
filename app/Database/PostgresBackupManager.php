<?php

namespace App\Database;

use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
final class PostgresBackupManager
{
    public function configuration(string $database): array
    {
        PostgresBootstrap::identifier($database);
        if ($database === config('database.connections.system.database')) return config('database.connections.central_migrator');
        $website = Website::where('uuid', $database)->firstOrFail();
        return app(\Hyn\Tenancy\Database\Connection::class)->generateConfigurationArray($website);
    }
    public function files(string $database): array
    {
        $result = [];
        $roots = ['tenant' => storage_path('app/tenancy/tenants/'.$database)];
        // Uploads are shared physical folders in this version; include them once per package.
        $roots['uploads'] = storage_path('app/public/uploads');
        foreach ($roots as $label => $root) {
            if (!is_dir($root)) continue;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!$file->isFile() || $file->isLink()) continue;
                $relative = substr($file->getPathname(), strlen($root) + 1);
                $entry=$label==='uploads' ? 'shared/uploads/'.$relative : 'files/'.$database.'/tenant/'.$relative;
                $result[$entry]=$file->getPathname();
            }
        }
        return $result;
    }
    public function backup(array $databases, string $destination, bool $quiesced = false): array
    {
        if ($quiesced && !app()->isDownForMaintenance()) throw new \LogicException('El corte integral requiere mantenimiento y workers/scheduler detenidos.');
        $directory = sys_get_temp_dir().'/pro9-pgdump-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $backup = new PostgresBackup(); $dumps = []; $files = [];
        try {
            foreach (array_unique($databases) as $database) {
                $config = $this->configuration($database);
                if ($quiesced) {
                    $pdo = new \PDO('pgsql:host='.$config['host'].';port='.$config['port'].';dbname='.$database, $config['username'], $config['password']);
                    $query = $pdo->query("SELECT count(*) FROM pg_stat_activity WHERE datname=current_database() AND pid<>pg_backend_pid() AND state IS DISTINCT FROM 'idle'");
                    if ($query->fetchColumn() > 0) throw new \LogicException('Existen operaciones activas durante el corte integral.');
                }
                $dumps[$database] = $directory.'/'.$database.'.dump';
                $backup->dump($config, $dumps[$database]);
                $files += $this->files($database);
            }
            if (!is_dir(dirname($destination))) mkdir(dirname($destination), 0700, true);
            return $backup->pack($dumps, $files, $destination, ['application_revision' => config('app.revision', 'unknown'), 'global_coherent' => $quiesced, 'consistency' => $quiesced ? 'quiesced' : 'per-database']);
        } finally {
            foreach ($dumps as $path) if (is_file($path)) unlink($path);
            rmdir($directory);
        }
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
