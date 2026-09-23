<?php

namespace App\Services\Fiscal;

use Illuminate\Database\Eloquent\Model;
use App\Models\Tenant\FiscalNumberReservation;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalIdentity
{
    public static function preload(iterable $rows): void
    {
        $groups = [];
        foreach ($rows as $row) {
            $model = $row instanceof \Illuminate\Http\Resources\Json\JsonResource ? $row->resource : $row;
            if (!$model instanceof Model || !method_exists($model, 'fiscalReservation')) continue;
            $key = $model->getConnectionName() . ':' . $model->getTable();
            $groups[$key][] = $model;
        }
        foreach ($groups as $models) {
            $models = new \Illuminate\Database\Eloquent\Collection($models);
            $models->loadMissing('fiscalReservation');
            self::preloadReplacementChains($models->pluck('fiscalReservation')->filter()->values());
        }
    }

    /** Derive report groups from its records, including documents without a series. */
    public static function seriesForType(iterable $records, string $type): \Illuminate\Support\Collection
    {
        $series = [];
        foreach ($records as $record) {
            if ((string) $record->getDocumentType()->id !== $type) continue;
            $code = self::forDocument($record)['series'];
            if (!in_array($code, $series, true)) $series[] = $code;
        }
        return collect($series)->map(fn ($code) => ['number' => $code]);
    }

    public static function numberFull($series, $number): string
    {
        return ($series === null || $series === '') ? (string) $number : $series . '-' . $number;
    }

    /** Public presentation only: never changes the commercial identifiers stored on the model. */
    public static function forDocument(Model $document): array
    {
        $attributes = $document->getAttributes();
        $fallback = ['series' => $attributes['series'] ?? '', 'document_number' => (string) ($attributes['number'] ?? ''),
            'number_full' => self::numberFull($attributes['series'] ?? '', $attributes['number'] ?? ''),
            'control_number' => null, 'device_serial' => null, 'status' => null,
            'mode' => $attributes['fiscal_emission_mode'] ?? null, 'original_number_full' => null, 'contingency' => false];
        if (!$document->exists || !method_exists($document, 'fiscalReservation')) return $fallback;
        // Batch consumers preload this relationship. Unloaded single records are read fresh.
        $original = $document->relationLoaded('fiscalReservation') ? $document->getRelation('fiscalReservation') : $document->fiscalReservation()->first();
        if (!$original) return $fallback;
        $record = self::effective($original);
        $snapshot = json_decode($record->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $result = json_decode($record->provider_result ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        $source = json_decode($original->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
        return ['series' => $snapshot['series'], 'document_number' => (string) $record->document_number,
            'number_full' => self::numberFull($snapshot['series'], $record->document_number),
            'control_number' => $record->control_number, 'device_serial' => $result['device_serial'] ?? null,
            'status' => $record->status, 'mode' => $snapshot['mode'] ?? null,
            'original_number_full' => $record->id !== $original->id ? self::numberFull($source['series'], $original->document_number) : null,
            'contingency' => $original->status === 'contingency'];
    }

    /** Resolve matching effective leaves to their commercial root reservations for list filters. */
    public static function matchingRootIds(callable $match): array
    {
        $query = FiscalNumberReservation::query()->whereDoesntHave('replacement');
        $match($query);
        $current = $query->get(['id', 'parent_reservation_id']);
        $roots = [];
        $visited = [];
        for ($depth = 0; $current->isNotEmpty(); $depth++) {
            if ($depth >= 100) throw new \DomainException('La cadena de reemplazos fiscales es demasiado extensa.');
            $parentIds = [];
            foreach ($current as $reservation) {
                if (isset($visited[$reservation->id])) throw new \DomainException('La cadena de reemplazos fiscales es inválida.');
                $visited[$reservation->id] = true;
                if ($reservation->parent_reservation_id) $parentIds[] = (int) $reservation->parent_reservation_id;
                else $roots[] = (int) $reservation->id;
            }
            if (!$parentIds) break;
            $current = FiscalNumberReservation::query()->whereIn('id', array_values(array_unique($parentIds)))
                ->get(['id', 'parent_reservation_id']);
            if ($current->count() !== count(array_unique($parentIds))) {
                throw new \DomainException('La cadena de reemplazos fiscales está incompleta.');
            }
        }
        return array_values(array_unique($roots));
    }

    private static function preloadReplacementChains(\Illuminate\Support\Collection $current): void
    {
        $visited = [];
        for ($depth = 0; $current->isNotEmpty(); $depth++) {
            if ($depth >= 100) throw new \DomainException('La cadena de reemplazos fiscales es demasiado extensa.');
            $ids = $current->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($ids as $id) {
                if (isset($visited[$id])) throw new \DomainException('La cadena de reemplazos fiscales es inválida.');
                $visited[$id] = true;
            }
            $children = FiscalNumberReservation::query()->whereIn('parent_reservation_id', $ids)->get()->keyBy('parent_reservation_id');
            foreach ($current as $reservation) {
                $reservation->setRelation('replacement', $children->get($reservation->id));
            }
            $current = $children->values();
        }
    }

    private static function effective(FiscalNumberReservation $original): FiscalNumberReservation
    {
        $current = $original;
        $visited = [];
        for ($depth = 0; $depth < 100; $depth++) {
            if (isset($visited[$current->id])) throw new \DomainException('La cadena de reemplazos fiscales es inválida.');
            $visited[$current->id] = true;
            if (!in_array($current->status, ['contingency', 'inutilized'], true)) return $current;
            $replacement = $current->relationLoaded('replacement')
                ? $current->getRelation('replacement')
                : $current->replacement()->first();
            if (!$replacement) {
                if ($current->status === 'contingency') throw new \DomainException('Falta la reserva física de contingencia.');
                return $current;
            }
            $current = $replacement;
        }
        throw new \DomainException('La cadena de reemplazos fiscales es demasiado extensa.');
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
