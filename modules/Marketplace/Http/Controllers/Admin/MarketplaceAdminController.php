<?php

namespace Modules\Marketplace\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\Marketplace\Models\Report;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\Settings;

class MarketplaceAdminController extends Controller
{
    /**
     * La pantalla del admin: un Blade que monta el componente Vue.
     *
     * Deliberadamente NO lleva el middleware marketplace.enabled: el admin
     * tiene que poder revisar y aprobar tiendas con el marketplace apagado,
     * para recién entonces encenderlo.
     */
    public function index(): View
    {
        return view('marketplace::admin.index', [
            'summary' => [
                'is_enabled' => Settings::isEnabled(),
                'pending' => Store::where('status', Store::STATUS_PENDING)->count(),
                'approved' => Store::where('status', Store::STATUS_APPROVED)->count(),
                'open_reports' => Report::where('status', Report::STATUS_OPEN)->count(),
                'public_url' => url(config('marketplace.route_prefix')),
            ],
        ]);
    }
}
