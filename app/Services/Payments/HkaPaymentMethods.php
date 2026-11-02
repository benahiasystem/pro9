<?php

namespace App\Services\Payments;

/** Catálogo 11 de HKA y restricciones de clasificación local. */
final class HkaPaymentMethods
{
    private const NAMES = [
        '01' => 'Depósito', '02' => 'Pago móvil', '03' => 'Transferencia',
        '04' => 'Orden de pago', '05' => 'Tarjeta de débito', '06' => 'Tarjeta de crédito',
        '07' => 'Cheque no negociable', '08' => 'Efectivo de curso legal', '09' => 'Efectivo en divisas',
        '10' => 'Medios de pago del exterior', '11' => 'Transferencia del exterior',
        '12' => 'Cheque del exterior', '13' => 'Orden de pago simple',
        '14' => 'Orden de pago documentaria', '15' => 'Remesa simple',
        '16' => 'Remesa documentaria', '17' => 'Carta de crédito simple',
        '18' => 'Carta de crédito documentaria', '99' => 'Otros',
    ];

    public static function options(): array
    {
        $options = [];
        foreach (self::NAMES as $code => $name) {
            $code = (string) $code;
            $options[] = [
                'code' => $code, 'name' => $name,
                'kinds' => $code === '99' ? ['cash', 'account', 'credit', 'unclassified'] : (in_array($code, ['08', '09'], true) ? ['cash'] : ['account']),
                'currencies' => $code === '08' ? ['VES'] : ($code === '09' ? ['USD'] : []),
            ];
        }
        return $options;
    }

    public static function compatible($code, $kind, $currency): bool
    {
        foreach (self::options() as $option) {
            if ($option['code'] === $code) {
                return in_array($kind, $option['kinds'], true)
                    && (!$option['currencies'] || in_array($currency, $option['currencies'], true));
            }
        }
        return false;
    }

    public static function name($code): ?string
    {
        return self::NAMES[$code] ?? null;
    }
}
