<?php

namespace App\Services\Payments;

use App\Models\Tenant\{Cash, BankAccount, DocumentPayment, DocumentNoteRefund, PaymentMethodType};
use App\Support\Exact\Decimal as D;
use Modules\Expense\Models\ExpensePayment;
use Modules\Finance\Models\GlobalPayment;
use Modules\Pos\Models\CashTransaction;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
/** One adapter for actual money. Documentary allocation is never a cash receipt. */
final class Movement
{
    public static function preload($payments): void
    {
        $payments->pluck('payment')->filter()->groupBy(fn($model)=>get_class($model))->each(function($models) {
            $models=new \Illuminate\Database\Eloquent\Collection($models->all());
            $first=$models->first();
            $relation=$first instanceof CashTransaction ? 'cash' : 'associated_record_payment';
            if ($first instanceof \Modules\Hotel\Models\HotelRentItemPayment) $relation.='.hotel_rent';
            if (method_exists($first,explode('.',$relation)[0])) $models->loadMissing($relation);
        });
    }

    public static function beforeSourceChange($source, bool $deleting = false): void
    {
        if (!method_exists($source,'global_payment') || $source instanceof CashTransaction) return;
        if (!$source->getConnection()->getSchemaBuilder()->hasTable('global_payments')) return;
        $global=$source->global_payment()->first();
        if (!$global || ($global->report_context['version'] ?? null) !== 2) return;
        if ($deleting && $source instanceof \App\Models\Tenant\SaleNotePayment
            && DocumentPayment::where('payment_method_snapshot->receipt_origin->id',(string)$source->id)->whereNull('reversed_at')->exists()) Classification::error('payment','El cobro fue reutilizado en una factura y no puede revertirse desde la nota de venta.');
        if (!$deleting) {
            if ($source->isDirty(['payment','payment_method_type_id','expense_method_type_id','currency_type_id','exchange_rate','original_amount','tax_amount','date_of_payment',
                'document_id','sale_note_id','purchase_id','expense_id','income_id','quotation_id','contract_id','technical_service_id','hotel_rent_item_id','bank_loan_id',
                'amount','date_of_movement','destiny_id','destiny_type','origin_id','origin_type'])) Classification::error('payment','Revierta el cobro y registre uno nuevo para modificar importes, moneda, medio o fecha.');
            return;
        }
        $context=$global->report_context;
        $context['reversal']=['date'=>date('Y-m-d H:i:s'),'user_id'=>auth()->id(),'reason'=>'Eliminación del registro comercial',
            'amounts'=>self::row($global)['calculation_snapshot'] ?? null];
        $source->getConnection()->table('global_payments')->where('id',$global->id)->update(['report_context'=>json_encode($context,JSON_THROW_ON_ERROR)]);
    }

    public static function capture(GlobalPayment $global): void
    {
        $db = $global->getConnection();
        if ($db->transactionLevel() === 0) throw new \LogicException('El movimiento financiero requiere una transacción.');
        $source = $global->payment;
        if (!$source) Classification::error('payment', 'No existe el pago del movimiento financiero.');
        if ($source instanceof DocumentPayment && ($source->receipt_parent_id || !empty($source->payment_method_snapshot['receipt_origin']))) Classification::error('payment', 'Un recibo derivado no representa otra recepción de dinero.');
        if ($source instanceof \App\Models\Tenant\TransferAccountPayment) { self::captureTransfer($global,$source); return; }
        $opening = $source instanceof CashTransaction;
        $parent = $opening ? $source->cash : ($source->associated_record_payment ?? $source);
        if ($source instanceof \Modules\Hotel\Models\HotelRentItemPayment) $parent=$parent->hotel_rent;
        $currency = $source->currency_type_id ?? ($source instanceof \Modules\Hotel\Models\HotelRentItemPayment ? $parent->getDefaultCurrency() : $parent->currency_type_id);
        if (!in_array($currency, ['VES','USD'], true)) Classification::error('currency_type_id', 'El movimiento debe identificar su moneda recibida VES/USD.');
        $method = $source instanceof ExpensePayment ? $source->expense_method_type : $source->payment_method_type;
        if (!$method) Classification::error('payment_method_type_id', 'El movimiento requiere un medio de pago clasificado.');
        $classification = Classification::snapshot($method, $source instanceof ExpensePayment ? 'expense_method_types' : 'payment_method_types');
        $kind = $classification['collection_kind'];
        if (!in_array($kind, ['cash','account'], true)) Classification::error('payment_method_type_id', 'Crédito y medios sin clasificar no registran dinero recibido.');
        if ($source instanceof \Modules\Expense\Models\BankLoanPayment && $kind !== 'account') Classification::error('payment_method_type_id', 'El pago del préstamo requiere un medio bancario.');
        if ($method instanceof PaymentMethodType && !$method->is_active) Classification::error('payment_method_type_id', 'El medio de pago está inactivo.');
        if ($classification['cash_currency_type_id'] && $classification['cash_currency_type_id'] !== $currency) Classification::error('currency_type_id', 'La moneda recibida no coincide con la moneda del efectivo seleccionado.');

        $cash = null;
        if ($kind === 'cash') {
            if ($global->destination_type !== Cash::class) Classification::error('payment_destination_id', 'El efectivo requiere destino caja.');
            $cash = Cash::query()->whereKey($global->destination_id)->lockForUpdate()->first();
        } else {
            if ($global->destination_type !== BankAccount::class) Classification::error('payment_destination_id', 'Seleccione la cuenta donde se recibió o desembolsó el dinero.');
            $account = BankAccount::query()->whereKey($global->destination_id)->lockForUpdate()->first();
            if (!$account || !(bool)$account->status || $account->currency_type_id !== $currency) Classification::error('payment_destination_id', 'La cuenta debe existir en el tenant y tener la misma moneda del pago.');
            $user = $global->user_id ?? $parent->user_id;
            $cash = Cash::query()->where('user_id', $user)->where('state', true)->lockForUpdate()->first();
        }
        if ($cash && (!(bool)$cash->state)) Classification::error('cash', 'El turno no está abierto con la nueva clasificación.');
        if ($kind === 'cash' && !$cash) Classification::error('cash', 'Abra una caja antes de registrar efectivo.');
        $person=$parent->customer ?? $parent->supplier ?? null;
        $party=is_string($person) ? $person : (string)data_get($person,'name','');
        $global->cash_session_id = $cash?->id;
        $direction = in_array($source->getTable(), ['purchase_payments','purchase_settlement_payments','expense_payments','bank_loan_payments','document_note_refunds'], true) ? 'egress' : 'income';
        $global->report_context = ['version'=>2, 'method'=>$classification, 'currency_type_id'=>$currency,
            'destination_label'=>$kind === 'cash' ? 'Caja' : trim((string)$account->description).' · '.$currency,
            'exchange_rate'=>(string)($source->exchange_rate ?? $parent->exchange_rate_sale ?? '1.00000000'),
            'direction'=>$direction, 'opening'=>$opening,
            'party'=>$party, 'number'=>(string)($opening ? $source->description : ($parent->number_full ?? $parent->number ?? '')),
            'date'=>substr((string)($source->getRawOriginal('date_of_payment') ?? $source->date ?? $parent->date_of_issue ?? date('Y-m-d')),0,10)];
    }

    private static function captureTransfer(GlobalPayment $global, $source): void
    {
        if (!in_array($global->destination_type,[Cash::class,BankAccount::class],true)) Classification::error('destination','Destino financiero inválido.');
        $destination=$global->destination_type::query()->whereKey($global->destination_id)->lockForUpdate()->firstOrFail();
        $cash=$destination instanceof Cash ? $destination : Cash::where('user_id',$source->user_id)->where('state',true)->lockForUpdate()->first();
        if ($destination instanceof Cash && (!$destination->state)) Classification::error('cash','La caja del traslado debe estar abierta.');
        $global->cash_session_id=$cash?->id;
        $global->report_context=['version'=>2,'method'=>['catalog'=>'transfer_account_payments','id'=>'transfer','description'=>'Traslado entre cuentas','collection_kind'=>$destination instanceof Cash ? 'cash' : 'account','cash_currency_type_id'=>null],
            'currency_type_id'=>$destination->currency_type_id,'exchange_rate'=>'1.00000000','direction'=>D::readRequired($source,'amount')->isNegative() ? 'egress' : 'income',
            'opening'=>false,'number'=>'TR-'.$source->id,'date'=>substr((string)$source->date_of_movement,0,10)];
    }

    public static function row(GlobalPayment $global): ?array
    {
        $context = $global->report_context;
        if (($context['version'] ?? null) !== 2 || isset($context['reversal'])) return null;
        $source = $global->payment;
        if (!$source) throw new \RuntimeException('Falta el origen del movimiento '.$global->id);
        if ($source->reversed_at || ($source instanceof DocumentPayment && $source->receipt_parent_id)) return null;
        $parent = $source instanceof CashTransaction ? $source->cash : ($source->associated_record_payment ?? $source);
        if ($source instanceof \Modules\Hotel\Models\HotelRentItemPayment) $parent=$parent->hotel_rent;
        if (method_exists($parent, 'isVoidedOrRejected') && $parent->isVoidedOrRejected()) return null;
        if ($source instanceof \App\Models\Tenant\TransferAccountPayment) {
            $principal=D::readRequired($source,'amount')->abs();$tax=D::of(0);
        } elseif ($source instanceof DocumentPayment) {
            $principal = D::readRequired($source, 'original_amount');
            $tax = D::readRequired($source, 'tax_amount');
        } elseif ($source instanceof DocumentNoteRefund) {
            $principal = D::readRequired($source, 'original_amount'); $tax = D::of(0);
        } else {
            $principal = D::readRequired($source, 'payment'); $tax = D::of(0);
        }
        $amount = $principal->plus($tax);
        if ($amount->isNegative()) Classification::error('payment', 'El movimiento financiero no puede ser negativo.');
        return ['id'=>$global->id, 'source'=>$global->payment_type.':'.$global->payment_id,
            'cash_session_id'=>$global->cash_session_id, 'destination_id'=>$global->destination_id, 'destination_type'=>$global->destination_type,
            'destination_label'=>$context['destination_label'] ?? ($context['method']['collection_kind']==='cash' ? 'Caja' : 'Cuenta #'.$global->destination_id),
            'method'=>$context['method'], 'currency_type_id'=>$context['currency_type_id'], 'direction'=>$context['direction'],
            'party'=>$context['party'] ?? '', 'opening'=>$context['opening'], 'date'=>$context['date'], 'number'=>$context['number'], 'exchange_rate'=>$context['exchange_rate'],
            'amount'=>D::represent($amount), 'principal'=>D::represent($principal), 'tax'=>D::represent($tax),
            'calculation_snapshot'=>D::snapshot(['amount'=>$amount,'principal'=>$principal,'tax'=>$tax])];
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
