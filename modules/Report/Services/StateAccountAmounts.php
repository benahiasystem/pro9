<?php

namespace Modules\Report\Services;

use App\Models\Tenant\Document;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\Receivables;

/** Amounts are kept in document currency, with original fractions for exports. */
final class StateAccountAmounts
{
    public const FIELDS = ['total_charge','total_exonerated','total_taxed','total_discount','total_igv','total','payment_state'];

    public static function saleNoteBalance($note)
    {
        $payable = $note->total_payable === null ? D::readRequired($note, 'total') : D::readRequired($note, 'total_payable');
        return $payable->minus(D::sum($note->payments->map(fn ($payment) => D::readRequired($payment, 'payment'))));
    }

    public static function activeLinkedInvoices($records)
    {
        $ids = collect($records)->filter(fn ($row) => !($row instanceof Document))->pluck('document_id')->filter()->unique();
        return $ids->isEmpty() ? collect() : Document::withoutEagerLoads()->whereIn('id', $ids)->where('document_type_id', '01')->whereStateTypeAccepted()->pluck('id');
    }

    public static function presentation($records)
    {
        $rows = self::rows($records);
        $display = [];
        foreach ($rows->where('included_in_totals', true)->groupBy('currency_type_id') as $group) {
            $plain = $group->map(function ($row) { unset($row['model']); return $row; });
            $projected = \App\Support\Exact\ReportProjection::money(new \App\Support\Exact\ReportDataset($plain), self::FIELDS);
            foreach ($projected as $row) $display[$row['report_key']] = $row['display'];
        }
        return $rows->map(function ($row) use ($display) {
            $row['display'] = $display[$row['report_key']] ?? [];
            return $row;
        });
    }

    public static function rows($records)
    {
        $records = collect($records);
        $linked = self::activeLinkedInvoices($records);
        $present = $records->filter(fn ($row) => $row instanceof Document)->pluck('id');
        $balances = [];
        foreach ($records->filter(fn ($row) => $row instanceof Document)->chunk(500) as $chunk) {
            $balances += Receivables::balancesForDocuments($chunk);
        }
        return $records->map(function ($row) use ($balances, $linked, $present) {
            $document = $row instanceof Document;
            $type = $document ? $row->document_type_id : '80';
            $void = in_array($row->state_type_id, ['09','11'], true);
            $values = [];
            foreach (array_diff(self::FIELDS, ['payment_state']) as $field) {
                $amount = $void ? D::of(0) : D::read($row, $field);
                $values[$field] = $type === '07' ? $amount->negated() : $amount;
            }
            $converted = !$document && $row->document_id && $linked->contains($row->document_id);
            $values['payment_state'] = ($void || $converted) ? D::of(0) : ($document ? ($balances[$row->id] ?? D::of(0)) :
                self::saleNoteBalance($row));
            return array_merge(['report_key'=>($document ? 'document:' : 'sale_note:').$row->id,
                'model'=>$row, 'document_type_id'=>$type, 'currency_type_id'=>$row->currency_type_id,
                'converted'=>$converted, 'included_in_totals'=>!($converted && $present->contains($row->document_id)),
                'calculation_snapshot'=>D::snapshot($values)], D::values($values));
        })->values();
    }
}
