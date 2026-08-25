<?php

use App\Models\System\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plan bloqueado NRUS (como Ilimitado): no editable ni eliminable desde UI.
     * Límites: 1 sucursal, ventas mensuales S/ 8000, giro business = 6.
     */
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        if (Plan::where('name', 'NRUS')->where('locked', true)->exists()) {
            return;
        }

        Plan::create([
            'name' => 'NRUS',
            'pricing' => 49,
            'limit_users' => 0,
            'limit_documents' => 0,
            'plan_documents' => [1],
            'is_popular' => false,
            'locked' => true,
            'establishments_limit' => 1,
            'establishments_unlimited' => false,
            'sales_limit' => 8000,
            'sales_unlimited' => false,
            'include_sale_notes_sales_limit' => false,
            'include_sale_notes_limit_documents' => false,
            'module_permissions' => $this->buildNrusModulePermissions(),
            'whatsapp_messages_limit' => 0,
            'whatsapp_messages_unlimited' => true,
            'test_days' => 0,
            'test_days_enabled' => false,
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        Plan::where('name', 'NRUS')
            ->where('locked', true)
            ->delete();
    }

    /**
     * Toma módulos/apps/niveles del giro NRUS si ya existe; si no, usa el spec del formulario.
     *
     * @return array{business:int,modules:int[],apps:int[],levels:int[]}
     */
    private function buildNrusModulePermissions(): array
    {
        if (Schema::hasTable('business_turns')) {
            $turn = DB::table('business_turns')->where('value', 'nrus')->first();

            if ($turn) {
                $modules = json_decode($turn->modules ?? '[]', true) ?: [];
                $apps = json_decode($turn->apps ?? '[]', true) ?: [];
                $levelsRaw = array_merge(
                    json_decode($turn->levels ?? '[]', true) ?: [],
                    json_decode($turn->app_levels ?? '[]', true) ?: []
                );

                return [
                    'business' => 6,
                    'modules' => array_values(array_unique(array_map('intval', $modules))),
                    'apps' => array_values(array_unique(array_map('intval', $apps))),
                    'levels' => array_values(array_unique($this->normalizeLevelIds($levelsRaw))),
                ];
            }
        }

        return [
            'business' => 6,
            'modules' => [7, 2, 1, 17, 18, 8, 12, 52, 4],
            'apps' => [11, 14, 5, 53],
            'levels' => [1, 2, 5, 8, 15, 84, 16],
        ];
    }

    /**
     * El giro guarda "moduleId-levelId"; el plan guarda solo el id del level.
     *
     * @param  array<int, mixed> $levels
     * @return int[]
     */
    private function normalizeLevelIds(array $levels): array
    {
        $ids = [];

        foreach ($levels as $level) {
            if (is_string($level) && str_contains($level, '-')) {
                $parts = explode('-', $level, 2);
                $ids[] = (int) $parts[1];
                continue;
            }

            $ids[] = (int) $level;
        }

        return $ids;
    }
};
