<?php

namespace App\Support\Venezuela;

final class IdentityDocument
{
    public const TYPES = [
        ['id' => '0', 'active' => 1, 'description' => 'Doc.sin.rif', 'code' => null],
        ['id' => '1', 'active' => 1, 'description' => 'Venezolano', 'code' => 'V'],
        ['id' => '6', 'active' => 1, 'description' => 'Juridico', 'code' => 'J'],
        ['id' => '7', 'active' => 1, 'description' => 'Pasaporte', 'code' => 'P'],
        // ######## INICIO CATÁLOGO IDENTIDADES ACTIVAS VENEZUELA ########
        ['id' => 'E', 'active' => 1, 'description' => 'Extranjero', 'code' => 'E'],
        ['id' => 'C', 'active' => 1, 'description' => 'Comuna', 'code' => 'C'],
        ['id' => 'G', 'active' => 1, 'description' => 'Gubernamental', 'code' => 'G'],
        ['id' => 'R', 'active' => 1, 'description' => 'Firma Personal', 'code' => 'R'],
        // ######## FIN CATÁLOGO IDENTIDADES ACTIVAS VENEZUELA ########
    ];

    public static function ids(): array
    {
        return array_column(self::TYPES, 'id');
    }

    // ######## INICIO POLITICA IDENTIDAD ACTIVA EN VENTAS ########
    public static function activeIds(): array
    {
        return array_values(array_map(
            static fn (array $type): string => $type['id'],
            array_filter(self::TYPES, static fn (array $type): bool => (int) $type['active'] === 1)
        ));
    }
    // ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########

    public static function definition($id): ?array
    {
        $id = (string) $id;

        foreach (self::TYPES as $type) {
            if ($type['id'] === $id) {
                return $type;
            }
        }

        return null;
    }

    public static function code($id): ?string
    {
        $definition = self::definition($id);

        return $definition['code'] ?? null;
    }

    public static function selectionLabel($id, ?string $description = null): string
    {
        $definition = self::definition($id);

        return trim((string) ($definition['description'] ?? $description ?? ''));
    }

    public static function normalizeNumber($id, $number): string
    {
        $number = strtoupper(trim((string) $number));
        $code = self::code($id);

        if ($code && preg_match('/^'.preg_quote($code, '/').'\s*-?\s*(.+)$/i', $number, $matches)) {
            $number = trim($matches[1]);
        }

        return $number;
    }

    public static function format($id, $number): string
    {
        $number = self::normalizeNumber($id, $number);
        $code = self::code($id);

        if ($number === '' || !$code) {
            return $number;
        }

        return $code.'-'.$number;
    }
}
