<?php

namespace App\Support\Exact;

use App\Models\Tenant\SaleNote;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
final class SaleNoteBalance
{
    public static function values(SaleNote $note): array
    {
        $total = Decimal::readRequired($note, 'total');
        $payable = $note->total_payable === null ? Decimal::of(Decimal::represent($total, 2)) : Decimal::readRequired($note, 'total_payable');
        $paid = Decimal::of(0);
        foreach ($note->payments()->withoutEagerLoads()->lazyById(500) as $payment) {
            $paid = $paid->plus(Decimal::readRequired($payment, 'payment'));
        }
        return ['total'=>$payable, 'document_total'=>$total, 'total_paid'=>$paid,
            'total_difference'=>$payable->minus($paid), 'settlement_adjustment'=>$payable->minus($total)];
    }

    public static function transport(array $values): array
    {
        return array_map([Decimal::class, 'represent'], $values) + ['calculation_snapshot'=>Decimal::snapshot($values)];
    }

    public static function paymentTransport($row): array
    {
        $payment=Decimal::readRequired($row,'payment');
        $change=$row->change === null ? Decimal::of(0) : Decimal::readRequired($row,'change');
        $snapshot=$row->calculation_snapshot;
        $requested=isset($snapshot['values']['requested_payment']) ? Decimal::readRequired($row,'requested_payment') : $payment->plus($change);
        $adjustment=isset($snapshot['values']['cash_adjustment']) ? Decimal::readRequired($row,'cash_adjustment') : Decimal::of(0);
        return self::transport(['payment'=>$payment,'change'=>$change,'requested_payment'=>$requested,'cash_adjustment'=>$adjustment]);
    }

    /** The caller supplies its original indexed filters; no query per document. */
    public static function listing($query): array
    {
        return ReadSnapshot::run($query->getModel()->getConnection(), function () use ($query) {
            $total = $payable = $paid = Decimal::of(0);
            (clone $query)->withoutEagerLoads()->reorder()->chunkById(500, function ($notes) use (&$total, &$payable, &$paid) {
                foreach ($notes as $note) {
                    $value = Decimal::readRequired($note,'total');
                    $total = $total->plus($value);
                    $payable = $payable->plus($note->total_payable === null ? Decimal::represent($value,2) : Decimal::readRequired($note,'total_payable'));
                }
                $payments = \App\Models\Tenant\SaleNotePayment::withoutEagerLoads()->whereIn('sale_note_id',$notes->modelKeys());
                foreach ($payments->lazyById(500) as $payment) $paid = $paid->plus(Decimal::readRequired($payment,'payment'));
            });
            return self::transport(['total_pen'=>$total,'total_paid_pen'=>$paid,'total_pending_paid_pen'=>$payable->minus($paid),
                'total_payable'=>$payable,'settlement_adjustment'=>$payable->minus($total)]);
        });
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
