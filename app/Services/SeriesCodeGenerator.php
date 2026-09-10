<?php

namespace App\Services;

use App\Models\Tenant\Series;

/**
 * Codificación estándar de series (ver prompts/series-grupos-dedicados-plan.md §4.4-§4.5).
 *
 * Esquema: prefijo de 2 letras + correlativo. La auto-propuesta (Auto) escanea TODAS las
 * sucursales para el prefijo y propone el siguiente correlativo libre (FF01, FF02, FF03 -> FF04).
 *
 * Se utiliza el mismo catálogo vigente en la siembra y en las nuevas series de la UI.
 */
class SeriesCodeGenerator
{
    private const WAREHOUSE_DOCUMENT_SERIES = [
        'U2' => 'AI',
        'U3' => 'AS',
        'U4' => 'AT',
    ];

    /**
     * Tipos de serie disponibles para empresas NRUS.
     */
    public const NRUS_SERIES_KEYS = ['sale_note'];

    /**
     * Catálogo canónico de tipos de serie con su prefijo y categoría (tabs de la UI).
     * Fuente única para la siembra de tenant, la UI (Etapa 5) y la auto-propuesta.
     *
     * category: basic | advanced | internal
     *
     * @var array<int, array<string, string>>
     */
    public const SERIES_TYPES = [
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        ['key' => 'invoice',             'document_type_id' => '01', 'prefix' => 'FF', 'category' => 'basic',    'label' => 'FACTURA DE VENTA'],
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
        ['key' => 'credit_note_invoice', 'document_type_id' => '07', 'prefix' => 'FC', 'category' => 'basic',    'label' => 'NOTA DE CRÉDITO (factura)'],
        ['key' => 'debit_note_invoice',  'document_type_id' => '08', 'prefix' => 'FD', 'category' => 'basic',    'label' => 'NOTA DE DÉBITO (factura)'],
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        ['key' => 'retention',           'document_type_id' => '20', 'prefix' => 'RR', 'category' => 'advanced', 'label' => 'COMPROBANTE DE RETENCIÓN'],
        ['key' => 'perception',          'document_type_id' => '40', 'prefix' => 'PP', 'category' => 'advanced', 'label' => 'COMPROBANTE DE PERCEPCIÓN'],
        ['key' => 'dispatch_sender',     'document_type_id' => '09', 'prefix' => 'TT', 'category' => 'advanced', 'label' => 'ORDEN DE ENTREGA'],
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
        ['key' => 'purchase_settlement', 'document_type_id' => '04', 'prefix' => 'LL', 'category' => 'advanced', 'label' => 'LIQUIDACIÓN DE COMPRA'],
        ['key' => 'sale_note',           'document_type_id' => '80', 'prefix' => 'NV', 'category' => 'internal', 'label' => 'NOTA DE VENTA'],
        ['key' => 'warehouse_entry',     'document_type_id' => 'U2', 'prefix' => 'AI', 'category' => 'internal', 'label' => 'NOTA DE INGRESO ALMACÉN'],
        ['key' => 'warehouse_exit',      'document_type_id' => 'U3', 'prefix' => 'AS', 'category' => 'internal', 'label' => 'NOTA DE SALIDA ALMACÉN'],
        ['key' => 'warehouse_transfer',  'document_type_id' => 'U4', 'prefix' => 'AT', 'category' => 'internal', 'label' => 'NOTA DE TRANSFERENCIA ALMACÉN'],
    ];

    /**
     * Series que se siembran al crear un tenant.
     * Incluye Factura, notas asociadas, documentos avanzados compatibles,
     * Nota de venta y movimientos internos, sin crear series de Boleta.
     *
     * @param  int  $establishment_id
     * @param  bool $is_nrus
     * @return array<int, array<string, mixed>>
     */
    public static function defaultTenantSeries(int $establishment_id, bool $is_nrus = false): array
    {
        // ########## INICIO CAMBIO QUITAR BOLETAS A CRÉDITO
        $keys = [
            'invoice',
            'credit_note_invoice',
            'debit_note_invoice',
            'retention',
            'dispatch_sender',
            'sale_note',
            'warehouse_entry',
            'warehouse_exit',
            'warehouse_transfer',
        ];
        // ######### FIN CAMBIO QUITAR BOLETAS A CRÉDITO
        $rows = [];

        foreach (self::SERIES_TYPES as $type) {
            if (! in_array($type['key'], $keys, true)) {
                continue;
            }
            $rows[] = [
                'establishment_id' => $establishment_id,
                'document_type_id' => $type['document_type_id'],
                'number'           => $type['prefix'] . '01',
            ];
        }

        return $rows;
    }

    /**
     * Garantiza la serie interna solicitada para el establecimiento indicado.
     */
    public function ensureWarehouseDocumentSeries(int $establishment_id, string $document_type_id): Series
    {
        $prefix = self::WAREHOUSE_DOCUMENT_SERIES[$document_type_id] ?? null;

        if ($prefix === null) {
            throw new \InvalidArgumentException("El tipo de documento {$document_type_id} no corresponde a un movimiento interno de almacén.");
        }

        $existing = Series::query()
            ->where('establishment_id', $establishment_id)
            ->where('document_type_id', $document_type_id)
            ->where('dedicated', false)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Series::query()->create([
            'establishment_id' => $establishment_id,
            'document_type_id' => $document_type_id,
            'number' => $this->nextCode($prefix),
            'contingency' => false,
            'dedicated' => false,
        ]);
    }

    /**
     * Garantiza las series de ingreso (U2), salida (U3) y traslado (U4).
     *
     * @return array<string, Series>
     */
    public function ensureWarehouseInternalSeries(int $establishment_id): array
    {
        $series = [];

        foreach (array_keys(self::WAREHOUSE_DOCUMENT_SERIES) as $document_type_id) {
            $series[$document_type_id] = $this->ensureWarehouseDocumentSeries($establishment_id, $document_type_id);
        }

        return $series;
    }

    /**
     * Compatibilidad con consumidores existentes de la serie de traslado U4.
     */
    public function ensureWarehouseTransferSeries(int $establishment_id): Series
    {
        return $this->ensureWarehouseDocumentSeries($establishment_id, 'U4');
    }

    /**
     * Categoría (basic|advanced|internal) de un tipo de documento.
     *
     * @param  string $document_type_id
     * @return string|null
     */
    public static function categoryForDocumentType(string $document_type_id): ?string
    {
        foreach (self::SERIES_TYPES as $type) {
            if ($type['document_type_id'] === $document_type_id) {
                return $type['category'];
            }
        }

        return null;
    }

    /**
     * Catálogo de tipos permitido según el régimen de la empresa.
     *
     * @param  bool $is_nrus
     * @return array<int, array<string, string>>
     */
    public static function availableTypes(bool $is_nrus = false): array
    {
        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        $availableTypes = self::SERIES_TYPES;

        if (! $is_nrus) {
            return $availableTypes;
        }

        return array_values(array_filter($availableTypes, function ($type) {
            return in_array($type['key'], self::NRUS_SERIES_KEYS, true);
        }));
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
    }

    /**
     * IDs de documento permitidos para empresas NRUS.
     *
     * @return array<int, string>
     */
    public static function nrusDocumentTypeIds(): array
    {
        return array_values(array_unique(array_map(function ($type) {
            return $type['document_type_id'];
        }, self::availableTypes(true))));
    }

    /**
     * Entrada del catálogo que corresponde a una serie, identificada por su prefijo (2 letras).
     * El prefijo y el tipo deben pertenecer a la misma entrada del catálogo vigente.
     *
     * @param  string $number            código de la serie (ej. FF01, FC01).
     * @param  string $document_type_id
     * @return array<string, string>|null
     */
    public static function typeByNumber(string $number, string $document_type_id): ?array
    {
        $prefix = strtoupper(substr($number, 0, 2));

        foreach (self::SERIES_TYPES as $type) {
            if ($type['prefix'] === $prefix && $type['document_type_id'] === $document_type_id) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Siguiente código libre para un prefijo, escaneando TODAS las sucursales (§4.5-bis).
     * Ej: si existen FF01, FF02, FF03 (en cualquier sucursal) -> propone FF04.
     *
     * @param  string $prefix  prefijo de 2 letras (FF, BB, ...).
     * @return string
     */
    public function nextCode(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        $length = strlen($prefix);

        $max = 0;
        $numbers = Series::where('number', 'like', $prefix . '%')->pluck('number');

        foreach ($numbers as $number) {
            $suffix = substr($number, $length);
            if ($suffix !== '' && ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        // Mantener 4 caracteres totales (prefijo 2 + correlativo 2) cuando el prefijo sea de 2 letras.
        $pad = max(2, 4 - $length);

        return $prefix . str_pad((string) ($max + 1), $pad, '0', STR_PAD_LEFT);
    }
}
