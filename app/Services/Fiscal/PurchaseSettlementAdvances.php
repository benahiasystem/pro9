<?php

namespace App\Services\Fiscal;

use App\Support\Exact\Decimal;

/** A declared advance is a documented operand, not an invented payment. */
final class PurchaseSettlementAdvances
{
    public static function normalize(array $input): array
    {
        $rows=$input['prepayments'] ?? [];
        if (!is_array($rows)) FiscalAmounts::error('prepayments', 'Indique el detalle de los anticipos.');
        $total=Decimal::of(0); $seen=[]; $normalized=[];
        foreach ($rows as $row) {
            if (!is_array($row)) FiscalAmounts::error('prepayments', 'Anticipo inválido.');
            $number=trim((string) ($row['number'] ?? ''));
            $type=trim((string) ($row['document_type_id'] ?? ''));
            if ($number === '' || $type === '') FiscalAmounts::error('prepayments', 'Cada anticipo requiere tipo y número de documento.');
            $key=$type."\0".$number;
            if (isset($seen[$key])) FiscalAmounts::error('prepayments', 'Un anticipo no puede repetirse.');
            $seen[$key]=true;
            $amount=Decimal::of(Decimal::input($row['amount'] ?? null,'prepayments.amount'));
            if ($amount->isLessThanOrEqualTo(0)) FiscalAmounts::error('prepayments', 'El anticipo debe ser positivo.');
            $total=$total->plus($amount);
            $normalized[]=['number'=>$number,'document_type_id'=>$type,'amount'=>Decimal::represent($amount)];
        }
        $declared=Decimal::of(Decimal::input($input['total_prepayment'] ?? '0','total_prepayment'));
        if (!$declared->isEqualTo($total)) FiscalAmounts::error('total_prepayment', 'El total de anticipos debe coincidir con su detalle.');
        $input['total_prepayment']=Decimal::input(Decimal::represent($total),'total_prepayment');
        $input['prepayments']=$normalized;
        return $input;
    }
}
