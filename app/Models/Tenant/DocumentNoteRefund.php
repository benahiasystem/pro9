<?php
namespace App\Models\Tenant;

use Modules\Finance\Models\GlobalPayment;

/** A real outbound receipt. Original collections are never overwritten. */
class DocumentNoteRefund extends ModelTenant
{
    use \App\Traits\PaymentModelHelperTrait;
    protected $hidden = ['request_hash','context'];
    protected $guarded = ['id'];
    protected $casts = ['exchange_rate'=>'decimal:8', 'context'=>'array', 'date_of_payment'=>'date', 'reversed_at'=>'datetime'];
    public function document() { return $this->belongsTo(Document::class); }
    public function associated_record_payment() { return $this->belongsTo(Document::class, 'document_id'); }
    public function global_payment() { return $this->morphOne(GlobalPayment::class, 'payment'); }
    public function payment_method_type() { return $this->belongsTo(PaymentMethodType::class); }
    public function scopeFilterRelationsPayments($query) { return $query->with(['payment_method_type','associated_record_payment']); }
    public function getPaymentFileUrl() { return null; }
    public function getPaymentAttribute() { return $this->original_amount; }
    public function getCashReceivedAmountAttribute() { return $this->reversed_at ? 0 : $this->original_amount; }
    public function getRowResourceCashPayment(): array
    {
        $note=$this->document;
        return ['type'=>'note_refund','type_transaction'=>'egress','type_transaction_description'=>'Devolución por NC',
            'date_of_issue'=>$this->date_of_payment->format('Y-m-d'),'number_full'=>$note->number_full,
            'acquirer_name'=>$note->customer->name,'acquirer_number'=>$note->customer->number,
            'currency_type_id'=>$this->currency_type_id,'document_type_description'=>$note->document_type->description,
            'payment_method_type_id'=>$this->payment_method_type_id,'payment'=>$this->cash_received_amount];
    }
    public function getDataCashPaymentReport(): array { return $this->getRowResourceCashPayment()+['total'=>$this->document->total,'items_description_html'=>'']; }
    public function getRowIncomeSummaryPayment(): array
    {
        return $this->getRowResourceCashPayment()+['date_time_of_issue'=>$this->date_of_payment->format('Y-m-d').' 00:00:00',
            'payment_method_type_description'=>$this->payment_method_type->description,'total'=>$this->document->total,'change'=>0,
            'payment_for_calculate'=>-\App\Services\Fiscal\FiscalAmounts::convert($this->cash_received_amount,$this->currency_type_id,'VES',$this->exchange_rate)];
    }
}
