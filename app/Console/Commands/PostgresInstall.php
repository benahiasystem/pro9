<?php

namespace App\Console\Commands;

use App\Database\PostgresBootstrap;
use App\Database\SchemaInspector;
use App\Database\SequenceSynchronizer;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
class PostgresInstall extends \Illuminate\Console\Command
{
    protected $signature = 'sab:install-postgres {--resume : Continuar una instalación propia incompleta}';
    protected $description = 'Instala SAB en una base central nueva PostgreSQL 17';
    public function handle(): int
    {
        $db = DB::connection('central_migrator');
        $tables = SchemaInspector::tables($db);
        if ($tables && !$this->option('resume')) { $this->error('La instalación requiere una base nueva; --resume sólo admite un estado de SAB.'); return 1; }
        if ($tables) {
            if (!in_array('migrations', $tables, true)) throw new \LogicException('La base no contiene una instalación recuperable.');
            $allowed = array_map(static fn ($p) => pathinfo($p, PATHINFO_FILENAME), array_merge(glob(database_path('migrations/*.php')),glob(base_path('modules/Marketplace/Database/Migrations/*.php'))));
            foreach ($db->table('migrations')->pluck('migration') as $migration) if (!in_array($migration, $allowed, true)) throw new \LogicException('La base contiene migraciones ajenas.');
        }
        PostgresBootstrap::install($db);
        if ($this->call('migrate', ['--database' => 'central_migrator', '--force' => true]) !== 0) return 1;
        if ($this->call('db:seed',['--database'=>'central_migrator','--force'=>true]) !== 0) return 1;
        SequenceSynchronizer::sync($db);
        $this->info('Esquema y semillas centrales PostgreSQL 17 instalados. Las credenciales de operación no cambian.');
        return 0;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
