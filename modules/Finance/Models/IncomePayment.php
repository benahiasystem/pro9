<?php

namespace Modules\Finance\Models;

use Modules\Finance\Models\GlobalPayment;
use App\Models\Tenant\PaymentMethodType;

use App\Models\Tenant\ModelTenant;

class IncomePayment extends ModelTenant
{
    protected $with = ['payment_method_type'];
    public $timestamps = false;

    protected $fillable = [
        'income_id',
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
        return $this->belongsTo(Income::class, 'income_id');
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
     * Filtros para obtener pagos en efectivo
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeFilterCashPaymentWithoutDestination($query)
    {
        return \App\Services\Payments\Classification::filterRecorded($query, 'cash');
    }


    /**
     * 
     * Filtros para obtener pagos en efectivo de un registro aceptado
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeFilterCashPaymentWithDocument($query)
    {
        return $query->whereHas('associated_record_payment', function ($document) {
                        $document->whereStateTypeAccepted();
                    })
                    ->filterCashPaymentWithoutDestination();
    }


    /**
     * 
     * Obtener informacion del pago y registro origen relacionado
     *
     * @return array
     */
    public function getRowResourceCashPayment()
    {
        return [
            'type' => 'income',
            'type_transaction' => 'income',
            'type_transaction_description' => 'Ingresos (finanzas)',
            'date_of_issue' => $this->associated_record_payment->date_of_issue->format('Y-m-d'),
            'number_full' => $this->associated_record_payment->number_full,
            'acquirer_name' => $this->associated_record_payment->customer,
            'acquirer_number' => null,
            'currency_type_id' => $this->associated_record_payment->currency_type_id,
            'document_type_description' => $this->associated_record_payment->getDocumentTypeDescription(),
            'payment_method_type_id' => $this->payment_method_type_id,
            'payment' => $this->associated_record_payment->isVoidedOrRejected() ? 0 : $this->payment,
        ];
    }


    /**
     * 
     * Obtener informacion del pago, registro origen y items(opcional) relacionado
     *
     * @return array
     */
    public function getDataCashPaymentReport()
    {
        $data = [
            'total' => $this->associated_record_payment->isVoidedOrRejected() ? 0 : $this->associated_record_payment->total,
            'items_description_html' => $this->getGeneralHtmlItemsDescription($this->associated_record_payment)
        ];

        return array_merge($this->getRowResourceCashPayment(), $data);
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