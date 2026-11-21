<?php
namespace App\Console\Commands;

use App\Database\PostgresBackupManager;
use App\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
class BackupDatabase extends \Illuminate\Console\Command
{
    protected $signature = 'bk:bd {type?} {database?} {--quiesced : Corte integral con mantenimiento y trabajos detenidos}';
    protected $description = 'Respaldo PostgreSQL 17.11 con manifiesto, hashes y archivos';
    public function handle(): int
    {
        $type=$this->argument('type') ?: 'todos';
        if (!in_array($type,['individual','todos'],true)) throw new \InvalidArgumentException('Tipo de respaldo inválido.');
        $quiesced = (bool) $this->option('quiesced');
        $databases = $type === 'individual'
            ? [$this->argument('database')]
            : array_merge([config('database.connections.system.database')], Website::pluck('uuid')->all());
        if ($quiesced && $type === 'todos') {
            // The central owner cannot see the state of the runtime connection used to list tenants.
            $system = DB::connection('system');
            if ($system->getPdo()->inTransaction()) throw new \LogicException('Cierre la transacción central antes del corte integral.');
            $system->disconnect();
        }
        $path=storage_path('app/backups/zip/postgresql-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.zip');
        app(PostgresBackupManager::class)->backup($databases, $path, $quiesced);
        $this->info($path);return 0;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
