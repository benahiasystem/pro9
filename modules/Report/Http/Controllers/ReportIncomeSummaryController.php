<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Cash;
use Modules\CashReport\Services\CashReportRenderer;

/**
 * Ruta legacy del resumen de ingresos. La lógica vive en el módulo CashReport.
 */
class ReportIncomeSummaryController extends Controller
{
    /**
     * Reporte resumen de ingresos (descarga directa, como el original)
     *
     * @param  int $cash_id
     */
    public function pdf($cash_id)
    {
        return app(CashReportRenderer::class)->render('income_summary', Cash::findOrFail($cash_id), [
            'action' => CashReportRenderer::ACTION_DOWNLOAD,
        ]);
    }
}
