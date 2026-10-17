<?php
namespace App\Services\Fiscal;

use App\Services\ExchangeRates\ExchangeRateMath as Math;
use App\Support\Exact\Decimal as D;

/** Fiscal settlement declarations are not receipts and never enter financial ledgers. */
final class HkaNotePayments
{
    public static function append(array $snapshot, array $forms): array
    {
        if (!HkaNotePolicy::snapshot($snapshot)) return $forms;
        $credit = $snapshot['document_type_id'] === '07';
        if (!$credit && ($snapshot['payment_condition_id'] ?? null) !== '02') return $forms;
        $remaining = D::read($snapshot,'total');
        if (!$credit) foreach ($snapshot['payments'] ?? [] as $payment) {
            if (!empty($payment['reversed_at']) || !empty($payment['receipt_parent_id'])) continue;
            $remaining = $remaining->minus(isset($payment['payment']) ? D::read($payment,'payment') : D::convert(D::read($payment,'original_amount'),$payment['currency_type_id'],$snapshot['currency_type_id'],$payment['exchange_rate']));
            foreach ($snapshot['taxes'] ?? [] as $tax) if ($tax['tax_kind'] === 'IGTF' && ($tax['document_payment_id'] ?? null) === ($payment['id'] ?? null)) $remaining = $remaining->minus(D::read($tax,'amount'));
        }
        if ($credit) $forms = [];
        $amount = Math::finalAmount($remaining);
        if ($amount < -0.005) FiscalAmounts::error('payments', 'Los cobros de la ND superan su total.');
        if ($amount > 0) $forms[] = ['forma'=>'99', 'descripcion'=>$credit ? 'Crédito comercial por nota de crédito' : 'Crédito comercial pendiente de cobro',
            'moneda'=>$snapshot['currency_type_id'], 'monto'=>$amount, 'fecha'=>\Carbon\Carbon::parse($snapshot['date_of_issue'])->format('d/m/Y'),
            'tipoCambio'=>(string)$snapshot['exchange_rate_sale']];
        return $forms;
    }

    /** Reconcile only a non-cash declaration; real receipts and local balances stay intact. */
    public static function reconcile(array $payload, array $snapshot): array
    {
        if (!HkaNotePolicy::snapshot($snapshot) || $snapshot['document_type_id'] !== '08' || ($snapshot['payment_condition_id'] ?? '') !== '02') return $payload;
        $header=&$payload['documentoElectronico']['encabezado'];$forms=&$header['totales']['formasPago'];
        $currency=$header['identificacionDocumento']['moneda'];$sum=Math::rational(0);$creditIndex=null;$creditAmount='0.00';
        foreach ($forms as $index=>$form) {
            $amount=D::convert($form['monto'],$form['moneda'],$currency,$form['tipoCambio']);$sum=$sum->plus($amount);
            if (($form['descripcion'] ?? '') === 'Crédito comercial pendiente de cobro') {$creditIndex=$index;$creditAmount=$amount;}
        }
        if ($creditIndex !== null && Math::finalAmount($sum->minus($header['totales']['totalAPagar'])) !== '0.00') {
            $amount=Math::finalAmount(Math::rational($header['totales']['totalAPagar'])->minus($sum->minus($creditAmount)));
            if (D::of($amount)->isLessThanOrEqualTo(0)) FiscalAmounts::error('payments','El crédito fiscal no respalda los cobros conservados.');
            $forms[$creditIndex]['moneda']=$currency;$forms[$creditIndex]['monto']=$amount;
        }
        return $payload;
    }
}
