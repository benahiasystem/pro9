<?php

namespace Modules\Dashboard\Widgets;

use Carbon\Carbon;
use Modules\Dashboard\Helpers\DashboardData;
use Modules\Dashboard\Helpers\DashboardKpi;
use Modules\Dashboard\Helpers\DashboardSalePurchase;
use Modules\Dashboard\Helpers\DashboardUtility;

/**
 * Contexto compartido por todas las fuentes durante una petición batch.
 * Normaliza los filtros globales (sucursal/periodo) y memoriza las llamadas
 * a los helpers existentes, de modo que varias fuentes que dependen del
 * mismo helper (p. ej. data() alimenta notas_venta, comprobantes, totales
 * y balance) disparen una sola consulta.
 */
class WidgetContext
{
    private $filters;
    private $memo = [];

    public function __construct(array $filters = [])
    {
        $this->filters = $this->normalizeFilters($filters);
    }

    public function filters()
    {
        return $this->filters;
    }

    private function normalizeFilters(array $filters)
    {
        return [
            'establishment_id' => $filters['establishment_id'] ?? null,
            'period' => $filters['period'] ?? 'last_week',
            'date_start' => $filters['date_start'] ?? Carbon::now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
            'date_end' => $filters['date_end'] ?? Carbon::now()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d'),
            'month_start' => $filters['month_start'] ?? Carbon::now()->format('Y-m'),
            'month_end' => $filters['month_end'] ?? Carbon::now()->format('Y-m'),
        ];
    }

    /**
     * Memoiza por clave; las fuentes comparten resultados dentro del batch.
     */
    public function remember($key, callable $callback)
    {
        if (!array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $callback();
        }

        return $this->memo[$key];
    }

    public function data()
    {
        return $this->remember('data', function () {
            return (new DashboardData())->data($this->filters);
        });
    }

    public function dataAditional(array $options = [])
    {
        $enabled_move_item = filter_var($options['enabled_move_item'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $enabled_transaction_customer = filter_var($options['enabled_transaction_customer'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $this->remember("data_aditional|{$enabled_move_item}|{$enabled_transaction_customer}", function () use ($enabled_move_item, $enabled_transaction_customer) {
            return (new DashboardSalePurchase())->data(array_merge($this->filters, [
                'enabled_move_item' => $enabled_move_item,
                'enabled_transaction_customer' => $enabled_transaction_customer,
            ]));
        });
    }

    public function globalData()
    {
        return $this->remember('global_data', function () {
            return (new DashboardData())->globalData($this->filters);
        });
    }

    public function cashFlow()
    {
        return $this->remember('cash_flow', function () {
            return (new DashboardData())->cashFlow($this->filters);
        });
    }

    public function salesWeek()
    {
        return $this->remember('sales_week', function () {
            return (new DashboardData())->salesWeek($this->filters);
        });
    }

    public function paymentMethods()
    {
        return $this->remember('payment_methods', function () {
            return (new DashboardData())->paymentMethods($this->filters);
        });
    }

    public function debtors()
    {
        return $this->remember('debtors', function () {
            return (new DashboardData())->debtors($this->filters);
        });
    }

    public function monthGoal()
    {
        return $this->remember('month_goal', function () {
            return (new DashboardData())->monthGoal();
        });
    }

    public function lowStock()
    {
        return $this->remember('low_stock', function () {
            return (new DashboardData())->lowStock($this->filters);
        });
    }

    public function utilities(array $options = [])
    {
        $enabled_expense = filter_var($options['enabled_expense'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $item_id = $options['item_id'] ?? null;

        return $this->remember("utilities|{$enabled_expense}|{$item_id}", function () use ($enabled_expense, $item_id) {
            return (new DashboardUtility())->data(array_merge($this->filters, [
                'enabled_expense' => $enabled_expense,
                'item_id' => $item_id,
            ]));
        });
    }

    public function salesGrowth()
    {
        return $this->remember('sales_growth', function () {
            return (new DashboardKpi())->salesGrowth($this->rangeFilters());
        });
    }

    public function monthlyComparison()
    {
        return $this->remember('monthly_comparison', function () {
            return (new DashboardKpi())->monthlyComparison($this->rangeFilters());
        });
    }

    /**
     * DashboardKpi espera date_start/date_end ya resueltos (sin period/month_*).
     */
    private function rangeFilters()
    {
        $resolved = \Modules\Dashboard\Helpers\DashboardFilterHelper::resolve($this->filters);

        return [
            'establishment_id' => $resolved['establishment_id'],
            'date_start' => $resolved['date_start'],
            'date_end' => $resolved['date_end'],
        ];
    }
}
