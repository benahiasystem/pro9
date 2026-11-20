<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Database\IntegerTransport;
use App\Services\ExchangeRates\ExchangeRateMath;
use App\Support\Exact\Decimal as D;

final class DocumentEditSettlements
{
    /** Only snapshots read from this invoice may restore unchanged exact values. */
    public static function restoreLineSnapshots(Document $document, array $rows): array
    {
        $existing=$document->items()->lockForUpdate()->get()->keyBy('id');
        $originFields=['order_inventory_inherited','dispatch_inventory_quantity','dispatch_global_inventory_quantity',
            'legacy_order_note_inventory','lot_serial_assignments_verified'];
        $hasDeliveryOrigin=$existing->contains(fn($line)=>isset($line->item->dispatch_inventory_quantity) || !empty($line->item->order_inventory_inherited));
        $used=[];
        foreach ($rows as &$row) {
            $id=$row['record_id'] ?? $row['id'] ?? null;
            unset($row['calculation_snapshot'],$row['_invoice_components'],$row['_invoice_is_set'],$row['_invoice_modifier_components']);
            foreach ($originFields as $field) unset($row['item'][$field]);
            $itemId=IntegerTransport::positiveKey($row['item_id'] ?? null);
            if ($itemId === null)
                FiscalAmounts::error('items','Las partidas deben identificar un producto válido.');
            if ($id === null || $id === '') {
                if ($hasDeliveryOrigin) FiscalAmounts::error('items','La factura debe conservar las partidas de sus órdenes de entrega.');
                continue;
            }
            $recordId=IntegerTransport::positiveKey($id);
            if ($recordId === null || isset($used[$recordId]))
                FiscalAmounts::error('items','Las partidas guardadas deben identificarse una sola vez.');
            $source=$existing->get($recordId);
            if (!$source || IntegerTransport::positiveKey($source->item_id) !== $itemId)
                FiscalAmounts::error('items','La partida no pertenece a esta factura o no corresponde al producto.');
            $used[$recordId]=true;
            $row['calculation_snapshot']=$source->calculation_snapshot;
            $originalItem=(array)$source->item;
            foreach ($originFields as $field) {
                unset($row['item'][$field]);
                if (array_key_exists($field,$originalItem)) $row['item'][$field]=$originalItem[$field];
            }
            $row['_invoice_is_set']=(bool)($originalItem['is_set'] ?? false);
            $originalPresentation=(array)($originalItem['presentation'] ?? []);
            $postedPresentation=(array)($row['item']['presentation'] ?? []);
            $samePresentation=(string)($originalPresentation['id'] ?? '')===(string)($postedPresentation['id'] ?? '');
            if (!isset($originalPresentation['id']) && !isset($postedPresentation['id'])) {
                $samePresentation=(bool)$originalPresentation===(bool)$postedPresentation
                    && D::of(D::input($postedPresentation['quantity_unit'] ?? 1,'items.presentation.quantity_unit'))
                        ->isEqualTo(D::of(D::represent(FiscalNoteInventory::presentationQuantity($originalItem))));
            }
            if (isset($originalItem['dispatch_inventory_quantity']) || !empty($originalItem['order_inventory_inherited'])) {
                $warehouse=IntegerTransport::positiveKey($row['warehouse_id'] ?? $source->warehouse_id);
                if ($warehouse === null || $warehouse!==IntegerTransport::positiveKey($source->warehouse_id)) {
                    FiscalAmounts::error('items.warehouse_id','Conserve el almacén de la orden de entrega original.');
                }
                $row['warehouse_id']=$source->warehouse_id;
                $row['item']['lots']=json_decode(json_encode($originalItem['lots'] ?? [],JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
                $row['lots']=$row['item']['lots'];
                $row['item']['IdLoteSelected']=json_decode(json_encode($originalItem['IdLoteSelected'] ?? null,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
                $row['IdLoteSelected']=$row['item']['IdLoteSelected'];
                $quantity=D::of(D::input($row['quantity'],'items.quantity'));
                if (!empty($row['quantity_input'])) {
                    $price=\App\Support\Exact\LineCalculation::priceInput($row['quantity_input']['unit_price'] ?? null,'items.quantity_input.unit_price');
                    if (!$price->isPositive()) FiscalAmounts::error('items.quantity','El precio debe ser positivo.');
                    $quantity=D::of(D::input($row['quantity_input']['amount'] ?? null,'items.quantity_input.amount'))->dividedBy($price);
                    $sameQuantity=$source->exactValue('quantity')->isEqualTo($quantity);
                } else $sameQuantity=D::represent($source->exactValue('quantity'))===D::represent($quantity);
                if (!$sameQuantity || !$samePresentation) FiscalAmounts::error('items','Conserve las cantidades y presentaciones de las órdenes de entrega.');
            }
            if (!empty($originalItem['is_set']) || $samePresentation) {
                if (isset($originalItem['inventory_components'])) $row['_invoice_components']=json_decode(json_encode($originalItem['inventory_components']),true,512,JSON_THROW_ON_ERROR);
                $row['item']['presentation']=$originalPresentation;
                $row['item']['unit_type_id']=$originalItem['unit_type_id'];
            } else {
                $row['_invoice_modifier_components'] = InvoiceModifiers::components($originalItem);
            }
        }
        unset($row);
        if ($hasDeliveryOrigin && count($used)!==$existing->count()) FiscalAmounts::error('items','La factura debe conservar las partidas de sus órdenes de entrega.');
        return $rows;
    }

    /** Never recreate collections from the editor's serialized rows. */
    public static function validateInput(Document $document, array $inputs): void
    {
        validator($inputs, [
            'iva_retention_prevision' => 'nullable|array',
            'islr_retention_prevision' => 'nullable|array',
            'received_retentions' => 'nullable|array',
            'payments' => 'nullable|array',
        ])->validate();
        if (($inputs['currency_type_id'] ?? $document->currency_type_id) !== $document->currency_type_id) {
            FiscalAmounts::error('currency_type_id', 'La edición conserva la moneda del comprobante.');
        }
        foreach ($inputs['payments'] ?? [] as $row) {
            $paymentId = is_array($row) ? IntegerTransport::positiveKey($row['id'] ?? null) : null;
            if ($paymentId === null) {
                FiscalAmounts::error('payments', 'Seleccione un identificador de cobro válido.');
            }
            $payment = $document->payments()->find($paymentId);
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
        if ($document->received_retentions()->applied()->exists()) {
            $customerId = IntegerTransport::positiveKey($inputs['customer_id'] ?? $document->customer_id);
            if ($customerId === null || $customerId !== IntegerTransport::positiveKey($document->customer_id)) {
                FiscalAmounts::error('customer_id', 'Conserve el cliente asociado a las retenciones registradas.');
            }
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
