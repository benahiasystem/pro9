<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

// ######## INICIO COMANDO PUENTE PARA TENANTS HISTÓRICOS ########
final class MigrateExistingTenantToVenezuela extends Command
{
    protected $signature = 'tenant:migrate-venezuela
        {tenant : UUID/base de datos de un tenant registrado}
        {--dry-run : Validar el tenant y mostrar las migraciones pendientes sin escribir}';

    protected $description = 'Reconcilia migraciones consolidadas y actualiza un tenant histórico a VE/VES';

    public function handle(): int
    {
        $tenant = (string) $this->argument('tenant');

        if (!preg_match('/^[A-Za-z0-9_]+$/', $tenant)) {
            $this->error('El identificador del tenant contiene caracteres no permitidos.');

            return self::FAILURE;
        }

        $registered = DB::connection('system')->table('websites')->where('uuid', $tenant)->exists();
        if (!$registered) {
            $this->error("El tenant {$tenant} no está registrado en la base del sistema.");

            return self::FAILURE;
        }

        $connectionName = 'tenant_venezuela_upgrade';
        config([
            "database.connections.{$connectionName}" => array_merge(
                config('database.connections.system'),
                ['database' => $tenant]
            ),
        ]);
        DB::purge($connectionName);
        $connection = DB::connection($connectionName);

        try {
            $this->assertLegacyTenantCanBeBaselined($connection);
            $baseline = $this->consolidatedBaseline();
            $pending = $this->pendingMigrations($connection, $baseline);

            if ((bool) $this->option('dry-run')) {
                $this->info("Tenant válido: {$tenant}");
                $this->line('Migraciones consolidadas por reconciliar: '.count($pending));
                $this->line('Se ejecutarán después: 2026_08_17_000329 y 2026_08_17_000330.');

                return self::SUCCESS;
            }

            $batch = max(1, (int) $connection->table('migrations')->max('batch'));
            foreach ($pending as $migration) {
                $connection->table('migrations')->insert([
                    'migration' => $migration,
                    'batch' => $batch,
                ]);
            }

            $originalDefault = DB::getDefaultConnection();
            DB::setDefaultConnection($connectionName);

            try {
                $exitCode = Artisan::call('migrate', [
                    '--database' => $connectionName,
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
                $this->output->write(Artisan::output());
            } finally {
                DB::setDefaultConnection($originalDefault);
            }

            if ($exitCode !== self::SUCCESS) {
                throw new RuntimeException('Artisan migrate terminó con código '.$exitCode.'.');
            }

            $this->info("Tenant {$tenant} actualizado correctamente a Venezuela.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            DB::disconnect($connectionName);
        }
    }

    private function assertLegacyTenantCanBeBaselined($connection): void
    {
        $schema = $connection->getSchemaBuilder();
        foreach (['migrations', 'countries', 'departments', 'provinces', 'districts', 'configurations'] as $table) {
            if (!$schema->hasTable($table)) {
                throw new RuntimeException("No se puede reconciliar: falta la tabla {$table}.");
            }
        }

        $tableCount = $connection->table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $connection->getDatabaseName())
            ->where('TABLE_TYPE', 'BASE TABLE')
            ->count();

        if ($tableCount < 328 || $connection->table('migrations')->count() === 0) {
            throw new RuntimeException('El esquema no corresponde a un tenant histórico completo de Pro9.');
        }
    }

    private function consolidatedBaseline(): array
    {
        $files = glob(database_path('migrations/tenant/2026_08_17_*.php')) ?: [];
        sort($files);

        return array_map(
            static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            array_values(array_filter(
                $files,
                static fn (string $path): bool => preg_match('/_000(?:[0-2][0-9]{2}|3(?:0[0-9]|1[0-9]|2[0-8]))_/', basename($path)) === 1
            ))
        );
    }

    private function pendingMigrations($connection, array $baseline): array
    {
        $completed = $connection->table('migrations')
            ->whereIn('migration', $baseline)
            ->pluck('migration')
            ->all();

        return array_values(array_diff($baseline, $completed));
    }
}
// ######## FIN COMANDO PUENTE PARA TENANTS HISTÓRICOS ########
