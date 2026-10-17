<?php

namespace App\Support\Exact;

use Illuminate\Database\Eloquent\Model;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Cash posted in the document currency; invoice FX/IGTF has its own fiscal boundary. */
final class Settlement
{
    private const OWNERS = [
        'sale_note_payments'=>[\App\Models\Tenant\SaleNote::class,'sale_note_id'],
        'purchase_payments'=>[\App\Models\Tenant\Purchase::class,'purchase_id'],
    ];

    public static function prepare(Model $payment): void
    {
        if (!isset(self::OWNERS[$payment->getTable()]) || !$payment->isDirty('payment')) return;
        Decimal::input($payment->payment,'payment');
        $requested = $payment->exactValue('payment');
        $cash = Decimal::of(Decimal::represent($requested,2));
        if ($requested->isNegative() || $requested->isGreaterThan(0) && $cash->isZero()) {
            \App\Services\Fiscal\FiscalAmounts::error('payment','El cobro debe ser positivo y liquidable en céntimos.');
        }
        $snapshot=$payment->calculation_snapshot ?? Decimal::snapshot([]);
        $snapshot['inputs']['payment']=Decimal::represent($requested);
        $snapshot['values']=array_replace($snapshot['values'] ?? [],array_map([Decimal::class,'fraction'],[
            'payment'=>$cash,'requested_payment'=>$requested,'cash_adjustment'=>$cash->minus($requested)]));
        $payment->payment=Decimal::represent($cash);
        $payment->calculation_snapshot=$snapshot;
    }

    public static function afterPayment(Model $payment): void
    {
        if (!isset(self::OWNERS[$payment->getTable()]) || !Persistence::enabled($payment)) return;
        [$class,$foreign]=self::OWNERS[$payment->getTable()];
        $owner=$class::lockForUpdate()->findOrFail($payment->$foreign);
        $paid=Decimal::sum($owner->payments()->lockForUpdate()->get()->map(fn($row)=>$row->exactValue('payment')));
        $target=$owner->total_payable === null ? $owner->exactValue('total') : $owner->exactValue('total_payable');
        if ($paid->isGreaterThan($target)) \App\Services\Fiscal\FiscalAmounts::error('payment','Los pagos superan el importe liquidable.');
        $owner->total_canceled=$paid->isEqualTo($target);
        $owner->saveQuietly();
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
