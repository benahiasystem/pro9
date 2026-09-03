<?php

namespace App\Http\ViewComposers\Tenant;

use App\Models\System\Configuration;

/**
 * Publicidad configurada desde el panel system y mostrada en los tenant.
 */
class SystemAdsViewComposer
{
    public function compose($view)
    {
        $view->vc_system_ads_modal = Configuration::getTenantModalAds();
        $view->vc_system_ads_toolbar = Configuration::getTenantToolbarAds();
        $view->vc_system_ads_notification = Configuration::getTenantNotificationAds();
    }
}
