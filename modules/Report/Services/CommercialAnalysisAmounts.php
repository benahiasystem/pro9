<?php

namespace Modules\Report\Services;

use App\Support\Exact\Decimal as D;
use Carbon\Carbon;

/** Sales and quantities remain separated by documentary currency and frozen unit. */
final class CommercialAnalysisAmounts
{
    public static function row($customer, iterable $categories): array
    {
        $documents = $customer->documents->filter(fn ($doc) => in_array($doc->document_type_id, ['01','07','08'], true)
            && !in_array($doc->state_type_id, ['09','11'], true))->values();
        $invoices = $documents->where('document_type_id','01')->sortBy(fn ($doc) => $doc->date_of_issue->format('Y-m-d').'|'.str_pad((string)$doc->id,20,'0',STR_PAD_LEFT))->values();
        $first = $invoices->first(); $last = $invoices->last();
        $days = D::of(0);
        for ($i=1; $i<$invoices->count(); $i++) $days=$days->plus((string)$invoices[$i-1]->date_of_issue->diffInDays($invoices[$i]->date_of_issue));
        $average = $invoices->count()>1 ? $days->dividedBy((string)($invoices->count()-1)) : D::of(0);
        $totals = ['VES'=>D::of(0), 'USD'=>D::of(0)]; $quantities = [];
        foreach ($documents as $doc) {
            $currency=$doc->currency_type_id ?: 'Sin moneda';
            $totals[$currency] = ($totals[$currency] ?? D::of(0))->plus($doc->document_type_id === '07' ? D::read($doc,'total')->negated() : D::read($doc,'total'));
            $physical = $doc->document_type_id === '01' || ($doc->document_type_id === '07' && in_array(optional($doc->note)->note_credit_type_id, ['01','07'], true));
            if (!$physical) continue;
            foreach ($doc->items as $item) {
                $category=optional(optional($item->relation_item)->category)->name;
                if (!$category) continue;
                $name=mb_strtoupper($category); $unit=($item->item->unit_type_id ?? null) ?: 'Sin unidad';
                $quantity=D::read($item,'quantity'); if ($doc->document_type_id === '07') $quantity=$quantity->negated();
                $quantities[$name][$unit]=($quantities[$name][$unit] ?? D::of(0))->plus($quantity)->simplified();
            }
        }
        $values=['total_ves'=>$totals['VES'], 'total_usd'=>$totals['USD'], 'average_days'=>$average];
        $categoryDisplay=[]; $fractions=[];
        foreach ($categories as $category) {
            $name=mb_strtoupper($category); $parts=[]; $units=$quantities[$name] ?? []; ksort($units);
            foreach ($units as $unit=>$quantity) {
                $parts[]=D::represent($quantity).' '.$unit;
                $fractions[]=['id'=>'customer:'.$customer->id, 'field'=>'category:'.$name.':'.$unit, 'value'=>D::fraction($quantity)];
            }
            $categoryDisplay[$name]=implode('; ', $parts);
        }
        $other=[];
        foreach ($totals as $currency=>$amount) if (!in_array($currency,['VES','USD'],true)) {
            $other[$currency]=D::represent($amount);
            $fractions[]=['id'=>'customer:'.$customer->id,'field'=>'total:'.$currency,'value'=>D::fraction($amount)];
        }
        $zone=collect([optional($customer->country)->description,optional($customer->department)->description,
            optional($customer->province)->description,optional($customer->district)->description])->filter()->implode(' - ');
        $result=['id'=>$customer->id, 'report_key'=>'customer:'.$customer->id, 'number'=>$customer->number_full,
            'customer_name'=>$customer->name, 'customer_doc'=>optional($customer->identity_document_type)->description,
            'customer_number'=>format_person_identity_document($customer), 'zone'=>$zone, 'telephone'=>$customer->telephone,
            'first_document'=>$first ? $first->number_full : '-', 'last_document'=>$last ? $last->number_full : '-',
            'first_document_date'=>$first ? $first->date_of_issue->format('d-m-Y') : null,
            'last_document_date'=>$last ? $last->date_of_issue->format('d-m-Y') : null,
            'prom_difference_days'=>D::format($average,2,'.',''), 'quantity_visit'=>$invoices->count(),
            'contact_date'=>$last ? Carbon::parse($last->date_of_issue)->addDays((int)D::format($average,0,'.',''))->format('d-m-Y') : null,
            'other_currencies'=>$other, 'category_fractions'=>$fractions, 'calculation_snapshot'=>D::snapshot($values)];
        return array_merge($result,D::values($values),[
            'total_ves'=>D::format($values['total_ves'],2,'.',''),
            'total_usd'=>D::format($values['total_usd'],2,'.',''),
        ],$categoryDisplay);
    }
}
