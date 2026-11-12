<?php

namespace Modules\Restaurant\Observers;

use Modules\Restaurant\Models\PrintOrder;
use Modules\Restaurant\Services\PrintPublisherService;

class PrintOrderObserver
{
    public function __construct(
        private readonly PrintPublisherService $publisher
    ) {}

    /**
     * Publica únicamente órdenes persistidas. Los consumidores Redis no deben
     * imprimir una orden que una transacción aún puede revertir.
     */
    public function created(PrintOrder $order): void
    {
        if ($order->getConnection()->transactionLevel() > 0) {
            $order->getConnection()->afterCommit(function () use ($order) {
                $saved = $order->newQuery()->find($order->id);
                if ($saved) $this->publisher->publish($saved);
            });
            return;
        }
        $this->publisher->publish($order);
    }
}
