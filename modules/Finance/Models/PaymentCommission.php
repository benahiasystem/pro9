<?php

namespace Modules\Finance\Models;

use App\Models\Tenant\ModelTenant;

final class PaymentCommission extends ModelTenant
{
    public $timestamps = false;
    protected $fillable = ['source_global_payment_id', 'currency_type_id', 'date_of_payment', 'amount'];
    protected $casts = ['date_of_payment' => 'date'];

    public function sourceMovement()
    {
        return $this->belongsTo(GlobalPayment::class, 'source_global_payment_id');
    }

    public function global_payment()
    {
        return $this->morphOne(GlobalPayment::class, 'payment');
    }

    public function getAssociatedRecordPaymentAttribute()
    {
        return $this->sourceMovement?->payment?->associated_record_payment;
    }

    public function getPaymentFileUrl()
    {
        return null;
    }
}
