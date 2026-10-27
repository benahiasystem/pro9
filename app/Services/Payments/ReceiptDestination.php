<?php

namespace App\Services\Payments;

use App\Models\Tenant\{BankAccount, Cash, Company};

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
final class ReceiptDestination
{
    public static function attach($payment, array $input): void
    {
        if (!empty($payment->payment_method_snapshot['receipt_origin'])) return;
        $existing=$payment->global_payment()->first();
        if ($existing) {
            $requested=$input['payment_destination_id'] ?? null;
            $actual=$existing->destination_type === Cash::class ? 'cash' : (string)$existing->destination_id;
            if ($requested !== null && (string)$requested !== $actual) Classification::error('payment_destination_id','El cobro ya existe con otro destino.');
            return;
        }
        $parent=$payment->associated_record_payment;
        $destination=$input['payment_destination_id'] ?? null;
        $user=auth()->id() ?? $parent->user_id;
        $kind=$payment instanceof \Modules\Expense\Models\ExpensePayment ? Classification::snapshot($payment->expense_method_type,'expense_method_types')['collection_kind'] : $payment->payment_method_type?->collection_kind;
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
        $global->save();
        $payment->unsetRelation('global_payment');
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
