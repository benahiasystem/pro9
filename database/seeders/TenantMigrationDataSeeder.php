<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// ######## INICIO DATOS INICIALES VENEZUELA ########
/** Carga los catálogos vigentes sin convertir datos de instalaciones anteriores. */
class TenantMigrationDataSeeder extends Seeder
{
    public function run(): void
    {
        $payload = require database_path('seeders/data/tenant_initial_data.php');
        $geography = require database_path('seeders/data/venezuela_geopolitical_data.php');
        $tables = array_replace($payload['tables'], $geography['tables']);
        $connection = DB::connection();
        $connection->unprepared('SET FOREIGN_KEY_CHECKS=0');

        try {
            $connection->transaction(function () use ($connection, $tables) {
                foreach ($tables as $table => $definition) {
                    if (!$connection->getSchemaBuilder()->hasTable($table)) {
                        throw new RuntimeException("No existe la tabla requerida por el seeder: {$table}.");
                    }
                    $rows = $definition['rows'];
                    if ($connection->table($table)->count() === 0) {
                        foreach (array_chunk($rows, 250) as $chunk) {
                            $connection->table($table)->insert($chunk);
                        }
                        continue;
                    }
                    foreach ($rows as $row) {
                        $identity = array_intersect_key($row, array_fill_keys($definition['key_columns'], true));
                        $connection->table($table)->updateOrInsert($identity ?: $row, $row);
                    }
                }
            });
        } finally {
            $connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
// ######## FIN DATOS INICIALES VENEZUELA ########
