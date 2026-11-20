<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $retiredPlans = DB::table('plans')->where('name', 'NRUS')->where('locked', true)->pluck('id');
            if ($retiredPlans->isNotEmpty()) {
                $assigned = DB::table('clients')->whereIn('plan_id', $retiredPlans)->exists();
                $pending = DB::table('payment_orders')->whereIn('plan_id', $retiredPlans)->whereIn('order_state_id', [1, 3])->exists();
                if ($assigned || $pending) {
                    throw new RuntimeException('El plan retirado tiene clientes u órdenes pendientes.');
                }
                DB::table('configurations')->whereIn('guest_register_plan_id', $retiredPlans)->update(['guest_register_plan_id' => null]);
                DB::table('plans')->whereIn('id', $retiredPlans)->delete();
            }

            $legacyTurn = Schema::hasTable('business_turns')
                ? DB::table('business_turns')->where('value', 'nrus')->first() : null;
            foreach (DB::table('plans')->whereNotNull('module_permissions')->get(['id', 'module_permissions']) as $plan) {
                $permissions = json_decode($plan->module_permissions, true);
                if (!is_array($permissions)) continue;
                unset($permissions['nrus']);
                if ($legacyTurn && (int) ($permissions['business'] ?? 0) === (int) $legacyTurn->id) {
                    $permissions['business'] = null;
                }
                DB::table('plans')->where('id', $plan->id)->update(['module_permissions' => json_encode($permissions)]);
            }
            if ($legacyTurn) DB::table('business_turns')->where('id', $legacyTurn->id)->delete();
        });
    }

    public function down(): void {}
};
