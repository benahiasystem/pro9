<?php

namespace App\Services\Payments;

use App\Models\Tenant\PaymentMethodType;
use App\Services\Fiscal\FiscalAmounts;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Prepare a receipt in its own currency and its applied documentary value. */
final class ReceiptAmounts
{
    public static function forMethod($id, string $documentCurrency, $documentRate, array $input): array
    {
        $method = PaymentMethodType::query()->lockForUpdate()->find($id);
        if (!$method || !$method->is_active) Classification::error('payment_method_type_id', 'La forma de pago seleccionada no está disponible.');
        MethodCurrency::assertActiveCurrencies($method->getConnection(), [$method->currency_type_id]);
        return self::prepare($method, $documentCurrency, $documentRate, $input);
    }

    public static function prepare(PaymentMethodType $method, string $documentCurrency, $documentRate, array $input): array
    {
        if (!$method->is_active) Classification::error('payment_method_type_id', 'La forma de pago seleccionada no está disponible.');
        validator($input, ['exchange_rate_source'=>'nullable|string|max:255', 'exchange_rate_date'=>'nullable|date'])->validate();
        MethodCurrency::resolve((string) $method->currency_type_id, $documentCurrency, $input);
        // The catalog tax is descriptive; fiscal eligibility is evaluated separately.
        $input['igtf_status'] = 'not_applicable';
        $values = FiscalAmounts::payment($input, $documentCurrency, $documentRate, false, null);
        return [
            'currency_type_id' => $values['currency_type_id'],
            'original_amount' => $values['original_amount'],
            'payment' => $values['payment'],
            'exchange_rate' => $values['exchange_rate'],
            'exchange_rate_source' => $values['exchange_rate_source'],
            'exchange_rate_date' => $values['exchange_rate_date'],
            'payment_method_snapshot' => Classification::snapshot($method),
            'calculation_snapshot' => $values['calculation_snapshot'],
        ];
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
