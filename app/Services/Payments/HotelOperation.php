<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Hotel\Models\HotelRentOrder;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Idempotent retry of a hotel request after an uncertain HTTP response. */
final class HotelOperation
{
    public static function identify(Request $request, string $operation, int $target): array
    {
        $key = $request->input('operation_key');
        if ($key === null || $key === '') return [null, null];
        if (!is_string($key) || !Str::isUuid($key)) Classification::error('operation_key', 'Use un identificador de operación válido.');
        $data = $request->all();
        unset($data['operation_key']);
        return [$key, hash('sha256', json_encode([$operation, $target, self::ordered($data)], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION))];
    }

    public static function existing(?string $key, ?string $hash): ?HotelRentOrder
    {
        if (!$key) return null;
        $order = HotelRentOrder::query()->where('operation_key', $key)->first();
        if ($order && !hash_equals((string)$order->operation_hash, (string)$hash)) {
            Classification::error('operation_key', 'Esta operación ya fue registrada con otros datos.');
        }
        return $order;
    }

    private static function ordered($value)
    {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        foreach ($value as $key => $item) $value[$key] = self::ordered($item);
        return $value;
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
