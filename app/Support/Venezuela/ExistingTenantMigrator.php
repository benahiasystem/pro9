<?php

namespace App\Support\Venezuela;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

// ######## INICIO MIGRACIÓN INCREMENTAL DE TENANT VENEZUELA ########
final class ExistingTenantMigrator
{
    private const DEFAULT_DEPARTMENT = '14';

    private const DEFAULT_PROVINCE = '0229';

    private const DEFAULT_DISTRICT = '000619';

    public function migrate(ConnectionInterface $connection): void
    {
        $this->assertRequiredTables($connection);

        $payload = require database_path('seeders/data/venezuela_geopolitical_data.php');
        $tables = $payload['tables'] ?? [];

        foreach (['countries', 'departments', 'provinces', 'districts'] as $table) {
            if (!isset($tables[$table]['rows']) || !is_array($tables[$table]['rows'])) {
                throw new RuntimeException("Faltan los datos geopolíticos requeridos para {$table}.");
            }
        }

        $database = $connection->getDatabaseName();
        $columns = $connection->table('information_schema.COLUMNS')
            ->select(['TABLE_NAME', 'COLUMN_NAME', 'DATA_TYPE'])
            ->where('TABLE_SCHEMA', $database)
            ->get();

        $connection->unprepared('SET FOREIGN_KEY_CHECKS=0');
        $connection->beginTransaction();

        try {
            $connection->table('countries')->updateOrInsert(
                ['id' => 'VE'],
                ['description' => 'VENEZUELA', 'active' => true]
            );

            $this->replaceValue($connection, $columns, 'country_id', 'PE', 'VE', ['countries']);
            $this->replaceValue($connection, $columns, 'nationality_id', 'PE', 'VE');
            $this->replaceNonNull($connection, $columns, 'department_id', self::DEFAULT_DEPARTMENT, ['provinces']);
            $this->replaceNonNull($connection, $columns, 'province_id', self::DEFAULT_PROVINCE, ['districts']);
            $this->replaceNonNull($connection, $columns, 'district_id', self::DEFAULT_DISTRICT);
            $this->replaceLocations($connection, $columns);
            $this->replaceNonNull($connection, $columns, 'ubigeo', self::DEFAULT_DISTRICT);
            $this->replaceNonNull($connection, $columns, 'consigned_ubigeo', self::DEFAULT_DISTRICT);

            $connection->table('districts')->delete();
            $connection->table('provinces')->delete();
            $connection->table('departments')->delete();
            $connection->table('countries')->where('id', 'PE')->delete();

            foreach (['countries', 'departments', 'provinces', 'districts'] as $table) {
                foreach (array_chunk($tables[$table]['rows'], 250) as $rows) {
                    foreach ($rows as $row) {
                        $keys = array_intersect_key(
                            $row,
                            array_fill_keys($tables[$table]['key_columns'], true)
                        );
                        $connection->table($table)->updateOrInsert($keys, $row);
                    }
                }
            }

            $this->migrateIdentityDocuments($connection);
            $this->assertFinalState($connection);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        } finally {
            $connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
        }

        $connection->statement(
            "ALTER TABLE origin_addresses MODIFY country_id CHAR(2) NOT NULL DEFAULT 'VE'"
        );

        foreach (['locations', 'locations:PE', 'locations:VE', "locations:v2:{$database}:PE", "locations:v2:{$database}:VE"] as $key) {
            Cache::forget($key);
        }
    }

    private function assertRequiredTables(ConnectionInterface $connection): void
    {
        $schema = $connection->getSchemaBuilder();

        foreach (['countries', 'departments', 'provinces', 'districts', 'cat_identity_document_types', 'origin_addresses'] as $table) {
            if (!$schema->hasTable($table)) {
                throw new RuntimeException("El tenant no contiene la tabla requerida {$table}.");
            }
        }
    }

    private function replaceValue(
        ConnectionInterface $connection,
        $columns,
        string $column,
        string $oldValue,
        string $newValue,
        array $excludedTables = []
    ): void {
        $columns->where('COLUMN_NAME', $column)
            ->reject(static fn ($item): bool => in_array($item->TABLE_NAME, $excludedTables, true))
            ->each(static function ($item) use ($connection, $column, $oldValue, $newValue): void {
                $connection->table($item->TABLE_NAME)
                    ->where($column, $oldValue)
                    ->update([$column => $newValue]);
            });
    }

    private function replaceNonNull(
        ConnectionInterface $connection,
        $columns,
        string $column,
        string $newValue,
        array $excludedTables = []
    ): void {
        $columns->where('COLUMN_NAME', $column)
            ->reject(static fn ($item): bool => in_array($item->TABLE_NAME, $excludedTables, true))
            ->each(static function ($item) use ($connection, $column, $newValue): void {
                $connection->table($item->TABLE_NAME)
                    ->whereNotNull($column)
                    ->update([$column => $newValue]);
            });
    }

    private function replaceLocations(ConnectionInterface $connection, $columns): void
    {
        $columns->where('COLUMN_NAME', 'location_id')
            ->each(static function ($item) use ($connection): void {
                $value = $item->DATA_TYPE === 'json'
                    ? json_encode([self::DEFAULT_DEPARTMENT, self::DEFAULT_PROVINCE, self::DEFAULT_DISTRICT])
                    : self::DEFAULT_DISTRICT;

                $connection->table($item->TABLE_NAME)
                    ->whereNotNull('location_id')
                    ->update(['location_id' => $value]);
            });
    }

    private function migrateIdentityDocuments(ConnectionInterface $connection): void
    {
        $documents = [
            '1' => 'Cédula de Identidad (V)',
            '4' => 'Extranjero',
            '6' => 'RIF (V/E/J/G/P)',
        ];

        foreach ($documents as $id => $description) {
            $connection->table('cat_identity_document_types')
                ->whereRaw('BINARY `id` = ?', [(string) $id])
                ->update(['active' => true, 'description' => $description]);
        }
    }

    private function assertFinalState(ConnectionInterface $connection): void
    {
        if (
            $connection->table('departments')->count() !== 25
            || $connection->table('provinces')->count() !== 335
            || $connection->table('districts')->count() !== 1138
        ) {
            throw new RuntimeException('El catálogo debe contener 25 estados, 335 municipios y 1138 parroquias.');
        }

        $defaultExists = $connection->table('districts')
            ->join('provinces', 'districts.province_id', '=', 'provinces.id')
            ->join('departments', 'provinces.department_id', '=', 'departments.id')
            ->where('departments.id', self::DEFAULT_DEPARTMENT)
            ->where('departments.description', 'Miranda')
            ->where('provinces.id', self::DEFAULT_PROVINCE)
            ->where('provinces.description', 'Chacao')
            ->where('districts.id', self::DEFAULT_DISTRICT)
            ->where('districts.description', 'Chacao')
            ->exists();

        if (!$defaultExists || $connection->table('countries')->where('id', 'PE')->exists()) {
            throw new RuntimeException('El catálogo territorial venezolano no quedó íntegro.');
        }
    }
}
// ######## FIN MIGRACIÓN INCREMENTAL DE TENANT VENEZUELA ########
