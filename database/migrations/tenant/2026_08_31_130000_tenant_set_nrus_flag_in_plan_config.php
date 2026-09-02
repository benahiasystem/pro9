<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantSetNrusFlagInPlanConfig extends Migration
{
    public function up()
    {
        $this->eachPlanPermissions(function (array $permissions) {
            if (array_key_exists('nrus', $permissions)) {
                return null;
            }

            $is_nrus = (int) ($permissions['business'] ?? null) === 6;

            $permissions['nrus'] = $is_nrus;

            if ($is_nrus) {
                $permissions['business'] = null;
            }

            return $permissions;
        });
    }

    public function down()
    {
        $this->eachPlanPermissions(function (array $permissions) {
            if (!array_key_exists('nrus', $permissions)) {
                return null;
            }

            if (!empty($permissions['nrus'])) {
                $permissions['business'] = 6;
            }

            unset($permissions['nrus']);

            return $permissions;
        });
    }

    private function eachPlanPermissions(callable $callback)
    {
        if (!Schema::hasTable('configurations')) {
            return;
        }

        $configurations = DB::table('configurations')
            ->whereNotNull('plan')
            ->select('id', 'plan')
            ->get();

        foreach ($configurations as $configuration) {
            $plan = json_decode($configuration->plan, true);

            if (!is_array($plan) || !is_array($plan['module_permissions'] ?? null)) {
                continue;
            }

            $updated = $callback($plan['module_permissions']);

            if (is_null($updated)) {
                continue;
            }

            $plan['module_permissions'] = $updated;

            DB::table('configurations')
                ->where('id', $configuration->id)
                ->update(['plan' => json_encode($plan)]);
        }
    }
}
