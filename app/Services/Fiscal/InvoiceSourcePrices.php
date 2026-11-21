<?php

namespace App\Services\Fiscal;

use App\Database\IntegerTransport;
use App\Models\Tenant\{Document, Quotation, SaleNote};
use App\Services\{ExportInvoicePolicy, SalesDocumentCurrency};
use App\Services\ExchangeRates\{ExchangeRateMath, TenantExchangeRateService};
use App\Services\Payments\{ConvertedReceipt, QuotationReceiptsRequired, SaleNoteReceiptsRequired};
use App\Support\Exact\{Decimal as D, HeaderAllocation, LineCalculation};
use App\Support\Venezuela\Localization;
use Modules\Order\Models\OrderNote;
use Modules\Sale\Models\TechnicalService;

// ######## INICIO MONEDA DOCUMENTAL DE VENTAS ########
final class InvoiceSourcePrices
{
    public static function prepare(array $input, array $saleNotes): array
    {
        if (!$saleNotes && empty($input['quotation_id']) && empty($input['order_note_id'])
            && empty($input['technical_service_id'])) {
            return [$input, null];
        }
        if (!SalesDocumentCurrency::usesBolivars()) return [$input, null];

        $sources = $saleNotes ? SaleNote::withoutEagerLoads()->whereKey($saleNotes)->orderBy('id')->lockForUpdate()->get()->all() : [];
        if (!$sources) {
            foreach (['quotation_id' => Quotation::class, 'order_note_id' => OrderNote::class,
                'technical_service_id' => TechnicalService::class] as $field => $model) {
                if (empty($input[$field])) continue;
                $id = IntegerTransport::positiveKey($input[$field]);
                if ($id === null) FiscalAmounts::error($field, 'Seleccione un documento de origen válido.');
                $sources[] = $model::withoutEagerLoads()->lockForUpdate()->findOrFail($id);
                break;
            }
        }

        $lines = collect();
        foreach ($sources as $source) {
            if ((string) $source->customer_id !== (string) ($input['customer_id'] ?? '')
                || (string) $source->establishment_id !== (string) ($input['establishment_id'] ?? '')
                || $source->currency_type_id !== 'VES') {
                FiscalAmounts::error('items', 'El origen debe corresponder al cliente, sucursal y moneda de la factura.');
            }
            $lines = $lines->concat($source->items()->withoutEagerLoads()->orderBy('id')->lockForUpdate()->get());
        }
        $linesByItem = $lines->groupBy('item_id');
        $containsUsd = false;
        foreach ($input['items'] as &$row) {
            $matches = $linesByItem->get($row['item_id'] ?? '', collect());
            $ids = array_map('strval', array_column($row['source_sale_note_items'] ?? [], 'sale_note_item_id'));
            if ($ids && $saleNotes) $matches = $matches->filter(fn ($line) => in_array((string) $line->id, $ids, true));
            if (!$saleNotes && !empty($row['source_item_id'])) {
                $matches = $matches->filter(fn ($line) => (string) $line->id === (string) $row['source_item_id']);
                if ($matches->isEmpty()) FiscalAmounts::error('items', 'La partida indicada no pertenece al documento de origen.');
            }
            if ($matches->isEmpty()) continue;
            $manualPrice = $row['price_input'] ?? null;
            if (($manualPrice['currency_type_id'] ?? null) === 'VES') {
                // An amount entered in Bs. after loading the original price is a new agreement.
                $submittedPrice = LineCalculation::priceInput($manualPrice['amount'] ?? null);
                if (($manualPrice['includes_iva'] ?? true) === false && ($row['affectation_igv_type_id'] ?? '') === '10') {
                    $submittedPrice = $submittedPrice->multipliedBy(D::of(1)->plus(D::of(Localization::exactTaxPercentage())->dividedBy(100)));
                }
                if (!$matches->contains(fn ($line) => D::represent(D::readRequired($line, 'unit_price')) === D::represent($submittedPrice))) continue;
            }
            $prices = $matches->map(static function ($line) {
                return $line->calculation_snapshot['inputs']['price_input'] ?? [
                    'amount' => D::fraction(D::readRequired($line, 'unit_price')),
                    'currency_type_id' => 'VES',
                    'includes_iva' => true,
                ];
            })->uniqueStrict()->values();
            if ($prices->count() > 1 && $prices->contains('currency_type_id', 'USD')) {
                FiscalAmounts::error('items', 'Identifique las partidas de origen que tienen precios distintos.');
            }
            if ($prices->count() !== 1 || $prices->first()['currency_type_id'] !== 'USD') continue;
            $row['price_input'] = $prices->first();
            $containsUsd = $containsUsd || $row['price_input']['currency_type_id'] === 'USD';
        }
        unset($row);
        if (!$containsUsd) return [$input, null];

        $date = $input['date_of_issue'];
        $rate = ExchangeRateMath::rate(app(TenantExchangeRateService::class)->exchange($date)['sale']);
        $input['exchange_rate_sale'] = $rate;
        $input['exchange_rate_date'] = $date;
        $input['exchange_rate_source'] = 'bcv';

        $export = ExportInvoicePolicy::isExport(ExportInvoicePolicy::operation($input));
        foreach ($input['items'] as &$row) {
            $row['currency_type_id'] = 'VES';
            $row['exchange_rate_sale'] = $rate;
            // DocumentInput transports the charge factor. Match that operand in the preview.
            foreach ($row['charges'] ?? [] as $index => $charge) {
                if (isset($charge['factor'])) unset($row['charges'][$index]['percentage']);
            }
        }
        unset($row);
        $items = collect($input['items'])->map(static function (array $row) {
            $taxed = ($row['affectation_igv_type_id'] ?? null) === '10';
            return LineCalculation::fromRecord($row, $taxed ? Localization::exactTaxPercentage() : '0');
        });
        $document = new Document($input);
        $values = ExactDocumentSummary::commercial($document, $items, $export, static function () {});
        $advance = HeaderAllocation::includesAdvance($document->discounts) ? D::of(0) : D::read($document, 'total_prepayment');
        $otherTaxes = D::sum(array_map(static function (array $tax) {
            return D::of(D::input($tax['base'] ?? null, 'taxes.base'))
                ->multipliedBy(D::input($tax['percentage'] ?? null, 'taxes.percentage'))->dividedBy(100);
        }, $input['taxes'] ?? []));
        $total = $values['total_value']->plus($values['total_igv'])->plus($otherTaxes)->minus($advance);
        $received = D::of(0);
        foreach ($input['payments'] ?? [] as $payment) {
            if (ConvertedReceipt::resolve($document, $payment)) {
                $received = $received->plus(D::inputRational($payment['payment'], 'payments'));
            }
        }
        $balance = $total->minus($received);
        if ($balance->isNegative()) {
            FiscalAmounts::error('payments', 'Los abonos superan el nuevo total por Bs. '.D::format($balance->abs(), 2).'.');
        }
        $confirmation = [
            'date' => $date,
            'rate' => $rate,
            'customer' => (string) $input['customer_id'],
            'establishment' => (string) $input['establishment_id'],
            'total' => D::fraction($total),
            'applied' => D::fraction($received),
            'items' => $items->map(function ($line) use ($input, $linesByItem) {
                $id = $line['item_id'] ?? '';
                // The service controller creates its labor item only on submission.
                if (!empty($input['technical_service_id']) && !$linesByItem->has($id)) $id = '';
                return [(string) $id, $line['calculation_snapshot']['values']];
            })->all(),
            'sources' => array_map(fn ($source) => [$source->getTable(), (string) $source->id], $sources),
        ];
        return [$input, [
            'rate' => $rate,
            'source_total' => D::format(D::sum(array_map(fn ($source) => D::read($source, 'total'), $sources)), 2),
            'total' => D::represent($total, 2),
            'applied' => D::represent($received, 2),
            'balance' => D::represent($balance, 2),
            'exact_total' => D::fraction($total),
            'confirmation' => hash_hmac('sha256', json_encode($confirmation, JSON_THROW_ON_ERROR), config('app.key')),
        ]];
    }

    public static function preview(array $input): ?array
    {
        SalesDocumentCurrency::validate($input['currency_type_id'] ?? null);
        return (new Document())->getConnection()->transaction(function () use ($input) {
            $input = PrepaymentReference::prepare($input);
            $notes = SaleNoteReceiptsRequired::lockOwners($input);
            QuotationReceiptsRequired::assertPresent($input['quotation_id'] ?? null, $input['payments'] ?? []);
            SaleNoteReceiptsRequired::assertPresent($notes, $input['payments'] ?? []);
            return self::prepare($input, $notes)[1];
        });
    }
}
// ######## FIN MONEDA DOCUMENTAL DE VENTAS ########
