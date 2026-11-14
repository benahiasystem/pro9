<?php

namespace App\Services\Payments;

use App\Services\Fiscal\{FiscalAmounts, PaymentQuote};
use App\Support\Exact\Decimal as D;

/** Optional invoice input: the actual money handed over already includes IGTF. */
final class TenderedReceipt
{
    public static function prepare(array $row, bool $enabled, $rate): array
    {
        unset($row['_tendered_input']);
        if (!array_key_exists('tendered_amount', $row)) return $row;
        if (PaymentQuote::reused($row)) FiscalAmounts::error('tendered_amount', 'Un cobro registrado conserva su importe original.');
        $requested = D::of(D::input($row['tendered_amount'], 'tendered_amount'));
        $total = D::of(D::represent($requested, 2, true));
        if (!$requested->isPositive() || !$total->isPositive()) FiscalAmounts::error('tendered_amount', 'Indique un total entregado positivo.');
        $subject = ($row['igtf_status'] ?? 'not_applicable') === 'subject';
        if ($subject && (!$enabled || !is_numeric($rate) || !D::of($rate)->isPositive())) {
            FiscalAmounts::error('igtf_status', 'Configure y habilite el IGTF antes de registrar pagos sujetos.');
        }
        $taxRate = $subject ? $rate : '0';
        $principalExact = $total->multipliedBy(100)->dividedBy(D::of(100)->plus($taxRate));
        $taxExact = $total->minus($principalExact);
        // Allocate once from the exact split. Tax wins a half-cent tie (HALF_UP).
        [$tax, $principal] = D::allocate($total, [$taxRate, '100'], 2);
        $principal = D::of($principal);
        if (!$principal->isPositive()) FiscalAmounts::error('tendered_amount', 'El total entregado no cubre un principal liquidable.');
        $row['original_amount'] = D::represent($principal, 2, true);
        $row['_tendered_input'] = ['requested'=>D::fraction($requested), 'total'=>D::fraction($total), 'rate'=>$taxRate,
            'principal'=>D::fraction($principal), 'tax'=>D::fraction($tax),
            'principal_exact'=>D::fraction($principalExact), 'tax_exact'=>D::fraction($taxExact)];
        unset($row['tendered_amount']);
        return $row;
    }

    /** Called after principal/change allocation; IGTF belongs to the final net only. */
    public static function finish(array $rows, array $cashMethods, $payable): array
    {
        foreach ($rows as &$row) {
            if (!isset($row['_tendered_input']) || PaymentQuote::reused($row)) continue;
            $input = $row['_tendered_input'];
            $net = D::of($row['original_amount']);
            // A sole receipt that fully covers the known sale keeps real change:
            // a sale of 38.83 plus 1.16 IGTF still returns 0.01 from 40.00.
            if (count($rows) === 1 && D::of($row['payment'])->isEqualTo($payable)
                && !$net->plus(self::tax($net, $input['rate']))->isGreaterThan(D::of($input['total']))) {
                $input['tax_capped'] = true;
                $row['_tendered_input'] = $input;
            }
            $tax = self::collectedTax($net, $input);
            $change = D::of($input['total'])->minus($net)->minus($tax);
            if ($change->isNegative()) FiscalAmounts::error('tendered_amount', 'El total entregado no cubre el principal y su IGTF.');
            if ($change->isPositive() && !in_array((string)($row['payment_method_type_id'] ?? ''), $cashMethods, true)) {
                FiscalAmounts::error('tendered_amount', 'El total bancario debe coincidir con el principal y su IGTF, sin vuelto.');
            }
            $row['change'] = D::represent($change, 2, true);
        }
        unset($row);
        return $rows;
    }

    /** Trusted initial-receipt context, never read from the browser's snapshot. */
    public static function context(array $row): array
    {
        if (!isset($row['_tendered_input'])) return [];
        $input = $row['_tendered_input'];
        $tax = self::collectedTax(D::of($row['original_amount']), $input);
        return ['requested_total_tendered'=>$input['requested'], 'total_tendered'=>$input['total'],
            'original_amount'=>D::fraction(D::of($input['total'])->minus($tax)), 'inclusive_input'=>$input];
    }

    /** Only server-prepared input/context may supply this allocation. */
    public static function withAllocation(array $values, ?array $input): array
    {
        if ($input === null) return $values;
        $net = D::readRequired($values, 'original_amount');
        $baseExact = $net;
        $taxExact = D::readRequired($values, 'tax_exact');
        if (empty($input['tax_capped']) && $net->isEqualTo(D::of($input['principal']))) {
            $baseExact = D::of($input['principal_exact']);
            $taxExact = D::of($input['tax_exact']);
            $values['tax_amount'] = D::represent(D::of($input['tax']), 2, true);
            $values['calculation_snapshot']['values']['tax_amount'] = $input['tax'];
        }
        $values['calculation_snapshot']['values']['tendered_principal_exact'] = $input['principal_exact'];
        $values['calculation_snapshot']['values']['tendered_tax_exact'] = $input['tax_exact'];
        $values['calculation_snapshot']['values']['tax_base_exact'] = D::fraction($baseExact);
        $values['calculation_snapshot']['values']['tax_exact'] = D::fraction($taxExact);
        $values['calculation_snapshot']['values']['principal_settlement_adjustment'] = D::fraction($net->minus($baseExact));
        $values['calculation_snapshot']['values']['tax_settlement_adjustment'] = D::fraction(D::of($values['tax_amount'])->minus($taxExact));
        return $values;
    }

    private static function collectedTax($net, array $input)
    {
        return empty($input['tax_capped']) && $net->isEqualTo(D::of($input['principal']))
            ? D::of($input['tax']) : self::tax($net, $input['rate']);
    }

    private static function tax($principal, $rate)
    {
        return D::of(D::represent(D::of($principal)->multipliedBy($rate)->dividedBy(100), 2, true));
    }
}
