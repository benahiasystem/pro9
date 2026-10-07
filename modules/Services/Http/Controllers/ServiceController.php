<?php
// ######## INICIO API BCV ########
namespace Modules\Services\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Services\ExchangeRates\TenantExchangeRateService;

class ServiceController extends Controller
{
    public function exchange($date)
    {
        return app(TenantExchangeRateService::class)->exchange($date);
    }
}
// ######## FIN API BCV ########
