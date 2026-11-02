<?php

namespace App\Services\Payments;

use App\Models\Tenant\{BankAccount, Cash, Company};

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
final class ReceiptDestination
{
    public static function attach($payment, array $input, ?LockedPaymentMethods $lockedMethods = null): void
    {
        if (!empty($payment->payment_method_snapshot['receipt_origin'])) return;
        if ($payment->getConnection()->transactionLevel() === 0) throw new \LogicException('La asociación del cobro requiere una transacción.');
        // Serialize attempts on the source, then bypass any earlier consistent-read snapshot.
        $payment->newQuery()->withoutEagerLoads()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
        $existing=$payment->global_payment()->lockForUpdate()->first();
        if ($existing) {
            $requested=$input['payment_destination_id'] ?? null;
            $actual=$existing->destination_type === Cash::class ? 'cash' : (string)$existing->destination_id;
            if ($requested !== null && (string)$requested !== $actual) Classification::error('payment_destination_id','El cobro ya existe con otro destino.');
            return;
        }
        $parent=$payment->associated_record_payment;
        $destination=$input['payment_destination_id'] ?? null;
        $user=auth()->id() ?? $parent->user_id;
        // A relation loaded through a consistent read may predate the catalog lock.
        // Resolve new receipts using a current read in the surrounding transaction.
        $method=$payment instanceof \Modules\Expense\Models\ExpensePayment ? $payment->expense_method_type
            : ($lockedMethods ? $lockedMethods->find($payment->getConnection(), $payment->payment_method_type_id)
                : \App\Models\Tenant\PaymentMethodType::query()->lockForUpdate()->find($payment->payment_method_type_id));
        $kind=$payment instanceof \Modules\Expense\Models\ExpensePayment && $method
            ? Classification::snapshot($method,'expense_method_types')['collection_kind'] : $method?->collection_kind;
        if ($destination === 'cash' || ($destination === null && $kind === 'cash')) {
            $cash=Cash::where('user_id',$user)->where('state',true)->lockForUpdate()->first();
            if (!$cash) Classification::error('payment_destination_id','Abra una caja antes de registrar efectivo.');
            $type=Cash::class; $id=$cash->id;
        } else {
            if ($destination === null) Classification::error('payment_destination_id','Seleccione la cuenta del cobro electrónico.');
            $type=BankAccount::class; $id=$destination;
        }
        $global=$payment->global_payment()->make(['destination_type'=>$type,'destination_id'=>$id,'user_id'=>$user,
            'fiscal_environment'=>$parent->fiscal_environment ?? Company::active()->fiscal_environment]);
        $global->setRelation('payment',$payment);
        if ($lockedMethods) $global->setRelation('lockedReceiptMethods', $lockedMethods);
        $global->save();
        $payment->unsetRelation('global_payment');
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
