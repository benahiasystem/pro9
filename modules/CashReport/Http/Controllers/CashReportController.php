<?php

namespace Modules\CashReport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\CashReport\Services\CashReportRegistry;
use Modules\CashReport\Services\CashReportRenderer;
use App\Models\Tenant\Cash;

class CashReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! $this->canGenerateReports()) {
                abort(401, 'No tiene permisos para generar reportes de caja');
            }

            return $next($request);
        });
    }

    public function catalog()
    {
        return CashReportRegistry::catalog();
    }

    public function generate(Request $request, $type, $cash, CashReportRenderer $renderer)
    {
        $cash = Cash::findOrFail($cash);

        return $renderer->render($type, $cash, $request->only(['format', 'paper', 'summary', 'is_garage', 'action']));
    }

    /**
     * Mismo criterio que el listado de cajas: admin siempre, vendedor si la configuración lo permite.
     */
    protected function canGenerateReports(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->type === 'admin') {
            return true;
        }

        return $user->type === 'seller'
            && (bool) optional(Configuration::AvailableReportSeller())->available_cash_report_seller;
    }
}
