<?php

// ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
namespace App\Support\ItemImport;

use App\Traits\SunatItemCodeTrait;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class ItemImportContract
{
    use SunatItemCodeTrait;

    public const CORE_COLUMN_COUNT = 20;
    public const OPTIONAL_IMAGE_COLUMN_INDEX = 20;
    public const OPTIONAL_IMAGE_HEADER = 'URL Imagen';

    public const HEADERS = [
        'Nombre',
        'Código Interno',
        'Modelo',
        'Código',
        'Código Tipo de Unidad',
        'Código Tipo de Moneda',
        'Precio Unitario Venta',
        'Codigo Tipo de Afectación del Iva Venta',
        'Tiene Iva',
        'Precio Unitario Compra',
        'Codigo Tipo de Afectación del Iva Compra',
        'Stock',
        'Stock Mínimo',
        'Categoria',
        'Marca',
        'Descripcion',
        'Nombre secundario',
        'Código lote',
        'Fec. Vencimiento',
        'Cód barras',
    ];

    public function validateHeaders(array $headers): array
    {
        $errors = [];

        foreach (self::HEADERS as $index => $expected) {
            $actual = $this->textValue($headers[$index] ?? null);

            if ($actual !== $expected) {
                $errors[] = $this->error(
                    1,
                    $index + 1,
                    sprintf('La cabecera debe ser "%s" y conservar su posición.', $expected)
                );
            }
        }

        $optionalHeader = $this->textValue($headers[self::OPTIONAL_IMAGE_COLUMN_INDEX] ?? null);

        if ($optionalHeader !== '' && $optionalHeader !== self::OPTIONAL_IMAGE_HEADER) {
            $errors[] = $this->error(
                1,
                self::OPTIONAL_IMAGE_COLUMN_INDEX + 1,
                sprintf('La columna opcional debe llamarse "%s".', self::OPTIONAL_IMAGE_HEADER)
            );
        }

        return $errors;
    }

    public function validateRow(
        array $row,
        int $rowNumber,
        array $catalogs,
        bool $updatesExistingItem = false
    ): array {
        $errors = [];

        $this->validateRequiredText($errors, $row, $rowNumber, 0, 600);
        $this->validateNullableText($errors, $row, $rowNumber, 1, 30);
        $this->validateNullableText($errors, $row, $rowNumber, 2, 100);
        $this->validateItemCode($errors, $row, $rowNumber, 3);
        $this->validateCatalog($errors, $row, $rowNumber, 4, $catalogs['unit_type_ids'] ?? []);
        $this->validateCatalog($errors, $row, $rowNumber, 5, $catalogs['currency_type_ids'] ?? []);
        $this->validateDecimal($errors, $row, $rowNumber, 6, 16, 6, true, 0, true);
        $this->validateCatalog($errors, $row, $rowNumber, 7, $catalogs['affectation_igv_type_ids'] ?? []);
        $this->validateBooleanText($errors, $row, $rowNumber, 8);
        $this->validateDecimal($errors, $row, $rowNumber, 9, 16, 6, false, 0);
        $this->validateCatalog($errors, $row, $rowNumber, 10, $catalogs['affectation_igv_type_ids'] ?? []);
        $this->validateDecimal($errors, $row, $rowNumber, 11, 16, 4, true, 0, $updatesExistingItem);
        $this->validateDecimal($errors, $row, $rowNumber, 12, 12, 2, true, 0);
        $this->validateNullableText($errors, $row, $rowNumber, 13, 255);
        $this->validateNullableText($errors, $row, $rowNumber, 14, 255);
        $this->validateNullableText($errors, $row, $rowNumber, 15, 1000);
        $this->validateNullableText($errors, $row, $rowNumber, 16, 600);
        $this->validateNullableText($errors, $row, $rowNumber, 17, 255);
        $this->validateExcelDate($errors, $row, $rowNumber, 18, !$this->isEmpty($row[17] ?? null));
        $this->validateNullableText($errors, $row, $rowNumber, 19, 150);
        $this->validateImageUrl($errors, $row, $rowNumber, self::OPTIONAL_IMAGE_COLUMN_INDEX);

        return $errors;
    }

    public function isEmpty($value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    public function textValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function validateRequiredText(array &$errors, array $row, int $rowNumber, int $index, int $max): void
    {
        if ($this->isEmpty($row[$index] ?? null)) {
            $errors[] = $this->error($rowNumber, $index + 1, 'El valor es obligatorio.');
            return;
        }

        $this->validateNullableText($errors, $row, $rowNumber, $index, $max);
    }

    private function validateNullableText(array &$errors, array $row, int $rowNumber, int $index, int $max): void
    {
        $value = $row[$index] ?? null;

        if ($this->isEmpty($value)) {
            return;
        }

        if (!is_scalar($value) || is_bool($value)) {
            $errors[] = $this->error($rowNumber, $index + 1, 'El valor debe ser texto compatible con la base de datos.');
            return;
        }

        if (mb_strlen(trim((string) $value)) > $max) {
            $errors[] = $this->error(
                $rowNumber,
                $index + 1,
                sprintf('El valor excede el máximo de %d caracteres.', $max)
            );
        }
    }

    private function validateItemCode(array &$errors, array $row, int $rowNumber, int $index): void
    {
        $value = $row[$index] ?? null;

        if ($this->isEmpty($value)) {
            return;
        }

        if (!is_scalar($value) || is_bool($value)) {
            $errors[] = $this->error(
                $rowNumber,
                $index + 1,
                'El valor debe ser texto compatible con la base de datos.'
            );
            return;
        }

        $value = self::cleanSunatItemCode($value);

        if ($value !== null && !self::isValidSunatItemCode($value)) {
            $errors[] = $this->error($rowNumber, $index + 1, self::getSunatItemCodeMessage());
        }
    }

    private function validateCatalog(
        array &$errors,
        array $row,
        int $rowNumber,
        int $index,
        array $allowedValues
    ): void {
        $value = $this->textValue($row[$index] ?? null);

        if ($value === '') {
            $errors[] = $this->error($rowNumber, $index + 1, 'El identificador de catálogo es obligatorio.');
            return;
        }

        if (!in_array($value, array_map('strval', $allowedValues), true)) {
            $errors[] = $this->error(
                $rowNumber,
                $index + 1,
                sprintf('El identificador "%s" no existe o no está activo en el catálogo.', $value)
            );
        }
    }

    private function validateBooleanText(array &$errors, array $row, int $rowNumber, int $index): void
    {
        $value = strtoupper($this->textValue($row[$index] ?? null));

        if (!in_array($value, ['SI', 'NO'], true)) {
            $errors[] = $this->error($rowNumber, $index + 1, 'El valor debe ser SI o NO.');
        }
    }

    private function validateDecimal(
        array &$errors,
        array $row,
        int $rowNumber,
        int $index,
        int $precision,
        int $scale,
        bool $required,
        $minimum,
        bool $strictlyGreater = false
    ): void {
        $value = $row[$index] ?? null;

        if ($this->isEmpty($value)) {
            if ($required) {
                $errors[] = $this->error($rowNumber, $index + 1, 'El valor numérico es obligatorio.');
            }
            return;
        }

        $normalized = $this->normalizeNumericString($value);

        if ($normalized === null) {
            $errors[] = $this->error(
                $rowNumber,
                $index + 1,
                'El valor debe ser numérico y usar punto como separador decimal.'
            );
            return;
        }

        $absolute = ltrim($normalized, '+-');
        $parts = explode('.', $absolute, 2);
        $integerDigits = strlen(ltrim($parts[0], '0')) ?: 1;
        $decimalDigits = isset($parts[1]) ? strlen(rtrim($parts[1], '0')) : 0;

        if ($integerDigits > ($precision - $scale) || $decimalDigits > $scale) {
            $errors[] = $this->error(
                $rowNumber,
                $index + 1,
                sprintf('El valor no cabe en decimal(%d,%d) de la base de datos.', $precision, $scale)
            );
        }

        $numeric = (float) $normalized;
        $isBelowMinimum = $strictlyGreater ? $numeric <= (float) $minimum : $numeric < (float) $minimum;

        if ($isBelowMinimum) {
            $comparator = $strictlyGreater ? 'mayor que' : 'mayor o igual que';
            $errors[] = $this->error(
                $rowNumber,
                $index + 1,
                sprintf('El valor debe ser %s %s.', $comparator, $minimum)
            );
        }
    }

    private function normalizeNumericString($value)
    {
        if (is_bool($value) || !is_scalar($value)) {
            return null;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                return null;
            }

            return rtrim(rtrim(sprintf('%.14F', $value), '0'), '.');
        }

        $value = trim((string) $value);

        return preg_match('/^[+-]?\d+(?:\.\d+)?$/D', $value) === 1 ? $value : null;
    }

    private function validateExcelDate(
        array &$errors,
        array $row,
        int $rowNumber,
        int $index,
        bool $required
    ): void {
        $value = $row[$index] ?? null;

        if ($this->isEmpty($value)) {
            if ($required) {
                $errors[] = $this->error(
                    $rowNumber,
                    $index + 1,
                    'La fecha de vencimiento es obligatoria cuando se informa un lote.'
                );
            }
            return;
        }

        $normalized = $this->normalizeNumericString($value);

        if ($normalized === null || (float) $normalized <= 0) {
            $errors[] = $this->error(
                $rowNumber,
                $index + 1,
                'La fecha debe ser una celda de fecha válida de Excel, no texto.'
            );
            return;
        }

        try {
            Date::excelToDateTimeObject((float) $normalized);
        } catch (Throwable $exception) {
            $errors[] = $this->error($rowNumber, $index + 1, 'La fecha de Excel no es válida.');
        }
    }

    private function validateImageUrl(array &$errors, array $row, int $rowNumber, int $index): void
    {
        $value = $this->textValue($row[$index] ?? null);

        if ($value === '') {
            return;
        }

        if (mb_strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $errors[] = $this->error($rowNumber, $index + 1, 'La URL de imagen no es válida.');
            return;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            $errors[] = $this->error($rowNumber, $index + 1, 'La URL de imagen debe usar HTTP o HTTPS.');
        }
    }

    private function error(int $row, int $column, string $message): array
    {
        return [
            'row' => $row,
            'column' => $column,
            'messages' => [$message],
        ];
    }
}
// ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
