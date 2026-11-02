<?php

namespace App\Services\Payments;

use App\Models\Tenant\PaymentMethodType;
use Illuminate\Database\Connection;

/** A batch of current catalog rows, usable only inside the transaction that locked it. */
final class LockedPaymentMethods
{
    private bool $valid = true;

    private function __construct(private Connection $connection, private int $level, private $methods) {}

    public static function run(Connection $connection, array $ids, callable $operation)
    {
        $level = $connection->transactionLevel();
        if ($level === 0) throw new \LogicException('El catálogo bloqueado requiere una transacción.');
        $model = new PaymentMethodType();
        $model->setConnection($connection->getName());
        $methods = $model->newQuery()->active()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
        $scope = new self($connection, $level, $methods);
        try { return $operation($scope); }
        finally { $scope->valid = false; }
    }

    public function all(Connection $connection)
    {
        if (!$this->valid || $connection !== $this->connection || $connection->transactionLevel() < $this->level) {
            throw new \LogicException('El catálogo bloqueado ya no pertenece a esta transacción.');
        }
        return $this->methods;
    }

    public function find(Connection $connection, $id): PaymentMethodType
    {
        $method = $this->all($connection)->get($id);
        if (!$method) Classification::error('payment_method_type_id', 'La forma de pago seleccionada no está disponible.');
        return $method;
    }
}
