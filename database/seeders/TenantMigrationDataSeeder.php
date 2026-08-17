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

        if (!is_file($dataPath)) {
            throw new RuntimeException("No existe el archivo de datos {$dataPath}.");
        }

        $payload = require $dataPath;
        $tables = $payload['tables'] ?? null;

        if (!is_array($tables)) {
            throw new RuntimeException('El archivo de datos iniciales no es válido.');
        }

        $connection = DB::connection();
        $connection->unprepared('SET FOREIGN_KEY_CHECKS=0');

        try {
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
}
// ########### FIN CAMBIO RECONSTRUCCIÓN MIGRACIONES TENANT
