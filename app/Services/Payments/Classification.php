<?php

namespace App\Services\Payments;

use Illuminate\Validation\ValidationException;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
final class Classification
{
    public const KINDS = ['cash', 'account', 'credit', 'unclassified'];

    public static function initial(string $id): array
    {
        $kind = in_array($id, ['01', '10'], true) ? 'cash'
            : (in_array($id, ['02','03','04','06','11','12','13'], true) ? 'account'
            : (in_array($id, ['05','09'], true) ? 'credit' : 'unclassified'));
        return self::values($kind, $kind === 'cash' ? ($id === '10' ? 'USD' : 'VES') : null);
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
            'cash_currency_type_id'=>$method->cash_currency_type_id];
    }

    public static function cashIds(): array
    {
        if (\Illuminate\Container\Container::getInstance()->bound('db')) return \App\Models\Tenant\PaymentMethodType::where('collection_kind','cash')->pluck('id')->map(fn($id)=>(string)$id)->all();
        return ['01','10'];
    }

    public static function error(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field=>$message]);
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
