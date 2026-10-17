<?php

namespace App\Support\Exact;

use Illuminate\Database\Eloquent\Model;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
final class Persistence
{
    private static ?\WeakMap $schemas = null;

    public static function columns(Model $model): array
    {
        static $inventory;
        $inventory ??= require dirname(__DIR__, 3).'/config/exact_calculations.php';
        return $inventory[$model->getTable()] ?? [];
    }

    public static function enabled(Model $model): bool
    {
        if (!self::columns($model)) return false;
        $connection = $model->getConnection();
        self::$schemas ??= new \WeakMap();
        $pdo = $connection->getPdo();
        $key = $connection->getName().':'.$connection->getDatabaseName().':'.$model->getTable();
        $schemas = self::$schemas[$pdo] ?? [];
        if (!array_key_exists($key, $schemas)) {
            $schemas[$key] = $connection->getSchemaBuilder()->hasColumn($model->getTable(), 'calculation_snapshot');
            self::$schemas[$pdo] = $schemas;
        }
        return $schemas[$key];
    }

    /** Preserve fractions unless their corresponding source field actually changed. */
    public static function prepare(Model $model): void
    {
        if (!self::enabled($model)) {
            // No upgrade/backfill of existing tenant schemas is performed by application code.
            unset($model->calculation_snapshot, $model->total_payable, $model->settlement_adjustment, $model->quantity_input, $model->price_input);
            return;
        }
        CommercialCalculation::line($model);
        Settlement::prepare($model);
        unset($model->quantity_input, $model->price_input);
        $snapshot = $model->calculation_snapshot ?? Decimal::snapshot([]);
        foreach (self::columns($model) as $field) {
            $attributes = $model->getAttributes();
            if (!array_key_exists($field, $attributes) || $attributes[$field] === null) continue;
            $value = Decimal::input($attributes[$field], $field);
            if (!$model->exists || $model->isDirty($field)) $snapshot['inputs']['fields'][$field] = $value;
            if (!isset($snapshot['values'][$field]) || Decimal::represent($snapshot['values'][$field], 12, true) !== $value) {
                $snapshot['values'][$field] = Decimal::fraction($value);
            }
            $model->setAttribute($field, $value);
        }
        $snapshot['version'] = Decimal::VERSION;
        $model->calculation_snapshot = $snapshot;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
