<?php

namespace Modules\Sale\Models;

use Modules\Finance\Models\GlobalPayment;
use App\Models\Tenant\PaymentMethodType;

use App\Models\Tenant\ModelTenant;

class ContractPayment extends ModelTenant
{
    protected $with = ['payment_method_type'];
    public $timestamps = false;

    protected $fillable = [
        'contract_id',
        'date_of_payment',
        'payment_method_type_id',
        'currency_type_id',
        'original_amount',
        'exchange_rate',
        'exchange_rate_source',
        'exchange_rate_date',
        'payment_method_snapshot',
        'reference',
        'change',
        'payment',
    ];

    protected $casts = [
        'date_of_payment' => 'date',
        'exchange_rate_date' => 'date',
        'payment_method_snapshot' => 'array',
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
        return $this->belongsTo(Contract::class, 'contract_id');
    }
    
    /**
     * 
     * Obtener relaciones necesarias o aplicar filtros para reporte pagos - finanzas
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeFilterRelationsPayments($query)
    {
        return $query->generalPaymentsWithOutRelations()
                    ->with([
                        'payment_method_type' => function($payment_method_type){
                            $payment_method_type->select('id', 'description');
                        }, 
                    ]);
    }


    /**
     *
     * @return string|null
     */
    public function getPaymentFileUrl()
    {
        return null;
    }

}