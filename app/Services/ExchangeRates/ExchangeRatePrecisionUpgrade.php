<?php
// ######## INICIO TASAS OCHO DECIMALES ########
namespace App\Services\ExchangeRates;

use Illuminate\Database\Connection;

final class ExchangeRatePrecisionUpgrade
{
    public function upgrade(Connection $db, bool $dryRun = false): array
    {
        $changes = [];
        foreach (config('exchange_rate_precision') as $table => $columns) {
            foreach ($columns as $column) {
                $definition = $db->selectOne('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT
                    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                    [$db->getDatabaseName(), $table, $column]);
                if (!$definition || strtolower($definition->COLUMN_TYPE) === 'decimal(18,8)') continue;
                if (!preg_match('/^(decimal|double)\((\d+),(\d+)\)$/i', $definition->COLUMN_TYPE, $parts)
                    || (int) $parts[2] - (int) $parts[3] > 10 || (int) $parts[3] > 8 || $definition->EXTRA) {
                    throw new \RuntimeException('Definición de tasa no ampliable: '.$table.'.'.$column);
                }
                $sql = 'ALTER TABLE `'.$table.'` MODIFY `'.$column.'` DECIMAL(18,8) '.($definition->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL');
                if ($definition->COLUMN_DEFAULT !== null) {
                    $sql .= ' DEFAULT '.$db->getPdo()->quote((string) $definition->COLUMN_DEFAULT);
                } elseif ($definition->IS_NULLABLE === 'YES') {
                    $sql .= ' DEFAULT NULL';
                }
                if ($definition->COLUMN_COMMENT) $sql .= ' COMMENT '.$db->getPdo()->quote($definition->COLUMN_COMMENT);
                $changes[] = $table.'.'.$column.' '.$definition->COLUMN_TYPE.' → decimal(18,8)';
                if (!$dryRun) $db->statement($sql);
            }
        }
        return $changes;
    }
}
// ######## FIN TASAS OCHO DECIMALES ########
