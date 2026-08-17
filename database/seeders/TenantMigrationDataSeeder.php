<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Restaura los datos iniciales que antes eran insertados por migraciones tenant. */
// ########### INICIO CAMBIO RECONSTRUCCIÓN MIGRACIONES TENANT
class TenantMigrationDataSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = database_path('seeders/data/tenant_initial_data.php');
        // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
        $geopoliticalDataPath = database_path('seeders/data/venezuela_geopolitical_data.php');
        // ######## FIN CAMBIO GEOPOLITICO VENEZUELA

        // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
        if (!is_file($dataPath) || !is_file($geopoliticalDataPath)) {
            throw new RuntimeException("No existe el archivo de datos {$dataPath}.");
        }
        // ######## FIN CAMBIO GEOPOLITICO VENEZUELA

        $payload = require $dataPath;
        $tables = $payload['tables'] ?? null;

        if (!is_array($tables)) {
            throw new RuntimeException('El archivo de datos iniciales no es válido.');
        }

        // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
        $geopoliticalPayload = require $geopoliticalDataPath;
        $geopoliticalTables = $geopoliticalPayload['tables'] ?? null;

        if (!is_array($geopoliticalTables)) {
            throw new RuntimeException('El catálogo geopolítico venezolano no es válido.');
        }

        $tables = array_replace($tables, $geopoliticalTables);
        $tables = $this->normalizePayloadLocationReferences($tables);
        // ######## FIN CAMBIO GEOPOLITICO VENEZUELA

        $connection = DB::connection();
        $connection->unprepared('SET FOREIGN_KEY_CHECKS=0');

        try {
            // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
            $this->migrateExistingLocationReferences($connection);

            foreach (['districts', 'provinces', 'departments'] as $locationTable) {
                if ($connection->getSchemaBuilder()->hasTable($locationTable)) {
                    $connection->table($locationTable)->delete();
                }
            }

            if ($connection->getSchemaBuilder()->hasTable('countries')) {
                $connection->table('countries')->where('id', 'PE')->delete();
            }
            // ######## FIN CAMBIO GEOPOLITICO VENEZUELA

            foreach ($tables as $table => $definition) {
                if (!$connection->getSchemaBuilder()->hasTable($table)) {
                    throw new RuntimeException("No existe la tabla requerida por el seeder: {$table}.");
                }

                $rows = $definition['rows'] ?? [];
                $keyColumns = $definition['key_columns'] ?? [];

                if ($rows === []) {
                    continue;
                }

                if ($connection->table($table)->count() === 0) {
                    foreach (array_chunk($rows, 250) as $chunk) {
                        $connection->table($table)->insert($chunk);
                    }

                    continue;
                }

                foreach ($rows as $row) {
                    $identity = array_intersect_key($row, array_fill_keys($keyColumns, true));
                    $connection->table($table)->updateOrInsert($identity ?: $row, $row);
                }
            }
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
    private function normalizePayloadLocationReferences(array $tables): array
    {
        foreach ($tables as $table => &$definition) {
            if (!isset($definition['rows']) || !is_array($definition['rows'])) {
                continue;
            }

            foreach ($definition['rows'] as &$row) {
                if (($row['country_id'] ?? null) !== 'PE') {
                    continue;
                }

                $row['country_id'] = 'VE';

                foreach (config('venezuela.locations.default') as $column => $value) {
                    if (array_key_exists($column, $row)) {
                        $row[$column] = $value;
                    }
                }
            }
            unset($row);
        }
        unset($definition);

        return $tables;
    }

    private function migrateExistingLocationReferences($connection): void
    {
        $schema = $connection->getSchemaBuilder();

        if (!$schema->hasTable('districts')) {
            return;
        }

        $requiresLocationMigration = !$connection->table('districts')
            ->where('id', config('venezuela.locations.default.district_id'))
            ->exists();

        $database = $connection->getDatabaseName();
        $columns = $connection->table('information_schema.COLUMNS')
            ->select(['TABLE_NAME', 'COLUMN_NAME'])
            ->where('TABLE_SCHEMA', $database)
            ->whereIn('COLUMN_NAME', [
                'country_id',
                'nationality_id',
                'department_id',
                'province_id',
                'district_id',
            ])
            ->get()
            ->groupBy('TABLE_NAME');

        foreach ($columns as $table => $tableColumns) {
            if (in_array($table, ['countries', 'departments', 'provinces', 'districts'], true)) {
                continue;
            }

            $names = $tableColumns->pluck('COLUMN_NAME')->all();

            foreach (['country_id', 'nationality_id'] as $countryColumn) {
                if (in_array($countryColumn, $names, true)) {
                    $connection->table($table)
                        ->where($countryColumn, 'PE')
                        ->update([$countryColumn => 'VE']);
                }
            }

            if (!$requiresLocationMigration) {
                continue;
            }

            $updates = [];
            foreach (config('venezuela.locations.default') as $column => $value) {
                if (in_array($column, $names, true)) {
                    $updates[$column] = $value;
                }
            }

            if ($updates !== []) {
                $connection->table($table)
                    ->where(static function ($query) use ($updates): void {
                        foreach (array_keys($updates) as $column) {
                            $query->orWhereNotNull($column);
                        }
                    })
                    ->update($updates);
            }
        }
    }
    // ######## FIN CAMBIO GEOPOLITICO VENEZUELA
}
// ########### FIN CAMBIO RECONSTRUCCIÓN MIGRACIONES TENANT
