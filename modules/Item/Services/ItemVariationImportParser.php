<?php

namespace Modules\Item\Services;

use Exception;
use Modules\Item\Models\ProductVariable;

/**
 * Parsea la columna Variaciones del Excel de importación.
 *
 * Sintaxis: Talla: S, M, L; Color: Rojo#FF0000, Azul#0000FF
 */
class ItemVariationImportParser
{
    public const MAX_COMBINATIONS = 200;

    /**
     * @return array<int, array{name: string, value_type: string, values: array<int, array{value: string, color: string|null}>}>
     *
     * @throws Exception
     */
    public function parse(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new Exception('La columna Variaciones está vacía');
        }

        $segments = preg_split('/\s*;\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (!$segments) {
            throw new Exception('No se pudo interpretar la columna Variaciones');
        }

        $variables = [];
        $comboCount = 1;

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $parts = explode(':', $segment, 2);
            if (count($parts) < 2) {
                throw new Exception("Formato inválido en \"{$segment}\". Use Nombre: valor1, valor2");
            }

            $name = trim($parts[0]);
            $valuesRaw = trim($parts[1]);

            if ($name === '') {
                throw new Exception('El nombre de la variable no puede estar vacío');
            }
            if ($valuesRaw === '') {
                throw new Exception("La variable \"{$name}\" no tiene valores");
            }

            $valueTokens = preg_split('/\s*,\s*/', $valuesRaw, -1, PREG_SPLIT_NO_EMPTY);
            if (!$valueTokens) {
                throw new Exception("La variable \"{$name}\" no tiene valores válidos");
            }

            $values = [];
            $hasColor = false;

            foreach ($valueTokens as $token) {
                $parsed = $this->parseValueToken(trim($token), $name);
                if ($parsed['color']) {
                    $hasColor = true;
                }
                $values[] = $parsed;
            }

            $valueType = $hasColor
                ? ProductVariable::VALUE_TYPE_COLOR
                : ProductVariable::VALUE_TYPE_LIST;

            if ($valueType === ProductVariable::VALUE_TYPE_COLOR) {
                foreach ($values as $value) {
                    if (!$value['color']) {
                        throw new Exception(
                            "La variable \"{$name}\" es de color: el valor \"{$value['value']}\" debe incluir hex (#RRGGBB)"
                        );
                    }
                }
            }

            $uniqueValues = collect($values)->pluck('value')->map(function ($v) {
                return mb_strtolower($v);
            });
            if ($uniqueValues->count() !== $uniqueValues->unique()->count()) {
                throw new Exception("La variable \"{$name}\" tiene valores duplicados");
            }

            $comboCount *= count($values);
            if ($comboCount > self::MAX_COMBINATIONS) {
                throw new Exception(
                    'Se superarían ' . self::MAX_COMBINATIONS . ' combinaciones. Reduce valores o variables.'
                );
            }

            $variables[] = [
                'name' => $name,
                'value_type' => $valueType,
                'values' => $values,
            ];
        }

        if (count($variables) === 0) {
            throw new Exception('No se encontraron variables en la columna Variaciones');
        }

        return $variables;
    }

    /**
     * @return array{value: string, color: string|null}
     *
     * @throws Exception
     */
    protected function parseValueToken(string $token, string $variableName): array
    {
        if ($token === '') {
            throw new Exception("Valor vacío en la variable \"{$variableName}\"");
        }

        if (preg_match('/^(.+?)\s*(#[0-9A-Fa-f]{6})$/u', $token, $matches)) {
            return [
                'value' => trim($matches[1]),
                'color' => strtoupper($matches[2]),
            ];
        }

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $token)) {
            throw new Exception(
                "El valor \"{$token}\" de \"{$variableName}\" necesita un nombre junto al color (ej. Rojo#FF0000)"
            );
        }

        if (strpos($token, '#') !== false) {
            throw new Exception(
                "Color inválido en \"{$token}\" (variable \"{$variableName}\"). Use #RRGGBB"
            );
        }

        return [
            'value' => $token,
            'color' => null,
        ];
    }
}
