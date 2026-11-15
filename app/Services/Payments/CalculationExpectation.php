<?php
namespace App\Services\Payments;

use App\Support\Exact\Decimal as D;
use App\Models\Tenant\Document;

// ######## INICIO VERIFICACION FINAL DE CALCULOS ########
final class CalculationExpectation
{
    public const TOTALS = ['total_taxed','total_exonerated','total_igv','total_value','total_discount','total_charge','total_exportation','total_other_taxes','total'];
    public const LINE = ['quantity','unit_price','total_value','total_igv','total'];

    public static function summary($owner, array $inputs): array
    {
        $invoice = $owner instanceof Document;
        $tax = $invoice ? D::sum($owner->taxes()->where('tax_kind','IGTF')->get()->map(fn ($row)=>D::readRequired($row,'amount'))) : D::of(0);
        $totals = [];
        foreach (self::TOTALS as $field) {
            $value = D::readRequired($owner, $field);
            if (in_array($field, ['total','total_other_taxes'], true)) $value = $value->minus($tax);
            $totals[$field] = D::fraction($value);
        }
        $items = $owner->items()->orderBy('id')->get()->map(function ($row) {
            $values = ['item_id'=>(string)$row->item_id,'affectation_igv_type_id'=>$row->affectation_igv_type_id];
            foreach (self::LINE as $field) $values[$field] = D::fraction(D::readRequired($row, $field));
            return $values;
        })->all();
        $receipts = $invoice ? $owner->payments()->whereNull('receipt_parent_id')->orderBy('id')->get() : $owner->payments()->orderBy('id')->get();
        $payments = $receipts->map(function ($row) use ($invoice, $owner) {
            $reused = !empty($row->payment_method_snapshot['receipt_origin']);
            $change = $reused ? D::of(0) : D::of($row->calculation_snapshot['values']['received_change'] ?? $row->change ?? 0);
            $destination = $reused ? 'recorded' : self::destination($row->global_payment);
            $cashId = !$reused && $destination === 'cash' ? (string)$row->global_payment->destination_id : null;
            $disbursements = $invoice && !$reused ? $row->change_disbursements()->orderBy('position')->get()->map(fn ($line)=>[
                'payment_method_type_id'=>(string)$line->payment_method_type_id, 'payment_destination_id'=>self::destination($line->global_payment),
                'cash_id'=>self::destination($line->global_payment) === 'cash' ? (string)$line->global_payment->destination_id : null,
                'currency_type_id'=>$line->currency_type_id, 'amount'=>D::represent($line->original_amount,2,true)])->all() : [];
            return ['payment_method_type_id'=>(string)$row->payment_method_type_id, 'payment_destination_id'=>$destination, 'cash_id'=>$cashId,
                'change_disbursements'=>$disbursements, 'currency_type_id'=>$row->currency_type_id, 'exchange_rate'=>$row->exchange_rate,
                'original_amount'=>D::represent(D::readRequired($row,'original_amount'),2,true),
                'payment'=>D::represent(D::readRequired($row,'payment')), 'payment_exact'=>D::fraction(D::readRequired($row,'payment')), 'change'=>D::represent($change,2,true),
                'tax_amount'=>D::represent($invoice && !$reused ? D::readRequired($row,'tax_amount') : 0,2,true),
                'igtf_status'=>$invoice && !$reused ? $row->igtf_status : 'not_applicable', 'reused'=>$reused];
        })->all();
        $balance = $invoice ? $owner->exactBalance(true) : \App\Support\Exact\SaleNoteBalance::values($owner,true)['total_difference'];
        $fees = $owner->fee()->orderBy('id')->get()->map(fn ($row)=>['date'=>\Carbon\Carbon::parse($row->date)->format('Y-m-d'),
            'payment_method_type_id'=>(string)$row->payment_method_type_id, 'amount'=>D::represent(D::readRequired($row,'amount'),2,true)])->all();
        $company=\App\Models\Tenant\Company::query()->lockForUpdate()->firstOrFail();
        return ['version'=>1, 'revision'=>$inputs['expected_calculation']['revision'] ?? '', 'currency_type_id'=>$owner->currency_type_id,
            'igtf_enabled'=>$invoice && (bool)$company->igtf_enabled,'igtf_rate'=>$company->igtf_rate,
            'exchange_rate_sale'=>$owner->exchange_rate_sale, 'totals'=>$totals, 'items'=>$items, 'payments'=>$payments,
            'total_with_tax'=>D::represent(D::readRequired($owner,'total_payable'),2,true),
            'total_with_tax_exact'=>D::fraction(D::readRequired($owner,'total')),
            'settlement_adjustment_exact'=>D::fraction(D::readRequired($owner,'total_payable')->minus(D::readRequired($owner,'total'))),
            'balance'=>D::represent(D::max(0,$balance),2,true), 'balance_exact'=>D::fraction(D::max(0,$balance)), 'fee'=>$fees];
    }

    private static function destination($global): string
    {
        if (!$global) return '';
        return $global->destination_type === \App\Models\Tenant\Cash::class ? 'cash' : (string)$global->destination_id;
    }

    public static function verify($owner, array $inputs): void
    {
        if (!isset($inputs['expected_calculation'])) return;
        $expected = $inputs['expected_calculation'];
        validator(['expected_calculation'=>$expected], ['expected_calculation'=>'required|array',
            'expected_calculation.version'=>'required|integer|in:1', 'expected_calculation.revision'=>'required|string|max:100',
            'expected_calculation.totals'=>'required|array', 'expected_calculation.items'=>'required|array|max:1000',
            'expected_calculation.payments'=>'present|array|max:100', 'expected_calculation.fee'=>'present|array|max:1000'])->validate();
        $actual = self::summary($owner,$inputs);
        if (!self::equal($expected,$actual)) throw new CalculationChanged($actual,self::differences($expected,$actual));
    }

    private static function differences($expected, $actual, string $path = ''): array
    {
        $field = basename(str_replace('.', '/', $path));
        if (self::equal($expected,$actual,$field)) return [];
        if (!is_array($actual) || isset($actual['n'],$actual['d']) || !is_array($expected)) {
            return [['field'=>$path,'expected'=>$expected,'actual'=>$actual]];
        }
        $result = [];
        foreach (array_unique(array_merge(array_keys($expected),array_keys($actual))) as $key) {
            $result = array_merge($result,self::differences($expected[$key] ?? null,$actual[$key] ?? null,ltrim($path.'.'.$key,'.')));
        }
        return $result;
    }

    private static function equal($expected, $actual, string $field = ''): bool
    {
        if (is_array($actual) && isset($actual['n'], $actual['d'])) {
            try { return D::of($actual)->isEqualTo(D::of($expected)); } catch (\Throwable $e) { return false; }
        }
        if (is_array($actual)) {
            if (!is_array($expected) || count($expected) !== count($actual)) return false;
            foreach ($actual as $key=>$value) if (!array_key_exists($key,$expected) || !self::equal($expected[$key],$value,(string)$key)) return false;
            return true;
        }
        if (is_bool($actual)) return $expected === $actual;
        if (in_array($field,['revision','currency_type_id','payment_method_type_id','payment_destination_id','cash_id','item_id','affectation_igv_type_id'],true)) return $expected === $actual;
        if (is_numeric($actual) && is_numeric($expected)) {
            try { return D::of($actual)->isEqualTo(D::of($expected)); } catch (\Throwable $e) { return false; }
        }
        return (string)$expected === (string)$actual;
    }
}
// ######## FIN VERIFICACION FINAL DE CALCULOS ########
