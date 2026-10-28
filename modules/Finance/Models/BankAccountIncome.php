<?php

namespace Modules\Finance\Models;

use Modules\Finance\Models\GlobalPayment;
use App\Models\Tenant\PaymentMethodType;

use App\Models\Tenant\ModelTenant;

class BankAccountIncome extends ModelTenant
{
    protected $with = ['payment_method_type'];
    public $timestamps = false;

    protected $fillable = [
        'income_id',
        'date_of_payment',
        'payment_method_type_id',
        'reference',
        'change',
        'payment',
    ];

    protected $casts = [
        'date_of_payment' => 'date',
    ];

    public function payment_method_type()
    {
        return $this->belongsTo(PaymentMethodType::class);
    }

    public function global_payment()
    {
        return $this->morphOne(GlobalPayment::class, 'payment');
    }
 
    public function associated_record_payment()
    {
        return $this->belongsTo(Income::class, 'income_id');
    }
}