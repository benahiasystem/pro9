<?php

namespace App\Models\Tenant;

use Modules\Finance\Models\GlobalPayment;

// ######## INICIO DESEMBOLSOS DE VUELTO ########
/** A single actual outflow, linked to the receipt funding the change. */
class DocumentChangeDisbursement extends ModelTenant
{
    use \App\Traits\PaymentModelHelperTrait;

    protected $guarded = ['id'];
    protected $casts = ['context'=>'array', 'date_of_payment'=>'date', 'reversed_at'=>'datetime'];

    public function document() { return $this->belongsTo(Document::class); }
    public function associated_record_payment() { return $this->belongsTo(Document::class, 'document_id'); }
    public function document_payment() { return $this->belongsTo(DocumentPayment::class); }
    public function payment_method_type() { return $this->belongsTo(PaymentMethodType::class); }
    public function global_payment() { return $this->morphOne(GlobalPayment::class, 'payment'); }
    public function scopeFilterRelationsPayments($query) { return $query->with(['payment_method_type', 'associated_record_payment']); }
    public function getPaymentFileUrl() { return null; }
    public function getPaymentAttribute() { return $this->original_amount; }
    public function getCashReceivedAmountAttribute() { return $this->reversed_at ? '0' : $this->original_amount; }
    public function getRowResourceCashPayment(): array
    {
        $document = $this->associated_record_payment;
        return ['type'=>'change_disbursement', 'type_transaction'=>'egress', 'type_transaction_description'=>'Vuelto',
            'date_of_issue'=>$this->date_of_payment->format('Y-m-d'), 'number_full'=>$document->number_full,
            'acquirer_name'=>$document->customer->name, 'acquirer_number'=>$document->customer->number,
            'currency_type_id'=>$this->currency_type_id, 'document_type_description'=>$document->document_type->description,
            'payment_method_type_id'=>$this->payment_method_type_id, 'payment'=>$this->cash_received_amount];
    }
    public function getDataCashPaymentReport(): array { return $this->getRowResourceCashPayment()+['total'=>$this->document->total, 'items_description_html'=>'']; }
    public function getRowIncomeSummaryPayment(): array
    {
        $amount = \App\Support\Exact\Decimal::convert($this->cash_received_amount, $this->currency_type_id, 'VES', $this->exchange_rate);
        return $this->getRowResourceCashPayment()+['date_time_of_issue'=>$this->date_of_payment->format('Y-m-d').' 00:00:00',
            'payment_method_type_description'=>$this->payment_method_type->description, 'total'=>$this->document->total,
            'change'=>0, 'payment_for_calculate'=>\App\Support\Exact\Decimal::represent($amount->negated())];
    }
}
// ######## FIN DESEMBOLSOS DE VUELTO ########
