<?php

namespace App\Services\Payments;

use App\Models\Tenant\{Cash, DocumentPayment, Quotation, SaleNote, SaleNotePayment};
use App\Services\ExchangeRates\ExchangeRateMath;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\SaleNoteBalance;
use Modules\Sale\Models\{QuotationPayment, TechnicalService, TechnicalServicePayment};

/** Validate and apply an existing commercial receipt without receiving money again. */
final class CommercialSourceReceipt
{
    private const SOURCES = [
        'quotation' => [Quotation::class, QuotationPayment::class, 'quotation_id', 'source_quotation_payment_id', 'cotización'],
        'technical_service' => [TechnicalService::class, TechnicalServicePayment::class, 'technical_service_id', 'source_technical_service_payment_id', 'servicio técnico'],
    ];

    public static function resolve($target, array $input, string $kind): ?array
    {
        [$ownerClass, $paymentClass, $ownerField, $sourceField, $label] = self::SOURCES[$kind];
        $id = $input[$sourceField] ?? (!empty($input[$ownerField]) ? ($input['id'] ?? null) : null);
        if ($id === null) return null;
        $owner = $ownerClass::withoutEagerLoads()->lockForUpdate()->find($target->{$ownerField});
        $source = $paymentClass::withoutEagerLoads()->where($ownerField, $owner?->id)->lockForUpdate()->find($id);
        if (!$owner || !$source || ($kind === 'quotation' && $owner->isVoidedOrRejected())
            || (isset($input[$ownerField]) && (string)$input[$ownerField] !== (string)$owner->id)
            || (string)$owner->customer_id !== (string)$target->customer_id
            || (string)$owner->establishment_id !== (string)$target->establishment_id
            || $owner->currency_type_id !== $target->currency_type_id) {
            Classification::error($sourceField, "El cobro no pertenece al origen de {$label} del comprobante.");
        }
        $currency = $source->currency_type_id;
        if (!in_array($currency, ['VES', 'USD'], true)
            || (string)$source->payment_method_type_id !== (string)($input['payment_method_type_id'] ?? '')
            || !D::readRequired($source, 'payment')->isEqualTo(D::of(D::input($input['payment'] ?? null, $sourceField)))
            || $source->date_of_payment->format('Y-m-d') !== ($input['date_of_payment'] ?? '')
            || $currency !== ($input['currency_type_id'] ?? null)
            || (string)$source->reference !== (string)($input['reference'] ?? '')) {
            Classification::error($sourceField, 'El cobro de origen no coincide con el medio, importe, fecha, referencia o moneda.');
        }
        if (!isset($input['original_amount'], $input['exchange_rate'])
            || !D::readRequired($source, 'original_amount')->isEqualTo(D::of(D::input($input['original_amount'], $sourceField)))
            || ExchangeRateMath::rate($source->exchange_rate) !== ExchangeRateMath::rate($input['exchange_rate'])) {
            Classification::error($sourceField, 'El importe recibido o la tasa no coinciden con el cobro original.');
        }
        $global = $source->global_payment;
        if (!$global || ($global->report_context['version'] ?? null) !== 2 || !empty($global->report_context['reversal'])) {
            Classification::error($sourceField, 'El cobro de origen no tiene un movimiento vigente.');
        }
        $destination = $global->destination_type === Cash::class ? 'cash' : (string)$global->destination_id;
        if ((string)($input['payment_destination_id'] ?? '') !== (string)$destination) {
            Classification::error($sourceField, 'El destino no coincide con el cobro original.');
        }
        self::assertUnused($source, $kind);
        return ['type'=>$paymentClass, 'id'=>(string)$source->id, $ownerField=>(string)$owner->id,
            'reference'=>$source->reference,
            'destination_id'=>$destination, 'destination_label'=>$global->report_context['destination_label'] ?? null,
            'exchange_rate_source'=>$source->exchange_rate_source,
            'exchange_rate_date'=>$source->exchange_rate_date?->format('Y-m-d'),
            'method_snapshot'=>$source->payment_method_snapshot ?? $global->report_context['method'] ?? null];
    }

    /** Source row and its owner must already be locked by the caller. */
    public static function assertUnused($source, string $kind): void
    {
        [, $paymentClass, , $sourceField, $label] = self::SOURCES[$kind];
        if (!$source instanceof $paymentClass) throw new \LogicException('Tipo de cobro de origen incorrecto.');
        $usedNote = SaleNotePayment::withoutEagerLoads()
            ->where('payment_method_snapshot->receipt_origin->type', $paymentClass)
            ->where('payment_method_snapshot->receipt_origin->id', (string)$source->id)
            ->lockForUpdate()->get(['id'])->isNotEmpty();
        if ($usedNote) Classification::error($sourceField, "El cobro de {$label} ya fue aplicado a otro comprobante.");
        $usedDocument = DocumentPayment::withoutEagerLoads()
            ->where('payment_method_snapshot->receipt_origin->type', $paymentClass)
            ->where('payment_method_snapshot->receipt_origin->id', (string)$source->id)
            ->whereNull('reversed_at')->lockForUpdate()->get(['id'])->isNotEmpty();
        if ($usedDocument) Classification::error($sourceField, "El cobro de {$label} ya fue aplicado a otro comprobante.");
    }

    public static function applyToSaleNote(SaleNote $note, array $input, string $kind): SaleNotePayment
    {
        if ($note->getConnection()->transactionLevel() === 0) throw new \LogicException('La aplicación de un cobro requiere una transacción.');
        [, $paymentClass, , $sourceField] = self::SOURCES[$kind];
        $origin = self::resolve($note, $input, $kind);
        if (!$origin) Classification::error($sourceField, 'Identifique el cobro original.');
        $source = $paymentClass::withoutEagerLoads()->lockForUpdate()->findOrFail($origin['id']);
        $amount = D::readRequired($source, 'payment');
        if ($amount->isGreaterThan(SaleNoteBalance::values($note, true)['total_difference'])) {
            Classification::error($sourceField, 'El cobro de origen supera el saldo de la nota de venta.');
        }
        $method = $origin['method_snapshot'] ?? [];
        unset($origin['method_snapshot']);
        $snapshot = $source->calculation_snapshot ?? D::snapshot(['payment'=>$amount, 'original_amount'=>D::readRequired($source, 'original_amount')]);
        unset($snapshot['inputs']['tendered_payment'], $snapshot['inputs']['tender_position'],
            $snapshot['values']['tendered_amount'], $snapshot['values']['tendered_original_amount'], $snapshot['values']['received_change']);
        $snapshot['values']['change'] = D::fraction(D::of(0));
        $snapshot['values']['requested_payment'] = D::fraction($amount);
        $snapshot['values']['cash_adjustment'] = D::fraction(D::of(0));
        $payment = $note->payments()->make([
            'date_of_payment'=>$source->date_of_payment, 'payment_method_type_id'=>$source->payment_method_type_id,
            'reference'=>$source->reference, 'payment'=>D::represent($amount), 'change'=>'0',
            'currency_type_id'=>$source->currency_type_id, 'original_amount'=>D::represent(D::readRequired($source, 'original_amount')),
            'exchange_rate'=>$source->exchange_rate, 'exchange_rate_source'=>$source->exchange_rate_source,
            'exchange_rate_date'=>$source->exchange_rate_date, 'payment_method_snapshot'=>array_merge($method, ['receipt_origin'=>$origin]),
        ]);
        $payment->calculation_snapshot = $snapshot;
        $payment->save();
        return $payment;
    }
}
