<?php

namespace App\Services\Payments;

use Illuminate\Validation\ValidationException;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
final class Classification
{
    public const KINDS = ['cash', 'account', 'credit', 'unclassified'];

    public static function kindLabel(?string $kind): string
    {
        return ['cash'=>'Efectivo', 'account'=>'Bancario / electrónico', 'credit'=>'Crédito'][$kind] ?? 'Sin clasificar';
    }

    public static function historicalGroupKey(array $row): string
    {
        return json_encode([$row['method']['catalog'], $row['method']['id'], $row['method']['description'],
            $row['method']['collection_kind'], $row['currency_type_id'], $row['destination_type'],
            $row['destination_id'], $row['destination_label']], JSON_THROW_ON_ERROR);
    }

    public static function values(string $kind, ?string $currency = null): array
    {
        if (!in_array($kind, self::KINDS, true)) self::error('collection_kind', 'Seleccione la naturaleza del medio de pago.');
        if ($kind === 'cash' && !in_array($currency, ['VES','USD'], true)) self::error('cash_currency_type_id', 'Seleccione la moneda del efectivo.');
        if ($kind !== 'cash' && $currency !== null) self::error('cash_currency_type_id', 'La moneda fija corresponde únicamente al efectivo.');
        return ['collection_kind'=>$kind, 'cash_currency_type_id'=>$currency, 'is_cash'=>$kind === 'cash', 'is_credit'=>$kind === 'credit'];
    }

    public static function snapshot($method, string $catalog = 'payment_method_types'): array
    {
        if ($catalog === 'expense_method_types') {
            // Expense IDs belong to a separate catalog; never join them to payment IDs.
            $kind = (string)$method->id === '1' ? 'cash' : (in_array((string)$method->id, ['2','3'], true) ? 'account' : 'unclassified');
            return ['catalog'=>$catalog, 'id'=>(string)$method->id, 'description'=>$method->description, 'collection_kind'=>$kind, 'cash_currency_type_id'=>null];
        }
        return ['catalog'=>$catalog, 'id'=>(string)$method->id, 'description'=>$method->description,
            'hka_code'=>$method->hka_code, 'collection_kind'=>$method->collection_kind ?? 'unclassified',
            'currency_type_id'=>$method->currency_type_id,
            'cash_currency_type_id'=>$method->cash_currency_type_id];
    }

    public static function cashIds(): array
    {
        if (\Illuminate\Container\Container::getInstance()->bound('db')) return \App\Models\Tenant\PaymentMethodType::where('collection_kind','cash')->pluck('id')->map(fn($id)=>(string)$id)->all();
        self::error('payment_method_type_id', 'No se pudo consultar el catálogo de medios de pago.');
    }

    /** A recorded receipt keeps its classification even after catalog edits. */
    public static function filterRecorded($query, string $kind)
    {
        return $query->whereHas('global_payment', function ($movement) use ($kind) {
            $movement->where('report_context->method->collection_kind', $kind);
        });
    }

    public static function recordedKind($payment): ?string
    {
        $context = $payment->global_payment?->report_context;
        $snapshot = $payment->payment_method_snapshot;
        $kind = $context['method']['collection_kind'] ?? $snapshot['collection_kind'] ?? null;
        return in_array($kind, ['cash', 'account'], true) ? $kind : null;
    }

    public static function defaultCashMethod(string $currency): \App\Models\Tenant\PaymentMethodType
    {
        $query = \App\Models\Tenant\PaymentMethodType::active()
            ->where('collection_kind', 'cash')->where('currency_type_id', $currency);
        // Orden binario UTF-8 coherente con la comparación de códigos del frontend.
        if ($query->getModel()->getConnection()->getDriverName() === 'mysql') $query->orderByRaw('BINARY id');
        else $query->orderBy('id');
        if ($query->getModel()->getConnection()->transactionLevel() > 0) $query->lockForUpdate();
        $method = $query->first();
        if (!$method) {
            self::error('payment_method_type_id', 'Configure un método de efectivo activo en '.$currency.'.');
        }
        MethodCurrency::assertActiveCurrencies($method->getConnection(), [$currency]);
        return $method;
    }

    public static function error(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field=>$message]);
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
