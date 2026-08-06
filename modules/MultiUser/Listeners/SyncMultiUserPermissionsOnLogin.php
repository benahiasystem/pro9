<?php

namespace Modules\MultiUser\Listeners;

use App\Models\Tenant\User;
use Illuminate\Auth\Events\Login;
use Modules\MultiUser\Services\MultiUserPermissionSync;

class SyncMultiUserPermissionsOnLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (!$user instanceof User || !$user->is_multi_user) {
            return;
        }

        MultiUserPermissionSync::syncFromTenantAdmin($user);
    }
}
