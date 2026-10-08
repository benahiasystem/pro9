<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Services\ExchangeRates\ExchangeRateMath;

final class DocumentEditSettlements
{
    /** Never recreate collections from the editor's serialized rows. */
    public static function validateInput(Document $document, array $inputs): void
    {
        if (($inputs['currency_type_id'] ?? $document->currency_type_id) !== $document->currency_type_id) {
            FiscalAmounts::error('currency_type_id', 'La edición conserva la moneda del comprobante.');
        }
        foreach ($inputs['payments'] ?? [] as $row) {
            $payment = $document->payments()->find($row['id'] ?? 0);
            if (!$payment) FiscalAmounts::error('payments', 'Los cobros se administran desde Pagos, no desde la edición.');
            foreach (['payment', 'original_amount', 'tax_amount', 'change', 'exchange_rate', 'cash_received_amount', 'currency_type_id',
                'payment_method_type_id', 'igtf_status', 'exemption_reason', 'reference', 'operation_key', 'receipt_parent_id'] as $field) {
                if (!array_key_exists($field, $row)) continue;
                $stored = $payment->$field;
                $value = $row[$field];
                $same = is_numeric($stored) && is_numeric($value)
                    ? ExchangeRateMath::rational($stored)->isEqualTo(ExchangeRateMath::rational($value))
                    : (string) $stored === (string) $value;
                if (!$same) FiscalAmounts::error('payments', 'No se pueden modificar los cobros desde la edición.');
            }
            if (isset($row['date_of_payment']) && substr((string) $row['date_of_payment'], 0, 10) !== $payment->date_of_payment->format('Y-m-d')) {
                FiscalAmounts::error('payments', 'No se puede modificar la fecha de un cobro desde la edición.');
            }
            if (array_key_exists('payment_destination_id', $row)) {
                $global = $payment->global_payment;
                $destination = $global && $global->destination_type === \App\Models\Tenant\Cash::class ? 'cash' : ($global->destination_id ?? null);
                if ((string) $row['payment_destination_id'] !== (string) $destination) FiscalAmounts::error('payments', 'No se puede cambiar el destino de un cobro desde la edición.');
            }
            if (!empty($row['filename']) || !empty($row['temp_path'])) FiscalAmounts::error('payments', 'Los adjuntos de cobro se administran desde Pagos.');
        }
        if (!empty($inputs['received_retentions']) || !empty($inputs['guarantee_fund'])) {
            FiscalAmounts::error('document', 'Las retenciones y fondos existentes se conservan; utilice sus acciones específicas.');
        }
        if ($document->received_retentions()->exists() && (int) ($inputs['customer_id'] ?? $document->customer_id) !== (int) $document->customer_id) {
            FiscalAmounts::error('customer_id', 'Conserve el cliente asociado a las retenciones registradas.');
        }
    }

    public static function validateTotals(Document $document): void
    {
        if ($document->fresh()->balance < -0.005) FiscalAmounts::error('total', 'El total no puede ser inferior al importe ya aplicado.');
        $retentions = $document->received_retentions()->where('tax_kind', 'IVA')->get();
        foreach ($retentions as $retention) {
            $base = FiscalAmounts::convert($retention->base, $retention->currency_type_id, $document->currency_type_id, $retention->exchange_rate);
            if ($base > $document->total_igv + 0.005) FiscalAmounts::error('total_igv', 'El nuevo IVA no respalda la base de la retención registrada.');
        }
        if ($retentions->sum('applied_amount') > $document->total_igv + 0.005) FiscalAmounts::error('total_igv', 'El nuevo IVA es inferior a las retenciones aplicadas.');
        $document->forceFill(['total_canceled' => $document->fresh()->balance <= 0.005])->save();
    }
}
