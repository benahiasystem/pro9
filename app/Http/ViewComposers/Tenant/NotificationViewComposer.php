<?php

namespace App\Http\ViewComposers\Tenant;

use App\Http\Helpers\HeaderNotifications;

class NotificationViewComposer
{
    public function compose($view)
    {
        try {
            $view->vc_notification_cards = (new HeaderNotifications())->getAll()['total_count'];
        } catch (\Throwable $exception) {
            $view->vc_notification_cards = 0;
        }
    }
}
