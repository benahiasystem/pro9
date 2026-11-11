<?php

namespace App\Services\Fiscal;

use App\Services\Payments\ReceiptChange;
use App\Support\Exact\Decimal as D;

/** Read-only quote: saving must independently revalidate under its transaction locks. */
final class PaymentQuote
{
    public static function reused(array $row): bool
    {
        return !empty($row['id']) || !empty($row['_reused_receipt']) || !empty($row['receipt_parent_id'])
            || !empty($row['receipt_origin']) || !empty($row['payment_method_snapshot']['receipt_origin'])
            || !empty($row['source_sale_note_payment_id']) || !empty($row['source_quotation_payment_id'])
            || !empty($row['source_technical_service_payment_id']);
    }

    /** @param callable(string): array $issuerAt */
    public static function calculate(array $payments, string $currency, $documentRate, $payable, callable $issuerAt, bool $enabled, $rate, bool $later = false): array
    {
        $normalized = [];
        foreach (array_values($payments) as $row) {
            // ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
            // Quote markers are not proof of a recorded receipt.
            RetiredIgtfException::assertNew($row);
            // ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
            validator($row, ['date_of_payment'=>'required|date_format:Y-m-d'])->validate();
            $reused = self::reused($row);
            $row['_reused_receipt'] = $reused;
            unset($row['change'], $row['_settlement_payment']);
            if ($reused) {
                $row['igtf_status'] = 'not_applicable';
            } else {
                // ######## INICIO INTERMEDIACION AUTOMATICA DEL COBRO ########
                $row = \App\Services\Payments\Classification::withFinancialIntermediation($row);
                // ######## FIN INTERMEDIACION AUTOMATICA DEL COBRO ########
                // The server proposes the classification automatically.
                $decisionInput = $row;
                unset($decisionInput['igtf_status']);
                $issuer = $issuerAt($row['date_of_payment']);
                $row['_quote_issuer_spe'] = SpecialTaxpayerPolicy::spe($issuer, $row['date_of_payment']);
                $decision = SpecialTaxpayerPolicy::payment($issuer, $decisionInput, $row['currency_type_id'] ?? $currency, $row['date_of_payment']);
                $row['igtf_status'] = $decision['igtf_status'];
            }
            $values = FiscalAmounts::payment($row, $currency, $documentRate, $reused ? false : $enabled, $rate);
            $normalized[] = array_merge($row, $values);
        }

        $payable = D::of(D::input($payable, 'principal_due'));
        if ($payable->isNegative()) FiscalAmounts::error('principal_due', 'El saldo no puede ser negativo.');
        $applied = D::sum(array_map(fn ($row) => D::readRequired($row, 'payment'), $normalized));
        if ($later && $applied->isGreaterThan($payable)) FiscalAmounts::error('payment', 'El pago supera el saldo disponible.');
        if (!$later) $normalized = ReceiptChange::apply($normalized, $payable, $currency);

        $result = [];
        $taxDocument = D::of(0);
        $received = [];
        $tendered = [];
        $applied = D::of(0);
        foreach ($normalized as $row) {
            $reused = $row['_reused_receipt'];
            $values = FiscalAmounts::payment($row, $currency, $documentRate, $reused ? false : $enabled, $rate);
            if (isset($row['_settlement_payment'])) $values = ReceiptChange::withSettlement($values, $row['_settlement_payment']);
            $row = array_merge($row, $values);
            $applied = $applied->plus(D::readRequired($values, 'payment'));
            $tax = D::readRequired($row, 'tax_amount');
            $taxDocument = $taxDocument->plus(D::convert($tax, $row['currency_type_id'], $currency, $row['exchange_rate']));
            if (!$reused) {
                $money = $row['currency_type_id'];
                $net = D::readRequired($row, 'original_amount')->plus($tax);
                $received[$money] = ($received[$money] ?? D::of(0))->plus($net);
                $tendered[$money] = ($tendered[$money] ?? D::of(0))->plus($net)->plus(D::of($row['change'] ?? 0));
            }
            $result[] = [
                'currency_type_id'=>$row['currency_type_id'], 'original_amount'=>$row['original_amount'],
                'payment'=>$row['payment'], 'exchange_rate'=>$row['exchange_rate'], 'change'=>D::represent($row['change'] ?? 0, 2, true),
                'settlement_adjustment'=>isset($row['_settlement_payment'])
                    ? D::represent(D::readRequired($values, 'payment_adjustment'), 2, true)
                    : null,
                'settlement_adjustment_exact'=>isset($row['_settlement_payment'])
                    ? D::fraction(D::readRequired($values, 'payment_adjustment')) : null,
                'igtf_status'=>$row['igtf_status'], 'tax_amount'=>$row['tax_amount'], 'reused'=>$reused,
                'reason'=>$reused ? 'Cobro ya registrado.' : ($row['igtf_status'] === 'subject' ? 'Cobro sujeto a IGTF.'
                    : (empty($row['_quote_issuer_spe'])
                        ? 'El emisor no tiene un perfil de sujeto pasivo especial vigente.'
                        : ($row['currency_type_id'] !== 'USD' ? 'El cobro no es en USD.' : 'El cobro tiene intermediación financiera.'))),
            ];
        }
        return ['payments'=>$result, 'principal_due'=>D::represent($payable, 2, true),
            'principal_applied'=>D::represent($applied, 2, true), 'tax_in_document_currency'=>D::represent($taxDocument, 2, true),
            'total_with_tax'=>D::represent($payable->plus($taxDocument), 2, true),
            'balance'=>D::represent(D::max(0, $payable->minus($applied)), 2, true),
            'received_by_currency'=>array_map(fn ($amount)=>D::represent($amount, 2, true), $received),
            'tendered_by_currency'=>array_map(fn ($amount)=>D::represent($amount, 2, true), $tendered), 'can_submit'=>true];
    }
}
