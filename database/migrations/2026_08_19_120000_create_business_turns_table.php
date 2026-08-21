<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class CreateBusinessTurnsTable extends Migration
{
    public function up()
    {
        Schema::create('business_turns', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('value', 60)->unique();
            $table->string('description', 255)->nullable();
            $table->json('modules')->nullable();
            $table->json('levels')->nullable();
            $table->json('apps')->nullable();
            $table->json('app_levels')->nullable();
            $table->boolean('locked')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $this->seedDefaults();

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE business_turns AUTO_INCREMENT = 7');
        }
    }

    public function down()
    {
        Schema::dropIfExists('business_turns');
    }

    private function seedDefaults()
    {
        $existing_modules = DB::table('modules')->pluck('id')->all();
        $all_levels = DB::table('module_levels')->select('id', 'module_id')->get();

        $levels_of = function (array $module_ids) use ($all_levels) {
            return $all_levels->whereIn('module_id', $module_ids)
                ->map(function ($level) {
                    return "{$level->module_id}-{$level->id}";
                })
                ->values()
                ->all();
        };

        $levels_pick = function ($module_id, array $level_ids) use ($all_levels) {
            return $all_levels->where('module_id', $module_id)
                ->whereIn('id', $level_ids)
                ->map(function ($level) {
                    return "{$level->module_id}-{$level->id}";
                })
                ->values()
                ->all();
        };

        $only_existing = function (array $module_ids) use ($existing_modules) {
            return array_values(array_intersect($module_ids, $existing_modules));
        };

        $basic = $only_existing([7, 1, 6, 17, 18, 5, 14]);
        $extended = $only_existing([7, 1, 6, 17, 18, 5, 14, 8, 4]);

        $nrus_all = $only_existing([7, 2, 17, 18, 8, 52, 4]);
        $nrus_modules = $only_existing([7, 2, 1, 17, 18, 8, 12, 52, 4]);
        $nrus_apps = $only_existing([11, 14, 5, 53]);

        $rows = [
            [
                'id' => 1,
                'value' => 'basic',
                'name' => 'Básico',
                'description' => 'Módulos esenciales de facturación.',
                'modules' => array_merge($basic, $only_existing([12])),
                'levels' => array_merge($levels_of($basic), $levels_pick(12, [16])),
                'apps' => [],
                'app_levels' => [],
                'locked' => 0,
                'sort' => 1,
            ],
            [
                'id' => 2,
                'value' => 'pharmacy',
                'name' => 'Farmacia',
                'description' => 'Incluye la app de farmacia (DIGEMID).',
                'modules' => array_merge($extended, $only_existing([12])),
                'levels' => array_merge($levels_of($extended), $levels_pick(12, [16])),
                'apps' => $only_existing([19]),
                'app_levels' => $levels_of($only_existing([19])),
                'locked' => 0,
                'sort' => 2,
            ],
            [
                'id' => 3,
                'value' => 'hotel',
                'name' => 'Hotel',
                'description' => 'Incluye la app de hotel.',
                'modules' => array_merge($extended, $only_existing([12])),
                'levels' => array_merge($levels_of($extended), $levels_pick(12, [16])),
                'apps' => $only_existing([15]),
                'app_levels' => $levels_of($only_existing([15])),
                'locked' => 0,
                'sort' => 3,
            ],
            [
                'id' => 4,
                'value' => 'restaurant',
                'name' => 'Restaurante',
                'description' => 'Incluye la app de restaurante.',
                'modules' => array_merge($extended, $only_existing([12])),
                'levels' => array_merge($levels_of($extended), $levels_pick(12, [16])),
                'apps' => $only_existing([23]),
                'app_levels' => $levels_of($only_existing([23])),
                'locked' => 0,
                'sort' => 4,
            ],
            [
                'id' => 6,
                'value' => 'nrus',
                'name' => 'NRUS',
                'description' => 'Régimen NRUS: solo 1 sucursal y hasta S/ 8000 de ventas al mes.',
                'modules' => $nrus_modules,
                'levels' => array_merge(
                    $levels_of($nrus_all),
                    $levels_pick(1, [1, 2, 5, 8, 15, 84]),
                    $levels_pick(12, [16])
                ),
                'apps' => $nrus_apps,
                'app_levels' => $levels_of($nrus_apps),
                'locked' => 1,
                'sort' => 6,
            ],
        ];

        $now = now();

        foreach ($rows as $row) {
            DB::table('business_turns')->insert([
                'id' => $row['id'],
                'name' => $row['name'],
                'value' => $row['value'],
                'description' => $row['description'],
                'modules' => json_encode(array_values(array_unique($row['modules']))),
                'levels' => json_encode(array_values(array_unique($row['levels']))),
                'apps' => json_encode(array_values(array_unique($row['apps']))),
                'app_levels' => json_encode(array_values(array_unique($row['app_levels']))),
                'locked' => $row['locked'],
                'is_default' => 1,
                'active' => 1,
                'sort' => $row['sort'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
