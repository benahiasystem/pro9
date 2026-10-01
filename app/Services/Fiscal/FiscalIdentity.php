<?php

namespace App\Services\Fiscal;

use Illuminate\Database\Eloquent\Model;

/** Identifiers belong to the commercial document, without provider reservations. */
final class FiscalIdentity
{
    public static function preload(iterable $rows): void
    {
        // Kept for batch report callers: direct attributes require no extra queries.
    }

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

    public static function forDocument(Model $document): array
    {
        $attributes = $document->getAttributes();
        return [
            'series' => $attributes['series'] ?? '',
            'document_number' => (string) ($attributes['number'] ?? ''),
            'number_full' => self::numberFull($attributes['series'] ?? '', $attributes['number'] ?? ''),
            'control_number' => $attributes['control_number'] ?? null,
            'mode' => $attributes['fiscal_emission_mode'] ?? null,
            'status' => null, 'device_serial' => null, 'original_number_full' => null, 'contingency' => false,
        ];
    }
}
