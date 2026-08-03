<?php

namespace App\Http\ViewComposers\Tenant;

use App\Http\Helpers\HeaderNotifications;
use App\Models\Tenant\Company;

class CompanyViewComposer
{
    public function compose($view)
    {
        $view->vc_company = Company::first();
        $view->vc_orders = HeaderNotifications::getPendingOrdersCount();
    }
}
