<?php

namespace Modules\MultiUser\Services;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class MultiUserPermissionSync
{
    /**
     * El usuario espejo (is_multi_user) debe ver los módulos del plan de la
     * empresa destino (admin id=1), no los de la empresa origen.
     */
    public static function syncFromTenantAdmin(User $user): void
    {
        $admin = User::whereFilterWithOutRelations()->find(1);

        if (!$admin) {
            return;
        }

        self::syncByValues(
            $user,
            $admin->modules()->pluck('value')->toArray(),
            $admin->levels()->pluck('value')->toArray()
        );
    }

    public static function syncByValues(User $user, array $moduleValues, array $levelValues): void
    {
        $moduleIds = [];
        $levelIds = [];

        if (!empty($moduleValues)) {
            $moduleIds = DB::connection('tenant')
                ->table('modules')
                ->whereIn('value', $moduleValues)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        if (!empty($levelValues)) {
            $levelIds = DB::connection('tenant')
                ->table('module_levels')
                ->whereIn('value', $levelValues)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        if (empty($moduleIds) && empty($levelIds)) {
            return;
        }

        $user->setModuleAndLevelModule($moduleIds, $levelIds);
    }
}
