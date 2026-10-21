<?php

namespace App\Support\Exact;

use App\Models\System\PaymentOrder;
use App\Support\Venezuela\Localization;
use Illuminate\Validation\ValidationException;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** A new order freezes its own quote; a browser amount/currency is never authority. */
final class CentralPaymentQuote
{
    public static function freeze(PaymentOrder $order): void
    {
        if (!Persistence::enabled($order)) return;
        $amount=Decimal::readRequired($order,'amount');
        if ($amount->isNegative()) throw ValidationException::withMessages(['amount'=>'El importe de la orden no puede ser negativo.']);
        $payable=Decimal::of(Decimal::represent($amount,2));
        $snapshot=$order->calculation_snapshot ?? Decimal::snapshot([]);
        $snapshot['inputs']['payment_currency_type_id']=Localization::nationalCurrencyId();
        $snapshot['inputs']['payment_order_uuid']=$order->uuid;
        $snapshot['values']=array_replace($snapshot['values'] ?? [],array_map([Decimal::class,'fraction'],[
            'amount'=>$amount,'provider_payable'=>$payable,'provider_adjustment'=>$payable->minus($amount)]));
        $order->calculation_snapshot=$snapshot;
    }

    public static function read(PaymentOrder $order): array
    {
        $snapshot=$order->calculation_snapshot;
        $currency=$snapshot['inputs']['payment_currency_type_id'] ?? null;
        if (!in_array($currency,['VES','USD'],true) || ($snapshot['inputs']['payment_order_uuid'] ?? null) !== $order->uuid) {
            throw ValidationException::withMessages(['currency'=>'La orden carece de una moneda y referencia de cobro conservadas.']);
        }
        $amount=Decimal::readRequired($order,'amount');
        $payable=Decimal::readRequired($order,'provider_payable');
        $adjustment=Decimal::readRequired($order,'provider_adjustment');
        if ($amount->isNegative() || !$payable->isEqualTo(Decimal::represent($amount,2)) || !$amount->plus($adjustment)->isEqualTo($payable)) {
            throw ValidationException::withMessages(['amount'=>'La representación de cobro no coincide con la orden conservada.']);
        }
        return ['currency'=>$currency,'amount'=>$amount,'payable'=>$payable,'adjustment'=>$adjustment,
            'minor_units'=>(string)$payable->multipliedBy(100)->toBigInteger()];
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
