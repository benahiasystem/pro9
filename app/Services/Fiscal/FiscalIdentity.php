<?php

namespace App\Services\Fiscal;

use Illuminate\Database\Eloquent\Model;

/** Identifiers belong to the commercial document, without provider reservations. */
final class FiscalIdentity
{
    public const EMPTY_SERIES_FILTER = '__without_series__';

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

    /** Presentation only: never use this value as a stored/provider identifier. */
    public static function displayNumber($number): string
    {
        $value = (string) $number;
        return preg_match('/\A[0-9]+\z/', $value)
            ? str_pad($value, 8, '0', STR_PAD_LEFT) : $value;
    }

    public static function numberFull($series, $number): string
    {
        $number = self::displayNumber($number);
        return ($series === null || $series === '') ? $number : $series . '-' . $number;
    }

    /** Format a display reference returned by a report query, without changing its source. */
    public static function displayReference($reference): string
    {
        $value = (string) $reference;
        if (preg_match('/\ASIN_SERIE_S[0-9]+-([0-9]+)(?:-[0-9]{8})?\z/', $value, $legacy)) {
            return self::displayNumber($legacy[1]);
        }
        $parts = self::parseNumberFull($value);
        return $parts === null ? $value : self::numberFull($parts[0], $parts[1]);
    }

    /** The final separator precedes the number; earlier hyphens belong to the series. */
    public static function parseNumberFull(string $reference): ?array
    {
        if (!preg_match('/\A(?:([A-Za-z0-9-]{0,20})-)?([0-9]+)\z/', trim($reference), $parts)) {
            return null;
        }
        return [\App\Services\SeriesNumbering::normalizeCode($parts[1] ?? ''), $parts[2]];
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
