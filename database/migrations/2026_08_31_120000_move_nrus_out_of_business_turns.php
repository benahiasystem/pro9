<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->movePlansToNrusFlag();

        if (Schema::hasTable('business_turns')) {
            DB::table('business_turns')
                ->where('value', 'nrus')
                ->update(['active' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_turns')) {
            DB::table('business_turns')
                ->where('value', 'nrus')
                ->update(['active' => true, 'updated_at' => now()]);
        }

        $this->movePlansToBusinessTurn();
    }

    private function movePlansToNrusFlag(): void
    {
        $this->eachPlanPermissions(function (array $permissions) {
            if ((int) ($permissions['business'] ?? null) !== 6) {
                return null;
            }

            $permissions['business'] = null;
            $permissions['nrus'] = true;

            return $permissions;
        });
    }

    private function movePlansToBusinessTurn(): void
    {
        $this->eachPlanPermissions(function (array $permissions) {
            if (empty($permissions['nrus'])) {
                return null;
            }

            $permissions['business'] = 6;
            unset($permissions['nrus']);

            return $permissions;
        });
    }

    private function eachPlanPermissions(callable $callback): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        $plans = DB::table('plans')
            ->whereNotNull('module_permissions')
            ->select('id', 'module_permissions')
            ->get();

        foreach ($plans as $plan) {
            $permissions = json_decode($plan->module_permissions, true);

            if (!is_array($permissions)) {
                continue;
            }

            $updated = $callback($permissions);

            if (is_null($updated)) {
                continue;
            }

            DB::table('plans')
                ->where('id', $plan->id)
                ->update(['module_permissions' => json_encode($updated)]);
        }
    }
};
