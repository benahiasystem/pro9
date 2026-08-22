<?php

namespace Modules\CashReport\Services;

/**
 * Catálogo de reportes de caja.
 *
 * Cada entrada define: etiqueta, categoría, builder, vistas por formato,
 * formatos y papeles soportados y opciones adicionales.
 */
class CashReportRegistry
{
    const FORMAT_PDF = 'pdf';
    const FORMAT_EXCEL = 'excel';

    const PAPER_A4 = 'a4';
    const PAPER_TICKET_80 = 'ticket80';
    const PAPER_TICKET_58 = 'ticket58';

    public static function categories(): array
    {
        return [
            'cash' => ['label' => 'Caja', 'description' => 'Resumen de movimientos de la caja: ventas, métodos de pago, saldo inicial y final.'],
            'payments' => ['label' => 'Efectivo', 'description' => 'Pagos en efectivo con destino caja.'],
            'products' => ['label' => 'Productos', 'description' => 'Ítems vendidos durante la caja.'],
            'income' => ['label' => 'Ingresos', 'description' => 'Resumen de ingresos por método de pago y comprobantes a crédito.'],
        ];
    }

    /**
     * type => definición. Se completa conforme se migra cada reporte.
     *
     * Ejemplo:
     * 'cash_summary' => [
     *     'label' => 'Reporte de caja',
     *     'description' => 'Detalle completo de documentos y pagos.',
     *     'category' => 'cash',
     *     'builder' => Builders\CashSummaryBuilder::class,
     *     'views' => ['pdf' => 'cashreport::reports.cash_summary_a4', 'ticket' => '...', 'excel' => '...'],
     *     'formats' => [self::FORMAT_PDF, self::FORMAT_EXCEL],
     *     'papers' => [self::PAPER_A4, self::PAPER_TICKET_80, self::PAPER_TICKET_58],
     *     'options' => [['key' => 'summary', 'label' => 'Solo resumen (ticket)']],
     * ],
     */
    public static function reports(): array
    {
        return [
            'cash_summary' => [
                'label' => 'Reporte de caja',
                'description' => 'Detalle completo de documentos, pagos y productos.',
                'category' => 'cash',
                'builder' => Builders\CashSummaryBuilder::class,
                'views' => [
                    'pdf' => 'cashreport::reports.cash_summary_a4',
                    'ticket' => 'cashreport::reports.cash_summary_ticket',
                    'excel' => 'cashreport::reports.cash_summary_excel',
                ],
                'formats' => [self::FORMAT_PDF, self::FORMAT_EXCEL],
                'papers' => [self::PAPER_A4, self::PAPER_TICKET_80, self::PAPER_TICKET_58],
                'options' => [
                    ['key' => 'summary', 'label' => 'Solo resumen (ticket)', 'papers' => [self::PAPER_TICKET_80, self::PAPER_TICKET_58]],
                ],
            ],
            'cash_simple' => [
                'label' => 'Reporte de caja simple',
                'description' => 'Versión simplificada A4.',
                'category' => 'cash',
                'builder' => Builders\CashSimpleBuilder::class,
                'views' => ['pdf' => 'cashreport::reports.cash_simple_a4'],
                'formats' => [self::FORMAT_PDF],
                'papers' => [self::PAPER_A4],
            ],
            'summary_daily_operations' => [
                'label' => 'Resumen de operaciones diarias',
                'description' => 'Ventas al contado/crédito, compras y saldos globales.',
                'category' => 'cash',
                'builder' => Builders\SummaryDailyOperationsBuilder::class,
                'views' => ['pdf' => 'cashreport::reports.summary_daily_operations_a4'],
                'formats' => [self::FORMAT_PDF],
                'papers' => [self::PAPER_A4],
            ],
            'general_with_payments' => [
                'label' => 'Reporte general con pagos (V2)',
                'description' => 'Pagos al contado con destino caja agrupados por método de pago.',
                'category' => 'cash',
                'builder' => Builders\GeneralWithPaymentsBuilder::class,
                'views' => ['pdf' => 'cashreport::reports.general_with_payments_a4'],
                'formats' => [self::FORMAT_PDF],
                'papers' => [self::PAPER_A4],
            ],
            'income_egress' => [
                'label' => 'Ingresos y egresos',
                'description' => 'Movimientos de efectivo con destino caja por tipo de transacción.',
                'category' => 'payments',
                'builder' => Builders\IncomeEgressBuilder::class,
                'views' => ['pdf' => 'cashreport::reports.income_egress_a4'],
                'formats' => [self::FORMAT_PDF],
                'papers' => [self::PAPER_A4],
            ],
            'products' => [
                'label' => 'Productos · Punto de venta',
                'description' => 'Cantidades y totales por producto vendido en la caja.',
                'category' => 'products',
                'builder' => Builders\ProductsBuilder::class,
                'views' => [
                    'pdf' => 'cashreport::reports.products_a4',
                    'excel' => 'cashreport::reports.products_excel',
                ],
                'formats' => [self::FORMAT_PDF, self::FORMAT_EXCEL],
                'papers' => [self::PAPER_A4],
            ],
            'products_garage' => [
                'label' => 'Productos · Venta rápida',
                'description' => 'Productos vendidos incluyendo datos de venta rápida.',
                'category' => 'products',
                'builder' => Builders\ProductsBuilder::class,
                'views' => ['pdf' => 'cashreport::reports.products_a4'],
                'formats' => [self::FORMAT_PDF],
                'papers' => [self::PAPER_A4],
                'defaults' => ['is_garage' => true],
            ],
            'income_summary' => [
                'label' => 'Resumen de ingreso',
                'description' => 'Pagos por método de pago, comprobantes con pago y a crédito.',
                'category' => 'income',
                'builder' => Builders\IncomeSummaryBuilder::class,
                'views' => ['pdf' => 'cashreport::reports.income_summary_a4'],
                'formats' => [self::FORMAT_PDF],
                'papers' => [self::PAPER_A4],
            ],
            'payments_associated' => [
                'label' => 'Pagos asociados a caja',
                'description' => 'Facturas, boletas y notas de venta pagadas en efectivo con destino caja. Excel: ingresos y egresos por moneda.',
                'category' => 'payments',
                'builder' => Builders\PaymentsAssociatedCashBuilder::class,
                'views' => [
                    'pdf' => 'cashreport::reports.payments_associated_a4',
                    'excel' => 'cashreport::reports.payments_associated_excel',
                ],
                'formats' => [self::FORMAT_PDF, self::FORMAT_EXCEL],
                'papers' => [self::PAPER_A4],
            ],
        ];
    }

    public static function get(string $type): ?array
    {
        $report = static::reports()[$type] ?? null;

        return $report ? array_merge(['type' => $type], $report) : null;
    }

    /**
     * Estructura pública para el selector del frontend.
     */
    public static function catalog(): array
    {
        $catalog = [];

        foreach (static::categories() as $key => $category) {
            $catalog[$key] = array_merge(['key' => $key, 'reports' => []], $category);
        }

        foreach (static::reports() as $type => $report) {
            if (! isset($catalog[$report['category']])) {
                continue;
            }

            $catalog[$report['category']]['reports'][] = [
                'type' => $type,
                'label' => $report['label'],
                'description' => $report['description'] ?? '',
                'formats' => $report['formats'],
                'papers' => $report['papers'] ?? [self::PAPER_A4],
                'options' => $report['options'] ?? [],
            ];
        }

        return array_values($catalog);
    }
}
