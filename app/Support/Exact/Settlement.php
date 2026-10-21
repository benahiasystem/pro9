<?php

namespace App\Support\Exact;

use Illuminate\Database\Eloquent\Model;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Cash posted in the document currency; invoice FX/IGTF has its own fiscal boundary. */
final class Settlement
{
    private static array $deferred = [];
    private const OWNERS = [
        'sale_note_payments'=>[\App\Models\Tenant\SaleNote::class,'sale_note_id'],
        'purchase_payments'=>[\App\Models\Tenant\Purchase::class,'purchase_id'],
    ];

    public static function prepare(Model $payment): void
    {
        if (!isset(self::OWNERS[$payment->getTable()])) return;
        $snapshot=$payment->calculation_snapshot ?? Decimal::snapshot([]);
        $hasTender=isset($snapshot['inputs']['tendered_payment'], $snapshot['values']['payment']);
        if (!$payment->isDirty('payment') && (!$hasTender || !$payment->isDirty('change'))) return;
        Decimal::input($payment->payment,'payment');
        if (isset($snapshot['inputs']['tendered_payment'], $snapshot['values']['payment'])
            && Decimal::of($payment->payment)->isEqualTo(Decimal::of($snapshot['values']['payment']))) {
            $requested = Decimal::of(Decimal::input($snapshot['inputs']['tendered_payment'],'payment'));
            $tendered = Decimal::of(Decimal::represent($requested, 2));
            $change = Decimal::of(Decimal::input($payment->change, 'change'));
            if ($requested->isNegative() || $change->isNegative() || $change->isGreaterThan($tendered)
                || !$change->isEqualTo(Decimal::represent($change,2))
                || !$tendered->minus($change)->isEqualTo($payment->payment)) {
                \App\Services\Fiscal\FiscalAmounts::error('payment','El efectivo y su vuelto no coinciden con el cobro aplicado.');
            }
            $snapshot['values']=array_replace($snapshot['values'],array_map([Decimal::class,'fraction'],[
                'requested_payment'=>$requested,'tendered_amount'=>$tendered,'cash_adjustment'=>$tendered->minus($requested)]));
            $payment->calculation_snapshot=$snapshot;
            return;
        }
        unset($snapshot['inputs']['tendered_payment'], $snapshot['inputs']['tender_position'], $snapshot['values']['tendered_amount']);
        $requested = $payment->exactValue('payment');
        $cash = Decimal::of(Decimal::represent($requested,2));
        if ($requested->isNegative() || $requested->isGreaterThan(0) && $cash->isZero()) {
            \App\Services\Fiscal\FiscalAmounts::error('payment','El cobro debe ser positivo y liquidable en céntimos.');
        }
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
        if (isset(self::$deferred[self::key($payment->getConnection(), $class, $payment->$foreign)])) return;
        $owner=$class::withoutEagerLoads()->lockForUpdate()->findOrFail($payment->$foreign);
        self::summarize($owner);
    }

    private static function summarize(Model $owner): void
    {
        $paid=Decimal::of(0);
        foreach ($owner->payments()->withoutEagerLoads()->lockForUpdate()->lazyById(500) as $row) $paid=$paid->plus(Decimal::readRequired($row,'payment'));
        $target=$owner->total_payable === null ? $owner->exactValue('total') : $owner->exactValue('total_payable');
        if ($paid->isGreaterThan($target)) \App\Services\Fiscal\FiscalAmounts::error('payment','Los pagos superan el importe liquidable.');
        $owner->total_canceled=$paid->isEqualTo($target);
        $owner->saveQuietly();
    }

    public static function defer(Model $owner, callable $operation)
    {
        $db=$owner->getConnection();
        if ($db->transactionLevel() === 0) return $db->transaction(fn()=>self::defer($owner,$operation));
        $key=self::key($db,get_class($owner),$owner->getKey());
        $outer=!isset(self::$deferred[$key]);
        if ($outer) {
            $stored=$owner->newQuery()->withoutEagerLoads()->lockForUpdate()->findOrFail($owner->getKey());
            $owner->setRawAttributes($stored->getAttributes(),true);
        }
        self::$deferred[$key]=(self::$deferred[$key] ?? 0)+1;
        try { $result=$operation(); }
        finally { if (--self::$deferred[$key] === 0) unset(self::$deferred[$key]); }
        if ($outer) { self::summarize($owner); $owner->unsetRelation('payments'); }
        return $result;
    }

    private static function key($db,string $class,$id): string
    {
        return spl_object_id($db->getPdo()).':'.$class.':'.$id;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
