<?php

namespace App\Support\Exact;

use Modules\Sale\Models\TechnicalService;
use App\Services\Fiscal\FiscalAmounts;

/** Server-owned service totals; `total` remains the amount in addition to cost. */
final class TechnicalServiceCalculation
{
    /** Do not infer header adjustments absent from an older record. */
    public static function assertHistoricalAdjustmentsRecoverable(TechnicalService $service, $items): void
    {
        if (isset($service->calculation_snapshot['inputs']['service_adjustments'])) return;
        $sum=fn($field)=>Decimal::sum($items->map(fn($line)=>Decimal::read($line,$field)));
        if (!Decimal::read($service,'total')->isEqualTo($sum('total'))
            || !Decimal::read($service,'total_discount')->isEqualTo($sum('total_discount'))
            || !Decimal::read($service,'total_charge')->isEqualTo($sum('total_charge'))
            || !Decimal::read($service,'total_prepayment')->isZero()) {
            FiscalAmounts::error('items','El servicio histórico no conserva sus ajustes originales. Revise esos importes antes de continuar.');
        }
    }

    public static function summarize(TechnicalService $service, array $input): void
    {
        $rows=$service->items()->withoutEagerLoads()->lockForUpdate()->get();
        $stats=HeaderAllocation::stats();
        foreach ($rows as $row) HeaderAllocation::add($stats,$row);
        $cost=Decimal::of(Decimal::input($service->cost,'cost'));
        $advance=Decimal::of(Decimal::input($service->prepayment,'prepayment'));
        if ($cost->isNegative() || $advance->isNegative() || $advance->isGreaterThan($cost)) {
            FiscalAmounts::error('prepayment','El costo y el anticipo deben ser no negativos y el anticipo no puede superar el costo.');
        }
        $rate=Decimal::of(\App\Support\Venezuela\Localization::exactTaxPercentage())->dividedBy(100);
        $serviceBase=$cost->dividedBy(Decimal::of(1)->plus($rate));
        $taxed=$stats['taxed_base']->plus($serviceBase);
        $iva=$stats['iva']->plus($cost->minus($serviceBase));
        $discountRows=HeaderAllocation::adjustments($input['discounts'] ?? []);
        foreach ($discountRows as &$discount) {
            if (($discount['percentage'] ?? null) !== null && empty($discount['is_amount'])
                && in_array($discount['discount_type_id'] ?? null,['02','05'],true)) {
                $percentage=Decimal::of(Decimal::input($discount['percentage'],'discounts.percentage'));
                if ($percentage->isNegative() || $percentage->isGreaterThan(100)) FiscalAmounts::error('discounts','El porcentaje del descuento debe estar entre 0 y 100.');
                $base=$discount['discount_type_id'] === '02' ? $taxed : $stats['exempt_base'];
                $amount=$base->multipliedBy($percentage)->dividedBy(100);
            } else {
                $amount=Decimal::of(Decimal::input($discount['amount'] ?? null,'discounts.amount'));
            }
            if ($amount->isNegative()) FiscalAmounts::error('discounts','El descuento no puede ser negativo.');
            $discount['amount']=Decimal::represent($amount);
            $discount['amount_exact']=Decimal::fraction($amount);
        }
        unset($discount);
        $discounts=HeaderAllocation::discounts($discountRows,$stats);
        if ($discounts['taxed']->isGreaterThan($taxed) || $discounts['exempt']->isGreaterThan($stats['exempt_base'])) {
            FiscalAmounts::error('discounts','El descuento supera su base.');
        }
        if (!$taxed->isZero() && !$discounts['taxed']->isZero()) {
            $iva=$iva->multipliedBy($taxed->minus($discounts['taxed']))->dividedBy($taxed);
        }
        $prepayment=Decimal::of(Decimal::input($input['total_prepayment'] ?? 0,'total_prepayment'));
        if ($prepayment->isNegative()) FiscalAmounts::error('total_prepayment','El anticipo documental no puede ser negativo.');
        $chargeRows=HeaderAllocation::adjustments($input['charges'] ?? []);
        $charge=Decimal::of(0); $automatic=[];
        foreach ($chargeRows as $index=>&$row) {
            if (!empty($row['service_auto_percentage'])) { $automatic[]=$index; continue; }
            $amount=Decimal::of(Decimal::input($row['amount'] ?? 0,'charges.amount'));
            if ($amount->isNegative()) FiscalAmounts::error('charges','El cargo no puede ser negativo.');
            $row['amount']=Decimal::represent($amount);
            $row['amount_exact']=Decimal::fraction($amount);
            $charge=$charge->plus($amount);
        }
        unset($row);
        if (count($automatic)>1) FiscalAmounts::error('charges','Sólo se admite un cargo automático.');
        foreach ($automatic as $index) {
            $row=&$chargeRows[$index];
            if (($row['charge_type_id'] ?? null)!=='50') FiscalAmounts::error('charges','El tipo del cargo automático no es válido.');
            $percentage=Decimal::of(Decimal::input($row['percentage'] ?? null,'charges.percentage'));
            if ($percentage->isNegative() || $percentage->isGreaterThan(100)) FiscalAmounts::error('charges','El porcentaje del cargo debe estar entre 0 y 100.');
            $base=$stats['value']->plus($serviceBase)->minus($discounts['global'])->plus($charge)->plus($iva)->plus($stats['other'])
                ->minus(HeaderAllocation::includesAdvance($discountRows) ? 0 : $prepayment)->minus($cost);
            if ($base->isNegative()) FiscalAmounts::error('charges','La base del cargo no puede ser negativa.');
            $amount=$base->multipliedBy($percentage)->dividedBy(100);
            $row['base']=Decimal::represent($base); $row['amount']=Decimal::represent($amount);
            $row['amount_exact']=Decimal::fraction($amount);
            $charge=$charge->plus($amount); unset($row);
        }
        $value=$stats['value']->plus($serviceBase)->minus($discounts['global'])->plus($charge);
        $total=$value->plus($iva)->plus($stats['other'])->minus(HeaderAllocation::includesAdvance($discountRows) ? 0 : $prepayment);
        if ($value->isNegative() || $total->isNegative()) FiscalAmounts::error('total','El total no puede ser negativo.');
        $values=['total_prepayment'=>$prepayment, 'total_charge'=>$stats['charge']->plus($charge),
            'total_discount'=>$stats['discount']->plus($discounts['global']), 'total_exportation'=>$stats['export_base'],
            'total_free'=>Decimal::of(0), 'total_taxed'=>$taxed->minus($discounts['taxed']),
            'total_unaffected'=>Decimal::of(0), 'total_exonerated'=>$stats['exempt_base']->minus($discounts['exempt']),
            'total_igv'=>$iva, 'total_igv_free'=>Decimal::of(0), 'total_base_other_taxes'=>Decimal::of(0),
            'total_other_taxes'=>$stats['other'], 'total_taxes'=>$iva->plus($stats['other']),
            'total_value'=>$value, 'subtotal'=>$stats['total'], 'total'=>$total->minus($cost)];
        $service->setExactValues($values);
        $snapshot=$service->calculation_snapshot;
        $snapshot['inputs']['service_adjustments']=['discounts'=>$discountRows,'charges'=>$chargeRows];
        $service->calculation_snapshot=$snapshot;
        $service->save();
    }
}
