<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
// ######## INICIO CALCULOS EXACTOS PRO9 ########

namespace Modules\Dashboard\Widgets;

use App\Models\Tenant\Company;
use App\Support\Exact\Decimal as D;

/**
 * Fuentes de widgets propias del módulo Dashboard. Cada una envuelve la
 * lógica ya existente en los helpers (DashboardData, DashboardKpi,
 * DashboardSalePurchase, DashboardUtility) y la normaliza a WidgetDataset.
 */
class DefaultWidgetSources
{
    private static $companyEnvironment;

    private static function companyEnvironment()
    {
        if (is_null(static::$companyEnvironment)) {
            static::$companyEnvironment = optional(Company::select('fiscal_environment')->first())->fiscal_environment;
        }

        return static::$companyEnvironment;
    }

    /**
     * Delta del KPI a partir del trend de globalData(): penúltimo punto
     * como valor previo (en modo comparación el trend es [anterior, actual]).
     */
    private static function kpiFromGlobal($global, $trendKey, $totalKey)
    {
        $trend=$global['trend'];$values=[];
        foreach ($trend['calculation_snapshots'] as $snapshot) $values[]=static::original(['calculation_snapshot'=>$snapshot],$trendKey);
        $count=count($values);$previous=$count>=2 ? $values[$count-2] : null;
        return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
            ->serie($trend['labels'],[['name'=>'','data'=>$values]])
            ->totals(static::original($global,$totalKey),$previous);
    }

    private static function original(array $record, string $field): \Brick\Math\BigRational
    {
        $snapshot=$record['calculation_snapshot'] ?? null;
        if (!is_array($snapshot) || ($snapshot['version'] ?? null)!==D::VERSION
            || !is_array($snapshot['values'][$field] ?? null)) {
            throw new \UnexpectedValueException('Falta la fracción original del widget: '.$field);
        }
        return D::of($snapshot['values'][$field]);
    }

    private static function fractions(array $values): array
    {
        return array_map(static function ($value) {
            if ($value===null) return null;
            if (!is_array($value) || !isset($value['n'],$value['d'])) {
                throw new \UnexpectedValueException('El vector del widget requiere fracciones n/d.');
            }
            return D::of($value);
        },$values);
    }

    private static function graphValues(array $dataset): array
    {
        if (array_key_exists('data_fractions',$dataset)) return self::fractions($dataset['data_fractions']);
        $snapshot=$dataset['calculation_snapshot'] ?? null;
        if (!is_array($snapshot) || ($snapshot['version'] ?? null)!==D::VERSION || !is_array($snapshot['values'] ?? null)) {
            throw new \UnexpectedValueException('El gráfico del widget carece de sus operandos originales.');
        }
        return self::fractions(array_values($snapshot['values']));
    }

    /**
     * @return WidgetSource[]
     */
    public static function all()
    {
        return array_merge(
            static::ventas(),
            static::finanzas(),
            static::compras(),
            static::clientes(),
            static::inventario()
        );
    }

    private static function ventas()
    {
        $module = ['module' => 'ventas', 'module_label' => 'Ventas', 'icon' => 'ti-receipt-2'];
        return [
            new CallbackWidgetSource(array_merge($module, [
                'key' => 'ventas.ventas_totales',
                'label' => 'Ventas',
                'description' => 'vs periodo anterior',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'serie',
                'default_type' => 'kpi_spark',
            ]), function (WidgetContext $ctx) {
                return static::kpiFromGlobal($ctx->globalData(), 'monthly_sales', 'monthly_sales');
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'ventas.ticket_promedio',
                'label' => 'Ticket promedio',
                'description' => 'vs periodo anterior',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'serie',
                'default_type' => 'kpi_spark',
            ]), function (WidgetContext $ctx) {
                return static::kpiFromGlobal($ctx->globalData(), 'average_ticket', 'average_ticket');
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'ventas.notas_venta',
                'label' => 'Notas de venta',
                'description' => 'Cobros y pendientes',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'categorias',
                'default_type' => 'donut',
            ]), function (WidgetContext $ctx) {
                $data = $ctx->data()['sale_note'];
                $graph = $data['graph'];

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->breakdown($graph['labels'], static::graphValues($graph['datasets'][0]))
                    ->totals(static::original($data['totals'],'total'))
                    ->meta(['totals' => $data['totals']]);
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'ventas.comprobantes',
                'label' => 'Comprobantes',
                'description' => 'Cobros y pendientes',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'categorias',
                'default_type' => 'donut',
            ]), function (WidgetContext $ctx) {
                $data = $ctx->data()['document'];
                $graph = $data['graph'];

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->breakdown($graph['labels'], static::graphValues($graph['datasets'][0]))
                    ->totals(static::original($data['totals'],'total'))
                    ->meta(['totals' => $data['totals']]);
            }, function () {
                return true;
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'ventas.totales',
                'label' => 'Totales',
                'description' => 'Notas de venta y comprobantes',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'serie',
                'default_type' => 'area',
            ]), function (WidgetContext $ctx) {
                $data = $ctx->data()['general'];
                $graph = $data['graph'];

                $series = array_map(function ($dataset) {
                    return ['name' => $dataset['label'], 'data' => static::graphValues($dataset)];
                }, $graph['datasets']);

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->serie($graph['labels'], $series)
                    ->totals(static::original($data['totals'],'total'))
                    ->meta(['totals' => $data['totals']]);
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'ventas.periodo',
                'label' => 'Ventas del periodo',
                'description' => 'Periodo filtrado vs anterior',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'serie',
                'default_type' => 'bar',
            ]), function (WidgetContext $ctx) {
                $data = $ctx->salesWeek();
                $current = static::fractions($data['current_fractions']);
                $previous = static::fractions($data['previous_fractions']);

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->serie($data['labels'], [
                        ['name' => 'Periodo filtrado', 'data' => $current],
                        ['name' => 'Periodo anterior', 'data' => $previous],
                    ])
                    ->totals(static::original($data,'current_total'), static::original($data,'previous_total'))
                    ->meta([
                        'subtitle' => $data['subtitle'] ?? null,
                        'previous_labels' => $data['previous_labels'] ?? [],
                    ]);
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'ventas.crecimiento',
                'label' => 'Crecimiento de ventas',
                'description' => 'Año actual vs anterior',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'serie',
                'default_type' => 'line',
            ]), function (WidgetContext $ctx) {
                $data = $ctx->salesGrowth();
                $current = static::fractions($data['current_fractions']);
                $previous = static::fractions($data['previous_fractions']);

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->serie($data['categories'], [
                        ['name' => $data['current_label'], 'data' => $current],
                        ['name' => $data['previous_label'], 'data' => $previous],
                    ])
                    ->totals(static::original($data,'current_total'), static::original($data,'previous_total'));
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'ventas.comparativo',
                'label' => 'Ventas vs compras',
                'description' => 'Comparativo del periodo',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'serie',
                'default_type' => 'bar',
            ]), function (WidgetContext $ctx) {
                $data = $ctx->monthlyComparison();
                $sales = static::fractions($data['sales_fractions']);
                $purchases = static::fractions($data['purchases_fractions']);

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->serie($data['categories'], [
                        ['name' => 'Ventas', 'data' => $sales],
                        ['name' => 'Compras', 'data' => $purchases],
                    ])
                    ->totals(static::original($data,'sales_total'));
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'ventas.top_productos',
                'label' => 'Productos más vendidos',
                'description' => 'Top del periodo',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'categorias',
                'default_type' => 'ranking',
                'options' => [
                    ['key' => 'enabled_move_item', 'label' => 'Ordenar por cantidad de movimientos', 'type' => 'boolean', 'default' => false],
                ],
            ]), function (WidgetContext $ctx, array $options) {
                $items = collect($ctx->dataAditional($options)['items_by_sales'] ?? []);

                $labels = $items->map(function ($row) {
                    $row = (array) $row;
                    return $row['description'] ?? $row['name'] ?? '';
                })->all();

                $values = $items->map(function ($row) {
                    $row = (array) $row;
                    return static::original($row,'total');
                })->all();

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->breakdown($labels, $values)
                    ->totals(D::sum($values))
                    ->meta(['items' => $items->values()->all()]);
            }),
        ];
    }

    private static function finanzas()
    {
        $module = ['module' => 'finanzas', 'module_label' => 'Finanzas / Caja', 'icon' => 'ti-cash'];

        return [
            new CallbackWidgetSource(array_merge($module, [
                'key' => 'finanzas.por_cobrar',
                'label' => 'Por cobrar',
                'description' => 'vs periodo anterior',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'serie',
                'default_type' => 'kpi_spark',
            ]), function (WidgetContext $ctx) {
                return static::kpiFromGlobal($ctx->globalData(), 'accounts_receivable', 'accounts_receivable');
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'finanzas.utilidad_neta',
                'label' => 'Utilidad neta',
                'description' => 'vs periodo anterior',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'serie',
                'default_type' => 'kpi_spark',
            ]), function (WidgetContext $ctx) {
                return static::kpiFromGlobal($ctx->globalData(), 'net_utility', 'net_utility');
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'finanzas.flujo_caja',
                'label' => 'Flujo de caja',
                'description' => 'Ingresos vs egresos',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'serie',
                'default_type' => 'area',
            ]), function (WidgetContext $ctx) {
                $data = $ctx->cashFlow();
                $income = static::fractions($data['income_fractions']);
                $egress = static::fractions($data['egress_fractions']);

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->serie($data['labels'], [
                        ['name' => 'Ingresos', 'data' => $income],
                        ['name' => 'Egresos', 'data' => $egress],
                    ])
                    ->totals(static::original($data,'net_total'))
                    ->meta(['subtitle' => $data['subtitle'] ?? null]);
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'finanzas.medios_pago',
                'label' => '¿Cómo me pagan?',
                'description' => 'Distribución de cobros',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'categorias',
                'default_type' => 'donut',
            ]), function (WidgetContext $ctx) {
                $data = $ctx->paymentMethods();

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->breakdown($data['labels'], static::fractions($data['values_fractions']))
                    ->totals(static::original($data,'total'))
                    ->meta(['subtitle' => $data['subtitle'] ?? null]);
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'finanzas.deudores',
                'label' => '¿Quién me debe?',
                'description' => 'Cuentas por cobrar',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'categorias',
                'default_type' => 'custom',
                'custom_component' => 'widget-debtors',
            ]), function (WidgetContext $ctx) {
                $data = $ctx->debtors();
                $items = collect($data['items'] ?? []);

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->breakdown(
                        $items->pluck('customer')->all(),
                        $items->map(static fn($row)=>static::original($row,'total_to_pay'))->all()
                    )
                    ->totals(static::original($data,'total'))
                    ->meta([
                        'items' => $items->values()->all(),
                        'count' => $data['count'] ?? 0,
                    ]);
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'finanzas.meta_mes',
                'label' => 'Meta del mes',
                'description' => 'Avance sobre la meta configurada',
                'unit' => WidgetDataset::UNIT_PERCENT,
                'focus' => 'categorias',
                'default_type' => 'custom',
                'custom_component' => 'widget-month-goal',
                'types' => ['custom', 'radial', 'kpi'],
            ]), function (WidgetContext $ctx) {
                $data = $ctx->monthGoal();

                return WidgetDataset::make(WidgetDataset::UNIT_PERCENT)
                    ->breakdown(['Avance'], [static::original($data,'percent')])
                    ->totals(static::original($data,'percent'))
                    ->meta($data);
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'finanzas.utilidades',
                'label' => 'Utilidades / Ganancias',
                'description' => 'Ingresos, egresos y utilidad',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'categorias',
                'default_type' => 'donut',
                'options' => [
                    ['key' => 'enabled_expense', 'label' => 'Considerar gastos', 'type' => 'boolean', 'default' => true],
                    ['key' => 'item_id', 'label' => 'Filtrar por producto', 'type' => 'item', 'default' => null],
                ],
            ]), function (WidgetContext $ctx, array $options) {
                $data = $ctx->utilities($options)['utilities'];
                $totals = $data['totals'];

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->breakdown(['Ingreso', 'Egreso'], [static::original($totals,'total_income'), static::original($totals,'total_egress')])
                    ->totals(static::original($totals,'utility'))
                    ->meta(['totals' => $totals]);
            }),

            new CallbackWidgetSource(array_merge($module, [
                'key' => 'finanzas.balance',
                'label' => 'Balance',
                'description' => 'Ventas - Compras - Gastos',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'categorias',
                'default_type' => 'donut',
            ]), function (WidgetContext $ctx) {
                $data = $ctx->data()['balance'];
                $graph = $data['graph'];

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->breakdown($graph['labels'], static::graphValues($graph['datasets'][0]))
                    ->totals(static::original($data['totals'],'all_totals'))
                    ->meta(['totals' => $data['totals']]);
            }),
        ];
    }

    private static function compras()
    {
        $module = ['module' => 'compras', 'module_label' => 'Compras', 'icon' => 'ti-shopping-bag'];

        return [
            new CallbackWidgetSource(array_merge($module, [
                'key' => 'compras.totales',
                'label' => 'Compras',
                'description' => 'Compras y percepciones del año',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'serie',
                'default_type' => 'line',
            ]), function (WidgetContext $ctx) {
                $data = $ctx->dataAditional()['purchase'];
                $graph = $data['graph'];

                $perception=static::graphValues($graph['datasets'][0]);
                $purchases=static::graphValues($graph['datasets'][1]);
                $total=static::graphValues($graph['datasets'][2]);

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->serie($graph['labels'], [
                        ['name' => 'Total percepciones', 'data' => $perception],
                        ['name' => 'Total compras', 'data' => $purchases],
                        ['name' => 'Total', 'data' => $total],
                    ])
                    ->totals(static::original($data['totals'],'total'))
                    ->meta(['totals' => $data['totals']]);
            }),
        ];
    }

    private static function clientes()
    {
        $module = ['module' => 'clientes', 'module_label' => 'Clientes', 'icon' => 'ti-users'];

        return [
            new CallbackWidgetSource(array_merge($module, [
                'key' => 'clientes.top',
                'label' => 'Top clientes',
                'description' => 'Mejores clientes del periodo',
                'unit' => WidgetDataset::UNIT_MONEY,
                'focus' => 'categorias',
                'default_type' => 'ranking',
                'options' => [
                    ['key' => 'enabled_transaction_customer', 'label' => 'Ordenar por transacciones', 'type' => 'boolean', 'default' => false],
                ],
            ]), function (WidgetContext $ctx, array $options) {
                $customers = collect($ctx->dataAditional($options)['top_customers'] ?? []);

                return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                    ->breakdown(
                        $customers->pluck('name')->all(),
                        $customers->map(function ($row) {
                            return static::original((array)$row,'total');
                        })->all()
                    )
                    ->totals(null)
                    ->meta(['items' => $customers->values()->all()]);
            }),
        ];
    }

    private static function inventario()
    {
        $module = ['module' => 'inventario', 'module_label' => 'Inventario', 'icon' => 'ti-package'];

        return [
            new CallbackWidgetSource(array_merge($module, [
                'key' => 'inventario.stock_bajo',
                'label' => 'Stock por agotarse',
                'description' => 'Productos con stock mínimo',
                'unit' => WidgetDataset::UNIT_COUNT,
                'focus' => 'categorias',
                'default_type' => 'custom',
                'custom_component' => 'widget-low-stock',
                'types' => ['custom', 'table', 'barh'],
            ]), function (WidgetContext $ctx) {
                $data = $ctx->lowStock();
                $items = collect($data['items'] ?? []);

                return WidgetDataset::make(WidgetDataset::UNIT_COUNT)
                    ->breakdown(
                        $items->pluck('product')->all(),
                        $items->map(static fn($row)=>static::original($row,'stock'))->all()
                    )
                    ->totals($data['total'])
                    ->meta(['items' => $items->values()->all()]);
            }),
        ];
    }

}
// ######## FIN CALCULOS EXACTOS PRO9 ########
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
