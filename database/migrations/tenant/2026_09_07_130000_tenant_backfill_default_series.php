<?php

use App\Models\Tenant\Configuration;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Series;
use App\Services\SeriesCodeGenerator;
use Illuminate\Database\Migrations\Migration;

/**
 * Completa series por defecto faltantes en tenants ya creados
 * (guías remitente/transportista, retención, percepción, liquidación, almacén, etc.).
 * No sobrescribe ni duplica si el establecimiento ya tiene serie de ese tipo
 * (o del mismo prefijo en NC/ND).
 */
class TenantBackfillDefaultSeries extends Migration
{
    /**
     * Tipos con más de una serie (prefijos distintos).
     */
    private const MULTI_PREFIX_TYPES = ['07', '08'];

    public function up()
    {
        $is_nrus = false;

        if (class_exists(Configuration::class)) {
            $configuration = Configuration::query()->first();
            if ($configuration && method_exists($configuration, 'isNrus')) {
                $is_nrus = (bool) $configuration->isNrus();
            } elseif ($configuration && ($configuration->template_ticket_pdf ?? null) === 'nrus') {
                $is_nrus = true;
            }
        }

        $establishments = Establishment::query()->get(['id']);

        foreach ($establishments as $establishment) {
            $defaults = SeriesCodeGenerator::defaultTenantSeries((int) $establishment->id, $is_nrus);

            foreach ($defaults as $row) {
                if ($this->seriesAlreadyPresent($row)) {
                    continue;
                }

                Series::query()->create([
                    'establishment_id' => $row['establishment_id'],
                    'document_type_id' => $row['document_type_id'],
                    'number' => $row['number'],
                ]);
            }
        }
    }

    private function seriesAlreadyPresent(array $row): bool
    {
        $query = Series::query()
            ->where('establishment_id', $row['establishment_id'])
            ->where('document_type_id', $row['document_type_id']);

        if (in_array($row['document_type_id'], self::MULTI_PREFIX_TYPES, true)) {
            $prefix = substr($row['number'], 0, 2);

            return $query->where('number', 'like', $prefix . '%')->exists();
        }

        // Un tipo = una serie por sucursal (ej. 09, 31, 20…). Si ya hay T001, no crear TT01.
        return $query->exists();
    }

    public function down()
    {
        // No elimina series: podrían haberse usado o creado manualmente.
    }
}
