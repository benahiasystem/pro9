<?php

namespace App\Support\Exact;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
final class Payables
{
    public static function dataset(array $filters, bool $unfiltered = false): ReportDataset
    {
        $db = DB::connection('tenant');
        return $db->transaction(function () use ($db, $filters, $unfiltered) {
            return new ReportDataset((function () use ($db, $filters, $unfiltered) {
                $sources = [['purchases','purchase_payments','purchase_id','purchase'], ['expenses','expense_payments','expense_id','expense']];
                if (!empty($filters['withBankLoan'])) $sources[] = ['bank_loans','bank_loan_payments','bank_loan_id','bank_loans'];
                [$start, $end] = self::dates($filters);
                foreach ($sources as [$table, $paymentTable, $foreign, $type]) {
                    $query = $db->table($table)->whereIn($table.'.state_type_id', ['01','03','05','07','13'])->select($table.'.*');
                    if ($table === 'bank_loans') {
                        $query->join('bank_accounts', 'bank_accounts.id', '=', 'bank_loans.bank_account_id')
                            ->join('banks', 'banks.id', '=', 'bank_loans.bank_id')
                            ->addSelect('banks.description as bank_name', 'bank_accounts.description as bank_account_name', 'bank_accounts.id as supplier_id');
                    } else {
                        $query->join('persons', 'persons.id', '=', $table.'.supplier_id')->addSelect('persons.name as supplier_name');
                        if ($table === 'purchases') $query->whereIn('purchases.document_type_id', ['01','GU75','NE76']);
                        if (!empty($filters['supplier_id'])) $query->where($table.'.supplier_id', $filters['supplier_id']);
                        if (empty($filters['stablishmentTopaidAll']) && !empty($filters['establishment_id'])) $query->where($table.'.establishment_id', $filters['establishment_id']);
                        // Preserve the existing creator restriction; this change does not widen access.
                        if (!$unfiltered && (!empty($filters['user']) || auth()->user()->type !== 'admin')) $query->where($table.'.user_id', (int)($filters['user'] ?? 0));
                    }
                    if ($start !== null) $query->whereBetween($table.'.date_of_issue', [$start, $end]);
                    $lastId = 0;
                    $paymentColumns = $db->getSchemaBuilder()->getColumnListing($paymentTable);
                    do {
                        $batch = (clone $query)->where($table.'.id', '>', $lastId)->orderBy($table.'.id')->limit(500)->get();
                        if ($batch->isEmpty()) break;
                        $ids = $batch->pluck('id')->all();
                        $paymentQuery = $db->table($paymentTable)->whereIn($foreign, $ids)->orderBy('id');
                        if (in_array('reverted_at', $paymentColumns, true)) $paymentQuery->whereNull('reverted_at');
                        $paid = []; $lastPayment = [];
                        foreach ($paymentQuery->lazyById(500) as $payment) {
                            $id = $payment->$foreign;
                            $paid[$id] = ($paid[$id] ?? Decimal::of(0))->plus(Decimal::readRequired($payment, 'payment'))->simplified();
                            $date = (string)$payment->date_of_payment;
                            if (!isset($lastPayment[$id]) || strcmp($date, $lastPayment[$id]) > 0) $lastPayment[$id] = $date;
                        }
                        foreach ($batch as $row) {
                            $lastId = $row->id;
                            yield self::row($row, $type, $paid[$row->id] ?? Decimal::of(0), $lastPayment[$row->id] ?? null);
                        }
                    } while (count($batch) === 500);
                }
            })());
        });
    }

    public static function row($record, string $type, $paid, ?string $lastPayment): array
    {
        $row = (object)(is_array($record) ? $record : get_object_vars($record));
        $total = Decimal::readRequired($record, 'total');
        $paid = Decimal::of($paid);
        $payable = isset($row->total_payable) ? Decimal::readRequired($record, 'total_payable') : Decimal::of(Decimal::represent($total, 2));
        $balance = $payable->minus($paid)->simplified();
        $values = ['total'=>$total, 'total_payable'=>$payable, 'total_payment'=>$paid, 'total_to_pay'=>$balance,
            'total_subtraction'=>$paid->minus($payable), 'exact_balance'=>$total->minus($paid), 'settlement_adjustment'=>$payable->minus($total)];
        $due = !empty($row->date_of_due) ? Carbon::parse($row->date_of_due) : null;
        return array_merge([
            'id'=>$row->id, 'report_key'=>$type.':'.$row->id, 'user_id'=>$row->user_id ?? null,
            'date_of_issue'=>Carbon::parse($row->date_of_issue)->format('Y/m/d'),
            'date_of_due'=>$due ? $due->format('Y/m/d') : null,
            'supplier_id'=>$row->supplier_id,
            'supplier_name'=>$type === 'bank_loans' ? $row->bank_name.' - '.$row->bank_account_name : $row->supplier_name,
            'number_full'=>$type === 'purchase' ? $row->series.'-'.$row->number : $row->number,
            'type'=>$type, 'date_payment_last'=>$lastPayment ? Carbon::parse($lastPayment)->format('Y-m-d') : null,
            'delay_payment'=>$balance->isPositive() && $due && Carbon::now()->greaterThan($due) ? Carbon::now()->diffInDays($due) : null,
            'currency_type_id'=>$row->currency_type_id,
            'exchange_rate_sale'=>\App\Services\ExchangeRates\ExchangeRateMath::rate($row->exchange_rate_sale),
            'calculation_snapshot'=>Decimal::snapshot($values),
        ], Decimal::values($values));
    }

    public static function summary(iterable $rows): array
    {
        $totals = ['VES'=>['total'=>Decimal::of(0),'balance'=>Decimal::of(0)], 'USD'=>['total'=>Decimal::of(0),'balance'=>Decimal::of(0)]];
        $count = 0;
        foreach ($rows as $row) {
            if (!Decimal::readRequired($row, 'total_to_pay')->isPositive()) continue;
            $currency = $row['currency_type_id'];
            if (!isset($totals[$currency])) throw new \UnexpectedValueException('Moneda inválida en cuentas por pagar.');
            $totals[$currency]['total'] = $totals[$currency]['total']->plus(Decimal::readRequired($row, 'total'))->simplified();
            $totals[$currency]['balance'] = $totals[$currency]['balance']->plus(Decimal::readRequired($row, 'total_to_pay'))->simplified();
            $count++;
        }
        $values = [];
        foreach ($totals as $currency=>$fields) foreach ($fields as $field=>$value) $values[$currency.'_'.$field] = $value;
        return array_merge(Decimal::values($values), ['count'=>$count, 'calculation_snapshot'=>Decimal::snapshot($values)]);
    }

    private static function dates(array $filters): array
    {
        switch ($filters['period'] ?? null) {
            case 'month': return [Carbon::parse($filters['month_start'].'-01')->format('Y-m-d'), Carbon::parse($filters['month_start'].'-01')->endOfMonth()->format('Y-m-d')];
            case 'between_months': return [Carbon::parse($filters['month_start'].'-01')->format('Y-m-d'), Carbon::parse($filters['month_end'].'-01')->endOfMonth()->format('Y-m-d')];
            case 'date': return [$filters['date_start'], $filters['date_start']];
            case 'between_dates': return [$filters['date_start'], $filters['date_end']];
            default: return [null, null];
        }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
