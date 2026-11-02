<?php

namespace App\Services\Payments;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Resolves a new receipt without interpreting an amount in another currency. */
final class MethodCurrency
{
    /** Resolve an active method before tax decisions or currency conversion. */
    public static function validateNew($id, string $documentCurrency, array $input, $connection): \App\Models\Tenant\PaymentMethodType
    {
        $method = \App\Models\Tenant\PaymentMethodType::query()->lockForUpdate()->find($id);
        if (!$method || !$method->is_active) Classification::error('payment_method_type_id', 'La forma de pago seleccionada no está disponible.');
        self::assertActiveCurrencies($connection, [$method->currency_type_id]);
        self::resolve((string)$method->currency_type_id, $documentCurrency, $input);
        return $method;
    }

    /** Use a current, locked read within a transaction, including after earlier consistent reads. */
    public static function assertActiveCurrencies($connection, array $currencies): void
    {
        $currencies = array_values(array_unique(array_map('strval', $currencies)));
        sort($currencies, SORT_STRING);
        if (!$currencies) return;
        $query = $connection->table('cat_currency_types')->whereIn('id', $currencies)->where('active', 1)->orderBy('id');
        if ($connection->transactionLevel() > 0) $query->lockForUpdate();
        if (array_diff($currencies, $query->pluck('id')->all())) {
            Classification::error('currency_type_id', 'La moneda del método no está disponible.');
        }
    }

    public static function resolve(string $methodCurrency, string $documentCurrency, array $input): string
    {
        $received = $input['currency_type_id'] ?? $documentCurrency;
        if (!is_string($received) || !in_array($received, ['VES', 'USD'], true)) {
            Classification::error('currency_type_id', 'Seleccione una moneda recibida válida.');
        }
        self::assertMatches($methodCurrency, $received);
        if ($received !== $documentCurrency && (!isset($input['original_amount']) || !isset($input['exchange_rate']))) {
            Classification::error('original_amount', 'Indique el importe recibido y la tasa para un cobro en otra moneda.');
        }
        return $received;
    }

    public static function assertMatches(string $methodCurrency, string $received): void
    {
        if (!in_array($methodCurrency, ['VES', 'USD'], true)) {
            Classification::error('payment_method_type_id', 'El método no tiene una moneda de cobro válida.');
        }
        if ($received !== $methodCurrency) {
            Classification::error('currency_type_id', "La moneda recibida debe coincidir con la del método: {$methodCurrency}.");
        }
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
