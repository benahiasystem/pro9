<?php

namespace Modules\Item\Services;

use Illuminate\Support\Collection;
use Modules\Item\Models\ProductVariable;

/**
 * Interpreta el Excel de importación de atributos (catálogo).
 *
 * No vincula productos: solo agrupa nombre + tipo + valores.
 *
 * Columnas: Nombre | Tipo | Valor | Color | Orden | Activo
 */
class ProductVariableCatalogParser
{
    public const HEADINGS = ['Nombre', 'Tipo', 'Valor', 'Color', 'Orden', 'Activo'];

    public const COLOR_NAMES = [
        'rojo' => '#E53935',
        'rojo oscuro' => '#8E1414',
        'vino' => '#6D1B2E',
        'guinda' => '#6D1B2E',
        'granate' => '#7B1E1E',
        'coral' => '#FF7F50',
        'salmon' => '#FA8072',
        'naranja' => '#FB8C00',
        'anaranjado' => '#FB8C00',
        'mandarina' => '#F4711F',
        'durazno' => '#FFCBA4',
        'amarillo' => '#FDD835',
        'mostaza' => '#D4A017',
        'dorado' => '#D4AF37',
        'oro' => '#D4AF37',
        'ocre' => '#CC7722',
        'verde' => '#43A047',
        'verde claro' => '#81C784',
        'verde oscuro' => '#1B5E20',
        'verde limon' => '#B2D732',
        'limon' => '#B2D732',
        'menta' => '#98E2C6',
        'oliva' => '#808000',
        'esmeralda' => '#0F9D58',
        'turquesa' => '#1ABC9C',
        'aqua' => '#00FFFF',
        'cian' => '#00BCD4',
        'celeste' => '#4FC3F7',
        'azul' => '#1E88E5',
        'azul claro' => '#64B5F6',
        'azul oscuro' => '#0D47A1',
        'azul marino' => '#1A237E',
        'marino' => '#1A237E',
        'indigo' => '#3F51B5',
        'morado' => '#8E24AA',
        'purpura' => '#6A1B9A',
        'violeta' => '#7B1FA2',
        'lila' => '#B39DDB',
        'lavanda' => '#B57EDC',
        'magenta' => '#D81B60',
        'fucsia' => '#E91E8C',
        'rosado' => '#F06292',
        'rosa' => '#F06292',
        'palo rosa' => '#E8B4B8',
        'marron' => '#795548',
        'cafe' => '#795548',
        'chocolate' => '#5D4037',
        'terracota' => '#C56E4E',
        'camel' => '#C19A6B',
        'beige' => '#E8DCC4',
        'crema' => '#F3E9D2',
        'arena' => '#D9CBA3',
        'khaki' => '#C3B091',
        'caqui' => '#C3B091',
        'hueso' => '#F2EDE4',
        'blanco' => '#FFFFFF',
        'gris' => '#9E9E9E',
        'gris claro' => '#D5D8DC',
        'gris oscuro' => '#4F4F4F',
        'plateado' => '#C0C0C0',
        'plata' => '#C0C0C0',
        'negro' => '#111111',
    ];

    protected const HEADER_ALIASES = [
        'nombre' => 'nombre',
        'name' => 'nombre',
        'atributo' => 'nombre',
        'variable' => 'nombre',
        'nombre de la variable' => 'nombre',
        'tipo' => 'tipo',
        'type' => 'tipo',
        'tipo de valor' => 'tipo',
        'valor' => 'valor',
        'value' => 'valor',
        'valores' => 'valor',
        'color' => 'color',
        'hex' => 'color',
        'color hex' => 'color',
        'orden' => 'orden',
        'order' => 'orden',
        'position' => 'orden',
        'posicion' => 'orden',
        'activo' => 'activo',
        'active' => 'activo',
        'estado' => 'activo',
    ];

    /**
     * @return array{groups: array<int, array>, errors: array<int, string>, total: int}
     */
    public function parse(Collection $rows): array
    {
        $errors = [];
        $total = 0;
        $columnMap = [
            'nombre' => 0,
            'tipo' => 1,
            'valor' => 2,
            'color' => 3,
            'orden' => 4,
            'activo' => 5,
        ];

        $startIndex = 0;
        if ($rows->count() > 0) {
            $first = $rows->first();
            $detected = $this->detectColumnMap($first);
            if ($detected !== null) {
                $columnMap = $detected;
                $startIndex = 1;
            }
        }

        $rawGroups = [];

        foreach ($rows as $index => $row) {
            if ($index < $startIndex) {
                continue;
            }

            $excelRow = $index + 1;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $total++;

            $nombre = $this->cellString($this->cell($row, $columnMap['nombre']));
            $tipoRaw = $this->cellString($this->cell($row, $columnMap['tipo']));
            $valorRaw = $this->cellString($this->cell($row, $columnMap['valor']));
            $colorRaw = $this->cellString($this->cell($row, $columnMap['color']));
            $ordenRaw = $this->cellString($this->cell($row, $columnMap['orden']));
            $activoRaw = $this->cellString($this->cell($row, $columnMap['activo']));

            if ($nombre === '') {
                $errors[] = "Fila {$excelRow}: el nombre del atributo es obligatorio";
                continue;
            }
            if (mb_strlen($nombre) > 100) {
                $errors[] = "Fila {$excelRow}: el nombre \"{$nombre}\" supera 100 caracteres";
                continue;
            }
            if ($valorRaw === '') {
                $errors[] = "Fila {$excelRow}: el valor es obligatorio";
                continue;
            }

            $tipo = null;
            if ($tipoRaw !== '') {
                $tipo = $this->parseTipo($tipoRaw);
                if ($tipo === null) {
                    $errors[] = "Fila {$excelRow}: tipo inválido \"{$tipoRaw}\". Use lista o color";
                    continue;
                }
            }

            $activo = true;
            if ($activoRaw !== '') {
                $parsedActivo = $this->parseActivo($activoRaw);
                if ($parsedActivo === null) {
                    $errors[] = "Fila {$excelRow}: activo inválido \"{$activoRaw}\". Use 1, 0, sí o no";
                    continue;
                }
                $activo = $parsedActivo;
            }

            $orden = null;
            if ($ordenRaw !== '') {
                if (!preg_match('/^-?\d+$/', $ordenRaw)) {
                    $errors[] = "Fila {$excelRow}: orden inválido \"{$ordenRaw}\"";
                    continue;
                }
                $orden = (int) $ordenRaw;
            }

            $color = null;
            if ($colorRaw !== '') {
                $color = $this->normalizeHex($colorRaw);
                if ($color === null) {
                    $errors[] = "Fila {$excelRow}: color inválido \"{$colorRaw}\". Use #RRGGBB";
                    continue;
                }
            }

            $key = mb_strtolower($nombre);
            if (!isset($rawGroups[$key])) {
                $rawGroups[$key] = [
                    'name' => $nombre,
                    'tipos' => [],
                    'rows' => [],
                ];
            }

            $rawGroups[$key]['rows'][] = [
                'excel_row' => $excelRow,
                'tipo' => $tipo,
                'valor' => $valorRaw,
                'color' => $color,
                'orden' => $orden,
                'activo' => $activo,
            ];
            if ($tipo !== null) {
                $rawGroups[$key]['tipos'][$tipo] = $excelRow;
            }
        }

        $groups = [];
        foreach ($rawGroups as $group) {
            $resolved = $this->resolveGroup($group, $errors);
            if ($resolved !== null) {
                $groups[] = $resolved;
            }
        }

        return [
            'groups' => $groups,
            'errors' => $errors,
            'total' => $total,
        ];
    }

    /**
     * @param array $group
     * @param array<int, string> $errors
     * @return array|null
     */
    protected function resolveGroup(array $group, array &$errors): ?array
    {
        $explicitTipos = array_keys($group['tipos']);
        if (count($explicitTipos) > 1) {
            $errors[] = "El atributo \"{$group['name']}\" tiene tipos mezclados (lista y color)";
            return null;
        }

        $values = [];
        $seen = [];
        $hasColorHint = false;
        $nextOrden = 0;

        foreach ($group['rows'] as $row) {
            $tokens = $this->splitValues($row['valor']);
            if (count($tokens) === 0) {
                $errors[] = "Fila {$row['excel_row']}: el valor es obligatorio";
                continue;
            }

            if (count($tokens) > 1 && $row['color']) {
                $errors[] = "Fila {$row['excel_row']}: si hay varios valores, indica el color en cada uno (ej. Rojo#FF0000)";
                continue;
            }

            $ordenBase = $row['orden'] !== null ? $row['orden'] : $nextOrden;

            foreach ($tokens as $offset => $token) {
                $parsed = $this->parseValueToken($token, $group['name']);
                if (isset($parsed['error'])) {
                    $errors[] = "Fila {$row['excel_row']}: {$parsed['error']}";
                    continue;
                }

                $color = $parsed['color'] ?: $row['color'];
                if (!$color) {
                    $color = $this->colorFromName($parsed['value']);
                }
                if ($color) {
                    $hasColorHint = true;
                }

                if (mb_strlen($parsed['value']) > 100) {
                    $errors[] = "Fila {$row['excel_row']}: el valor \"{$parsed['value']}\" supera 100 caracteres";
                    continue;
                }

                $lookup = mb_strtolower($parsed['value']);
                if (isset($seen[$lookup])) {
                    $errors[] = "Fila {$row['excel_row']}: el valor \"{$parsed['value']}\" está duplicado en \"{$group['name']}\"";
                    continue;
                }
                $seen[$lookup] = true;

                $position = $ordenBase + $offset;
                $nextOrden = max($nextOrden, $position + 1);

                $values[] = [
                    'value' => $parsed['value'],
                    'color' => $color,
                    'position' => $position,
                    'active' => $row['activo'],
                    'excel_row' => $row['excel_row'],
                ];
            }
        }

        if (count($values) === 0) {
            return null;
        }

        $valueType = $explicitTipos[0] ?? ($hasColorHint
            ? ProductVariable::VALUE_TYPE_COLOR
            : ProductVariable::VALUE_TYPE_LIST);

        if ($valueType === ProductVariable::VALUE_TYPE_COLOR) {
            $valid = [];
            foreach ($values as $value) {
                if (!$value['color']) {
                    $errors[] = "Fila {$value['excel_row']}: el valor \"{$value['value']}\" de \"{$group['name']}\" necesita color (#RRGGBB)";
                    continue;
                }
                $valid[] = $value;
            }
            $values = $valid;
            if (count($values) === 0) {
                return null;
            }
        } else {
            foreach ($values as &$value) {
                $value['color'] = null;
            }
            unset($value);
        }

        return [
            'name' => $group['name'],
            'value_type' => $valueType,
            'values' => $values,
        ];
    }

    /**
     * @param mixed $headerRow
     * @return array<string, int>|null
     */
    public function detectColumnMap($headerRow): ?array
    {
        $map = [
            'nombre' => null,
            'tipo' => null,
            'valor' => null,
            'color' => null,
            'orden' => null,
            'activo' => null,
        ];

        foreach ($headerRow as $index => $cell) {
            $normalized = $this->normalizeHeader($this->cellString($cell));
            if ($normalized === '' || !isset(self::HEADER_ALIASES[$normalized])) {
                continue;
            }
            $field = self::HEADER_ALIASES[$normalized];
            if ($map[$field] === null) {
                $map[$field] = (int) $index;
            }
        }

        if ($map['nombre'] === null || $map['valor'] === null) {
            return null;
        }

        foreach ($map as $field => $index) {
            if ($index === null) {
                $defaults = [
                    'nombre' => 0,
                    'tipo' => 1,
                    'valor' => 2,
                    'color' => 3,
                    'orden' => 4,
                    'activo' => 5,
                ];
                $map[$field] = $defaults[$field];
            }
        }

        return $map;
    }

    public function parseTipo(string $raw): ?string
    {
        $key = $this->normalizeHeader($raw);
        if (in_array($key, ['lista', 'list', 'lista simple', 'simple'], true)) {
            return ProductVariable::VALUE_TYPE_LIST;
        }
        if (in_array($key, ['color', 'lista con color', 'con color'], true)) {
            return ProductVariable::VALUE_TYPE_COLOR;
        }

        return null;
    }

    public function parseActivo(string $raw): ?bool
    {
        $key = $this->normalizeHeader($raw);
        if (in_array($key, ['1', 'si', 'sí', 'true', 'activo', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($key, ['0', 'no', 'false', 'inactivo', 'off'], true)) {
            return false;
        }

        return null;
    }

    public function colorFromName(string $name): ?string
    {
        $key = $this->normalizeColorKey($name);

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $key)) {
            return $this->normalizeHex($key);
        }

        return self::COLOR_NAMES[$key]
            ?? self::COLOR_NAMES[preg_replace('/s$/', '', $key)]
            ?? null;
    }

    public function normalizeHex(string $raw): ?string
    {
        $value = strtoupper(trim($raw));
        if (preg_match('/^#([0-9A-F]{6})$/', $value, $matches)) {
            return '#' . $matches[1];
        }
        if (preg_match('/^#([0-9A-F]{3})$/', $value, $matches)) {
            $hex = $matches[1];
            return '#' . $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function splitValues(string $raw): array
    {
        $tokens = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);

        return $tokens ? array_values(array_filter(array_map('trim', $tokens))) : [];
    }

    /**
     * @return array{value?: string, color?: string|null, error?: string}
     */
    public function parseValueToken(string $token, string $variableName): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['error' => "valor vacío en \"{$variableName}\""];
        }

        if (preg_match('/^(.+?)\s*(#[0-9A-Fa-f]{6})$/u', $token, $matches)) {
            return [
                'value' => trim($matches[1]),
                'color' => strtoupper($matches[2]),
            ];
        }

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $token)) {
            return [
                'error' => "el valor \"{$token}\" necesita un nombre junto al color (ej. Rojo#FF0000)",
            ];
        }

        if (strpos($token, '#') !== false) {
            return [
                'error' => "color inválido en \"{$token}\". Use #RRGGBB",
            ];
        }

        return [
            'value' => $token,
            'color' => null,
        ];
    }

    public function normalizeHeader(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    protected function normalizeColorKey(string $name): string
    {
        $key = $name;
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($name, \Normalizer::FORM_D);
            if ($normalized !== false) {
                $key = $normalized;
            }
        }
        $key = preg_replace('/\p{Mn}/u', '', $key) ?? $key;
        $key = mb_strtolower($key);
        $key = strtr($key, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);
        $key = preg_replace('/[^a-z0-9 #]/', '', $key) ?? $key;

        return trim(preg_replace('/\s+/', ' ', $key) ?? $key);
    }

    /**
     * @param mixed $row
     */
    protected function isEmptyRow($row): bool
    {
        foreach ($row as $cell) {
            if ($this->cellString($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $row
     * @return mixed
     */
    protected function cell($row, int $index)
    {
        if ($row instanceof Collection) {
            return $row->get($index);
        }

        return $row[$index] ?? null;
    }

    /**
     * @param mixed $value
     */
    public function cellString($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (floor($value) == $value) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
        }

        return trim((string) $value);
    }
}
