<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega el widget Utilidades / Ganancias (finanzas.utilidades) a layouts
 * de dashboard ya guardados que aún no lo tienen.
 */
return new class extends Migration
{
    public function up()
    {
        $rows = DB::connection('tenant')->table('dashboard_layouts')->get();

        foreach ($rows as $row) {
            $layout = json_decode($row->layout, true);

            if (!is_array($layout) || !count($layout)) {
                continue;
            }

            $hasUtilidades = false;
            foreach ($layout as $widget) {
                if (is_array($widget) && ($widget['source'] ?? null) === 'finanzas.utilidades') {
                    $hasUtilidades = true;
                    break;
                }
            }

            if ($hasUtilidades) {
                continue;
            }

            $layout[] = [
                'id' => 'w_utilidades_' . $row->id,
                'source' => 'finanzas.utilidades',
                'type' => 'donut',
                'size' => 'l',
                'cols' => 4,
                'rows' => 5,
                'options' => [],
            ];

            DB::connection('tenant')->table('dashboard_layouts')
                ->where('id', $row->id)
                ->update([
                    'layout' => json_encode(array_values($layout)),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down()
    {
        $rows = DB::connection('tenant')->table('dashboard_layouts')->get();

        foreach ($rows as $row) {
            $layout = json_decode($row->layout, true);

            if (!is_array($layout)) {
                continue;
            }

            $filtered = array_values(array_filter($layout, function ($widget) {
                return !is_array($widget) || ($widget['source'] ?? null) !== 'finanzas.utilidades';
            }));

            if (count($filtered) === count($layout)) {
                continue;
            }

            DB::connection('tenant')->table('dashboard_layouts')
                ->where('id', $row->id)
                ->update([
                    'layout' => json_encode($filtered),
                    'updated_at' => now(),
                ]);
        }
    }
};
