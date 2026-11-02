<?php

namespace App\Services\Payments;

use App\Models\Tenant\{Cash, SaleNote, SaleNotePayment};
use App\Support\Exact\Decimal as D;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Bind a hotel item to an existing sale-note receipt, without receiving it twice. */
final class HotelReceipt
{
    public static function findSource(int $saleNoteId, $rent, array $input): SaleNotePayment
    {
        validator($input, ['payment'=>'required|numeric|gt:0', 'original_amount'=>'nullable|numeric|gt:0',
            'exchange_rate'=>'nullable|numeric|gt:0'])->validate();
        $note = SaleNote::query()->withoutEagerLoads()->lockForUpdate()->findOrFail($saleNoteId);
        if ((string) $note->customer_id !== (string) $rent->customer_id || (string) $note->establishment_id !== (string) $rent->establishment_id
            || $note->currency_type_id !== $rent->getDefaultCurrency() || $note->isVoidedOrRejected()) {
            Classification::error('payment', 'La nota de venta no corresponde al alojamiento o no está vigente.');
        }
        $currency = $input['currency_type_id'] ?? $note->currency_type_id;
        $query = SaleNotePayment::query()->where('sale_note_id', $note->id)
            ->where('payment_method_type_id', $input['payment_method_type_id'] ?? '')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('hotel_rent_item_payments')
                    ->whereColumn('hotel_rent_item_payments.source_sale_note_payment_id', 'sale_note_payments.id');
            })->orderBy('id')->lockForUpdate();
        foreach ($query->get() as $source) {
            if (($source->currency_type_id ?? $note->currency_type_id) !== $currency) continue;
            $tenderedApplied = D::readRequired($source, 'payment')->plus(D::read($source, 'change'));
            if (!$tenderedApplied->isEqualTo(D::of($input['payment'] ?? '0'))) continue;
            $original = $input['original_amount'] ?? ($currency === $note->currency_type_id ? ($input['payment'] ?? null) : null);
            $tenderedOriginal = data_get($source->calculation_snapshot, 'values.tendered_original_amount');
            $tenderedOriginal = $tenderedOriginal === null ? D::readRequired($source, 'original_amount') : D::of($tenderedOriginal);
            if ($original === null || !$tenderedOriginal->isEqualTo(D::of($original))) continue;
            if ($currency !== $note->currency_type_id && (!isset($input['exchange_rate']) || \App\Services\ExchangeRates\ExchangeRateMath::rate($input['exchange_rate']) !== \App\Services\ExchangeRates\ExchangeRateMath::rate($source->exchange_rate))) continue;
            if ((string) $source->reference !== (string) ($input['reference'] ?? '')) continue;
            $global = $source->global_payment;
            if (!$global || !Movement::row($global)) continue;
            $destination = $global->destination_type === Cash::class ? 'cash' : (string) $global->destination_id;
            if ((string) ($input['payment_destination_id'] ?? '') !== $destination) continue;
            return $source;
        }
        Classification::error('payment', 'No se encontró un cobro disponible de la nota que coincida con el medio, importe, moneda, tasa y destino.');
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
