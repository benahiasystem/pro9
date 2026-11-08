<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Services\ExchangeRates\ExchangeRateMath;
use App\Support\Exact\Decimal as D;

final class DocumentEditSettlements
{
    /** Only snapshots read from this invoice may restore unchanged exact values. */
    public static function restoreLineSnapshots(Document $document, array $rows): array
    {
        $existing=$document->items()->lockForUpdate()->get()->keyBy('id');
        $used=[];
        foreach ($rows as &$row) {
            $id=$row['record_id'] ?? $row['id'] ?? null;
            unset($row['calculation_snapshot']);
            if ($id === null || $id === '') continue;
            if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1 || isset($used[(int)$id]))
                FiscalAmounts::error('items','Las partidas guardadas deben identificarse una sola vez.');
            $source=$existing->get((int)$id);
            if (!$source || (int)$source->item_id !== (int)($row['item_id'] ?? 0))
                FiscalAmounts::error('items','La partida no pertenece a esta factura o no corresponde al producto.');
            $used[(int)$id]=true;
            $row['calculation_snapshot']=$source->calculation_snapshot;
        }
        unset($row);
        return $rows;
    }

    /** Never recreate collections from the editor's serialized rows. */
    public static function validateInput(Document $document, array $inputs): void
    {
        validator($inputs,['iva_retention_prevision'=>'nullable|array','islr_retention_prevision'=>'nullable|array','received_retentions'=>'nullable|array'])->validate();
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
        if ($document->received_retentions()->applied()->exists() && (int) ($inputs['customer_id'] ?? $document->customer_id) !== (int) $document->customer_id) {
            FiscalAmounts::error('customer_id', 'Conserve el cliente asociado a las retenciones registradas.');
        }
    }

    public static function validateTotals(Document $document): void
    {
        $tolerance=D::of('0.005');
        $balance=$document->fresh()->exactBalance();
        if ($balance->isLessThan($tolerance->negated())) FiscalAmounts::error('total', 'El total no puede ser inferior al importe ya aplicado.');
        $retentions = $document->received_retentions()->where('tax_kind', 'IVA')->applied()->get();
        $fiscalIva = D::convert(D::read($document,'total_igv'),$document->currency_type_id,'VES',$document->exchange_rate_sale);
        foreach ($retentions as $retention) {
            $base = D::convert(D::read($retention,'base'), $retention->currency_type_id, 'VES', $retention->exchange_rate);
            if ($base->isGreaterThan($fiscalIva->plus($tolerance))) FiscalAmounts::error('total_igv', 'El nuevo IVA fiscal no respalda la base de la retención registrada.');
        }
        $retained=D::sum($retentions->map(fn($row)=>D::convert(D::read($row,'amount'),$row->currency_type_id,'VES',$row->exchange_rate)));
        if ($retained->isGreaterThan($fiscalIva->plus($tolerance))) FiscalAmounts::error('total_igv', 'El nuevo IVA fiscal es inferior a las retenciones aplicadas.');
        $netVes=D::convert(D::read($document,'total_value'),$document->currency_type_id,'VES',$document->exchange_rate_sale);
        foreach ($document->received_retentions()->where('tax_kind','ISLR')->applied()->get() as $retention) {
            if (D::convert(D::read($retention,'base'),$retention->currency_type_id,'VES',$retention->exchange_rate)->isGreaterThan($netVes->plus($tolerance))) FiscalAmounts::error('total_value','El nuevo valor neto no respalda la base ISLR registrada.');
        }
        $document->forceFill(['total_canceled' => $balance->isLessThanOrEqualTo($tolerance)])->save();
    }
}
