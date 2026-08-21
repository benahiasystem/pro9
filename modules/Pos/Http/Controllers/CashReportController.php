<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Cash;
use Modules\CashReport\Services\CashReportRegistry;
use Modules\CashReport\Services\CashReportRenderer;

/**
 * Rutas legacy de reportes de caja. La lógica vive en el módulo CashReport.
 *
 * @deprecated Usar cash-reports/generate/{type}/{cash}
 */
class CashReportController extends Controller
{
    /**
     * Reporte de caja para pagos en efectivo con destino caja, ingresos y egresos (excel)
     *
     * @param  int $cash_id
     */
    public function cashPaymentReportExcel($cash_id)
    {
        return app(CashReportRenderer::class)->render('payments_associated', Cash::findOrFail($cash_id), [
            'format' => CashReportRegistry::FORMAT_EXCEL,
        ]);
    }

    /**
     * Reporte general de caja v2, asociado a pagos
     *
     * @param  int $cash_id
     */
    public function generalCashReportWithPayments($cash_id)
    {
        return app(CashReportRenderer::class)->render('general_with_payments', Cash::findOrFail($cash_id));
    }

    /**
     * Pagos asociados a caja, con destino caja y en efectivo (cpe y nv)
     *
     * @param  int $cash_id
     */
    public function reportPaymentsAssociatedCash($cash_id)
    {
        return app(CashReportRenderer::class)->render('payments_associated', Cash::findOrFail($cash_id));
    }

    /**
     * Resumen de Operaciones Diarias
     *
     * @param  int $cash_id
     */
    public function reportSummaryDailyOperations($cash_id)
    {
        return app(CashReportRenderer::class)->render('summary_daily_operations', Cash::findOrFail($cash_id));
    }
}
