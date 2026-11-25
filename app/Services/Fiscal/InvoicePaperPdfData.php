<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Services\ExchangeRates\ExchangeRateMath;
use App\Services\Payments\ReceiptPresentation;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\PdfAmounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

// ######## INICIO PRESENTACION FACTURA A4/A5 ########
/** Read-only presentation shared by local A4/A5 invoices, including unsaved previews. */
final class InvoicePaperPdfData
{
    public static function forDocument($document, $company): array
    {
        $persisted = $document instanceof Document && $document->exists;
        $source = self::relation($document, 'invoice_source_document');
        $ledger = $persisted ? $document : ($source instanceof Document && $source->exists ? $source : null);
        if ($persisted) {
            $document->loadMissing(['payments.change_disbursements', 'taxes', 'received_retentions',
                'fiscal_data', 'guarantee_fund', 'currency_totals']);
        }
        $currency = $document->currency_type_id;
        $activePayments = self::rows($document, 'payments')->filter(fn ($p) => !$p->reversed_at);
        $payments = $activePayments->filter(fn ($p) => !$p->receipt_parent_id);
        $retentions = self::rows($document, 'received_retentions')->filter(fn ($r) =>
            !$r->reversed_at && (!$r->application_status || $r->application_status === 'applied')
            && D::read($r, 'applied_amount')->isPositive());
        $taxes = self::rows($document, 'taxes')->where('tax_kind', 'IGTF');
        $fiscal = self::relation($document, 'fiscal_data');
        $receipts = [];
        $changes = [];
        foreach ($payments as $payment) {
            $tender = ReceiptPresentation::tender($payment);
            $snapshot = $payment->calculation_snapshot ?? [];
            if (is_string($snapshot)) $snapshot = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
            $gross = data_get($snapshot, 'values.total_tendered');
            if ($gross === null && $tender['tendered_original_amount'] !== null) {
                // The frozen original tender excludes the separately collected IGTF.
                $gross = D::of(data_get($snapshot, 'values.tendered_original_amount'))->plus(D::read($payment, 'tax_amount'));
            }
            $receivedChange = data_get($snapshot, 'values.received_change');
            // Without a gross snapshot, report the known net rather than inventing tender.
            $knownNet = $payment->original_amount !== null;
            $receipts[] = [
                'currency' => $payment->currency_type_id,
                'amount' => $gross !== null ? D::format($gross) : ($knownNet ? D::format(D::read($payment, 'original_amount')->plus(D::read($payment, 'tax_amount'))) : null),
                'net' => $gross === null && ($tender['received_change'] !== null || D::read($payment, 'change')->isPositive()),
                'previous' => !empty($payment->payment_method_snapshot['receipt_origin']),
                'applied' => D::format(D::read($payment, 'payment')),
                'different_currency' => $payment->currency_type_id !== $currency,
                'rate' => ExchangeRateMath::optionalRate($payment->exchange_rate),
            ];
            $disbursements = self::rows($payment, 'change_disbursements')->filter(fn ($r) => !$r->reversed_at);
            if ($disbursements->isNotEmpty()) {
                foreach ($disbursements as $row) self::addChange($changes, $row->currency_type_id, D::read($row, 'original_amount'), false);
            } elseif ($receivedChange !== null) {
                self::addChange($changes, $payment->currency_type_id, D::of($receivedChange), true);
            } elseif (D::read($payment, 'change')->isPositive() && $payment->currency_type_id === $currency) {
                self::addChange($changes, $currency, D::read($payment, 'change'), true);
            }
        }
        $forecasts = [];
        foreach (['IVA' => 'iva_retention_prevision', 'ISLR' => 'islr_retention_prevision'] as $kind => $field) {
            $forecast = data_get($fiscal, $field);
            if (!$forecast || empty($forecast['eligible'])) continue;
            $amount = D::readRequired($forecast, 'amount');
            $applied = D::sum($retentions->where('tax_kind', $kind)->map(fn ($r) => D::read($r, 'applied_amount')));
            $remaining = D::max(0, $amount->minus($applied));
            if ($remaining->isPositive()) $forecasts[] = ['kind' => $kind, 'amount' => D::format($amount),
                'remaining' => D::format($remaining), 'percentage' => self::percentage(D::read($forecast, 'percentage'))];
        }
        $fund = self::relation($document, 'guarantee_fund');
        $guarantee = $ledger ? $ledger->exactGuaranteeAmount() : ($fund ? D::read($fund, 'amount') : D::of(0));
        $balance = null;
        if ($retentions->isNotEmpty() || $forecasts || in_array((string) $document->payment_condition_id, ['02', '03'], true)) {
            if ($ledger) {
                $balance = $ledger->exactBalance();
                if (!$persisted) $balance = $balance->plus(D::read($document, $document->total_payable !== null ? 'total_payable' : 'total'))
                    ->minus(D::read($ledger, $ledger->total_payable !== null ? 'total_payable' : 'total'));
            } else {
                // Persistence settles collected IGTF with a derived application, not another receipt.
                $taxApplications = D::sum($payments->map(function ($payment) use ($activePayments, $currency) {
                    if (!D::read($payment, 'tax_amount')->isPositive()
                        || ($payment->id && $activePayments->contains(fn ($p) => $p->receipt_parent_id == $payment->id))) return D::of(0);
                    return D::of(D::represent(D::convert(D::read($payment, 'tax_amount'), $payment->currency_type_id,
                        $currency, $payment->exchange_rate), 2));
                }));
                $balance = D::read($document, $document->total_payable !== null ? 'total_payable' : 'total')
                    ->minus(D::sum($activePayments->map(fn ($p) => D::read($p, 'payment'))))->minus($taxApplications)
                    ->minus(D::sum($retentions->map(fn ($r) => D::read($r, 'applied_amount'))))->minus($guarantee);
            }
        }
        $vesTotals = $currency === 'USD' ? self::relation($document, 'currency_totals') : null;
        if (!$persisted && $currency === 'USD') {
            $rate = ExchangeRateMath::optionalRate($document->exchange_rate_sale);
            $vesTotals = $rate ? ['calculation_snapshot' => D::snapshot([
                'iva' => D::convert(D::read($document, 'total_igv'), 'USD', 'VES', $rate),
                'total' => D::convert(D::read($document, 'total'), 'USD', 'VES', $rate),
            ])] : null;
        }
        return [
            'rif' => self::rif((string) $company->number),
            'number' => FiscalIdentity::numberFull($document->series ?? '', $document->number ?? 0),
            'number_without_series' => FiscalIdentity::displayNumber($document->number ?? 0),
            'control' => $document->control_number ?: 'No asignado',
            'rate' => ExchangeRateMath::optionalRate($document->exchange_rate_sale),
            'environment' => $document->fiscal_environment ?? null,
            'currency' => $currency, 'receipts' => $receipts,
            'changes' => array_map(fn ($r) => array_replace($r, ['amount' => D::format($r['amount'])]), array_values($changes)),
            'igtf' => self::taxes($document, $taxes),
            'retentions' => $retentions->map(fn ($r) => self::retention($r, $currency))->values()->all(),
            'forecasts' => $forecasts, 'balance' => $balance === null ? null : D::format($balance),
            'historical_forecasts' => !$persisted && $ledger !== null,
            'guarantee' => $guarantee->isPositive() ? D::format($guarantee) : null,
            'ves_totals' => $vesTotals,
            'later_igtf' => $ledger ? self::laterIgtf($ledger) : [],
        ];
    }

    public static function rif(string $value): string
    {
        $plain = preg_replace('/[.\s-]/', '', strtoupper($value));
        return preg_match('/^([VJEGPCR])(\d{8})(\d)$/', $plain, $parts)
            ? $parts[1].'-'.$parts[2].'-'.$parts[3] : $value;
    }

    private static function relation($record, string $name)
    {
        if ($record instanceof Model && !$record->exists && !$record->relationLoaded($name)) return null;
        return $record->$name;
    }

    private static function rows($record, string $name): Collection { return collect(self::relation($record, $name) ?? []); }

    private static function addChange(array &$changes, string $currency, $amount, bool $equivalent): void
    {
        if (!$amount->isPositive()) return;
        $key = $currency.($equivalent ? ':equivalent' : ':actual');
        if (!isset($changes[$key])) $changes[$key] = ['currency' => $currency, 'amount' => D::of(0), 'equivalent' => $equivalent];
        $changes[$key]['amount'] = $changes[$key]['amount']->plus($amount);
    }

    private static function percentage($value): string
    {
        $parts = explode('.', D::represent($value), 2);
        return $parts[0].'.'.str_pad($parts[1] ?? '', 2, '0');
    }

    private static function taxes($document, Collection $taxes): array
    {
        if ($taxes->isEmpty()) return [];
        $groups = $taxes->groupBy(fn ($row) => D::represent(D::read($row, 'percentage')));
        $weights = $groups->map(fn ($rows) => D::sum($rows->map(fn ($row) => D::read($row, 'amount'))))->all();
        $sum = D::sum($weights);
        $budget = D::read($document, 'total')->minus($sum)->isEqualTo(D::read($document, 'subtotal'))
            ? PdfAmounts::read($document, 'total')->minus(PdfAmounts::read($document, 'subtotal')) : $sum;
        $amounts = D::allocate($budget, array_values($weights), 2);
        return array_map(fn ($rate, $amount) => ['percentage' => self::percentage($rate), 'amount' => D::format($amount)],
            array_keys($weights), $amounts);
    }

    private static function retention($row, string $documentCurrency): array
    {
        $currency = $row->currency_type_id ?: $documentCurrency;
        $details = [];
        foreach ($row->tax_kind === 'IVA' ? ['taxable_base' => 'Base imponible', 'base' => 'IVA sujeto'] : ['base' => 'Base', 'subtrahend' => 'Sustraendo'] as $field => $label) {
            if ($row->$field !== null) $details[] = $label.': '.$currency.' '.D::format(D::read($row, $field));
        }
        return ['kind' => $row->tax_kind, 'number' => $row->voucher_number,
            'date' => $row->voucher_date ? \Carbon\Carbon::parse($row->voucher_date)->format('d/m/Y') : null,
            'percentage' => $row->percentage !== null ? self::percentage(D::read($row, 'percentage')) : null,
            'currency' => $currency, 'amount' => $row->amount !== null ? D::format(D::read($row, 'amount')) : null,
            'applied' => D::format(D::read($row, 'applied_amount')), 'details' => $details];
    }

    private static function laterIgtf(Document $document): array
    {
        $db = $document->getConnection();
        $rows = $db->table('document_taxes as t')->join('document_payments as p', 'p.id', '=', 't.document_payment_id')
            ->join('documents as nd', 'nd.id', '=', 't.document_id')->join('notes as n', 'n.document_id', '=', 'nd.id')
            ->join('documents as origin', 'origin.id', '=', 'p.document_id')
            ->leftJoin('document_emissions as e', 'e.document_id', '=', 'nd.id')
            ->whereIn('p.document_id', FiscalNote::collectionDocumentIds($document))
            ->whereNull('p.receipt_parent_id')->whereNull('p.reversed_at')->where('t.tax_kind', 'IGTF')
            ->where('n.affected_document_id', $document->id)->where('n.note_debit_type_id', 'IGTF')
            ->where('nd.document_type_id', '08')->whereNotIn('nd.state_type_id', ['09', '11'])
            ->where('nd.customer_id', $document->customer_id)->where('nd.establishment_id', $document->establishment_id)
            ->orderBy('nd.id')->orderBy('t.id')
            ->select(['t.*', 'nd.series', 'nd.number', 'nd.control_number', 'nd.currency_type_id', 'nd.state_type_id', 'nd.fiscal_emission_mode',
                'e.status as emission_status', 'origin.series as origin_series', 'origin.number as origin_number', 'p.document_id as origin_id'])->get();
        if ($rows->isEmpty()) return [];
        $adjustments = $db->table('document_note_adjustments as a')->join('documents as nc', 'nc.id', '=', 'a.document_id')
            ->where('a.affected_document_id', $document->id)->where('a.kind', 'IGTF')->whereIn('a.tax_id', $rows->pluck('id'))
            ->select(['a.*', 'nc.series', 'nc.number'])->get();
        $refunds = $db->table('document_note_refunds')->whereIn('adjustment_id', $adjustments->pluck('id'))
            ->where('component', 'IGTF')->orderBy('id')->get()->groupBy('adjustment_id');
        $statusLabels = ['not_requested' => 'Sin enviar', 'prepared' => 'Preparada', 'pending' => 'Enviando',
            'confirmed' => 'Confirmada', 'rejected' => 'Rechazada', 'uncertain' => 'Por conciliar', 'cancelled' => 'Anulada'];
        return $rows->map(function ($row) use ($document, $adjustments, $refunds, $statusLabels) {
            return ['number' => FiscalIdentity::numberFull($row->series, $row->number), 'control' => $row->control_number ?: 'No asignado',
                'currency' => $row->currency_type_id, 'amount' => D::format(D::read($row, 'amount')),
                'status' => $row->state_type_id === '13' ? 'Por anular' : ($row->fiscal_emission_mode === 'digital'
                    ? ($statusLabels[$row->emission_status] ?? 'Sin enviar') : 'Registrada localmente'),
                'origin' => $row->origin_id == $document->id ? 'Factura' : 'ND '.FiscalIdentity::numberFull($row->origin_series, $row->origin_number),
                'adjustments' => $adjustments->where('tax_id', $row->id)->map(fn ($a) => [
                    'number' => FiscalIdentity::numberFull($a->series, $a->number),
                    'status' => $a->reversed_at ? 'Revertido' : (['blocked'=>'Bloqueado', 'pending'=>'Pendiente',
                        'confirmed'=>'Confirmado', 'applied'=>'Aplicado', 'cancelled'=>'Anulado', 'rejected'=>'Rechazado'][$a->status] ?? 'Pendiente'),
                    'amount' => D::format(D::read($a, 'amount')), 'currency' => $document->currency_type_id,
                    'refunds' => ($refunds[$a->id] ?? collect())->map(fn ($r) => ['amount' => D::format(D::read($r, 'original_amount')),
                        'currency' => $r->currency_type_id, 'reversed' => $r->reversed_at !== null])->all(),
                ])->all()];
        })->all();
    }
}
// ######## FIN PRESENTACION FACTURA A4/A5 ########
