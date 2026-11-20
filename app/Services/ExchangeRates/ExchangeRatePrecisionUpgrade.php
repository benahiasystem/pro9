<?php
namespace App\Services\ExchangeRates;

use Illuminate\Database\Connection;
use App\Database\SchemaInspector;

// ########## INICIO CAMBIO POSTGRESQL 17
/** New installations already declare the precision; never convert historic tenants. */
final class ExchangeRatePrecisionUpgrade
{
    public function upgrade(Connection $db, bool $dryRun = false): array
    {
        if ($db->getDriverName() !== 'pgsql') throw new \LogicException('La verificación requiere PostgreSQL 17.11.');
        foreach (config('exchange_rate_precision') as $table => $columns) {
            foreach ($columns as $column) {
                $definition = SchemaInspector::column($db, $table, $column);
                if ($definition && $definition->type !== 'numeric(18,8)') throw new \RuntimeException('Precisión incompatible en '.$table.'.'.$column.'; no se convierten bases existentes.');
            }
        }
        return [];
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
