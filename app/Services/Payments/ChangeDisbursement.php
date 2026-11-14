<?php

namespace App\Services\Payments;

use App\Models\Tenant\{Cash, Document, DocumentChangeDisbursement, DocumentPayment, PaymentMethodType};
use App\Support\Exact\Decimal as D;

// ######## INICIO DESEMBOLSOS DE VUELTO ########
/** Register real outflows alongside a newly created cash receipt in the caller's transaction. */
final class ChangeDisbursement
{
    public static function register(DocumentPayment $receipt, array $lines): void
    {
        $db = $receipt->getConnection();
        if (!$db->transactionLevel()) throw new \LogicException('El vuelto requiere una transacción.');
        if (!$db->getSchemaBuilder()->hasTable('document_change_disbursements')) {
            Classification::error('change_disbursements', 'La instalación aún no dispone del registro de vueltos.');
        }
        $document = Document::query()->lockForUpdate()->findOrFail($receipt->document_id);
        $receipt = DocumentPayment::query()->lockForUpdate()->findOrFail($receipt->id);
        $existing = $receipt->change_disbursements()->orderBy('position')->lockForUpdate()->get();
        $context = $receipt->fiscal_context ?? [];
        $marker = $context['change_disbursement'] ?? null;
        if ($marker !== null || $existing->isNotEmpty()) {
            if (($marker['version'] ?? null) !== 1 || !is_array($marker['requested'] ?? null) || self::canonical($lines) !== self::canonical($marker['requested'])
                || count($existing) !== count($marker['requested'] ?? []) || $existing->contains(fn ($row) => $row->reversed_at || !$row->global_payment)) {
                Classification::error('operation_key', 'El vuelto ya fue registrado con otra distribución o está incompleto.');
            }
            return;
        }
        if ($receipt->reversed_at || $receipt->receipt_parent_id || !empty($receipt->payment_method_snapshot['receipt_origin'])
            || ($receipt->payment_method_snapshot['collection_kind'] ?? null) !== 'cash' || $receipt->global_payment()->exists()) {
            Classification::error('change_disbursements', 'El vuelto debe asociarse a un cobro nuevo de efectivo.');
        }
        $plan = ChangeDisbursementPlan::forMethods($receipt->change, $receipt->currency_type_id, $receipt->exchange_rate,
            $lines, (int)$document->establishment_id, true);
        if (collect($plan['lines'])->contains(fn ($line) => $line['payment_destination_id'] === 'cash')) {
            $candidate = \App\Services\Sellnow\CashContext::current() ?? Cash::query()->where('user_id', auth()->id())->where('state', true)->first();
            $cash = $candidate ? Cash::query()->whereKey($candidate->id)->lockForUpdate()->first() : null;
            if (!$cash || !$cash->state || (int)optional($cash->user)->establishment_id !== (int)$document->establishment_id) {
                Classification::error('change_disbursements', 'Abra una caja de la sucursal del comprobante para devolver efectivo.');
            }
        }
        $context['change_disbursement'] = ['version'=>1, 'requested'=>self::canonical($lines), 'calculation_snapshot'=>$plan['calculation_snapshot']];
        $receipt->fiscal_context = $context;
        $receipt->save();
        foreach ($plan['lines'] as $index => $line) {
            $method = PaymentMethodType::query()->lockForUpdate()->findOrFail($line['payment_method_type_id']);
            $row = DocumentChangeDisbursement::create([
                'document_id'=>$document->id, 'document_payment_id'=>$receipt->id, 'position'=>$index,
                'payment_method_type_id'=>$method->id, 'currency_type_id'=>$line['currency_type_id'],
                'original_amount'=>$line['amount'], 'exchange_rate'=>$plan['exchange_rate'],
                'date_of_payment'=>$receipt->date_of_payment->format('Y-m-d'), 'reference'=>$line['reference'],
                'calculation_snapshot'=>D::snapshot(['original_amount'=>D::of($line['amount']),
                    'source_equivalent'=>D::readRequired($line, 'source_equivalent')]),
                'context'=>['method'=>Classification::snapshot($method), 'source_currency'=>$receipt->currency_type_id],
            ]);
            ReceiptDestination::attach($row, ['payment_destination_id'=>$line['payment_destination_id']]);
        }
    }

    private static function canonical(array $lines): array
    {
        $result = [];
        foreach ($lines as $line) {
            if (!is_array($line) || !isset($line['payment_method_type_id']) || !is_string($line['payment_method_type_id'])) {
                Classification::error('change_disbursements', 'Seleccione un método para cada desembolso.');
            }
            $result[] = ['payment_method_type_id'=>$line['payment_method_type_id'],
                'amount'=>D::represent(D::of(D::input($line['amount'] ?? null, 'change_disbursements.amount', 2)), 2, true),
                'reference'=>$line['reference'] ?? null];
        }
        return $result;
    }
}
// ######## FIN DESEMBOLSOS DE VUELTO ########
