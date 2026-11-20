<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $legacy = DB::table('establishments')->where(function ($query) {
                $query->where('template_pdf', 'nrus')->orWhere('template_ticket_pdf', 'nrus');
            })->exists();
            foreach (DB::table('configurations')->get(['id', 'plan']) as $configuration) {
                $plan = json_decode($configuration->plan ?? '', true);
                if (!is_array($plan)) continue;
                $permissions = $plan['module_permissions'] ?? [];
                if (!is_array($permissions)) $permissions = [];
                $legacy = $legacy || filter_var($permissions['nrus'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    || (!array_key_exists('nrus', $permissions) && (int) ($permissions['business'] ?? 0) === 6);
                unset($permissions['nrus']);
                if ((int) ($permissions['business'] ?? 0) === 6) $permissions['business'] = null;
                $plan['module_permissions'] = $permissions;
                if (($plan['name'] ?? null) === 'NRUS') $plan['name'] = 'Plan anterior';
                DB::table('configurations')->where('id', $configuration->id)->update(['plan' => json_encode($plan)]);
            }
            DB::table('establishments')->where('template_pdf', 'nrus')->update(['template_pdf' => 'default']);
            DB::table('establishments')->where('template_ticket_pdf', 'nrus')->update(['template_ticket_pdf' => 'default']);
            DB::table('configurations')->where('formats', 'nrus')->update(['formats' => 'default']);
            DB::table('format_templates')->where('formats', 'nrus')->delete();
            if ($legacy && DB::table('format_templates')->where('formats', 'default')->exists()) {
                DB::table('format_templates')->where('formats', 'default')->update(['is_custom_ticket' => true]);
            } elseif ($legacy) {
                DB::table('format_templates')->insert(['formats' => 'default', 'is_custom_ticket' => true]);
            }
            if ($legacy && Schema::hasTable('cat_operation_types')) {
                $active = DB::table('cat_operation_types')->where('active', true)->pluck('id')->all();
                if ($active === [] || $active === ['0113']) {
                    DB::table('cat_operation_types')->where('id', '0113')->update(['active' => false]);
                    DB::table('cat_operation_types')->whereIn('id', ['0101', '0201', '0202', '0203'])->update(['active' => true]);
                }
            }
        });
    }

    public function down(): void {}
};
