<?php

namespace Modules\Finance\Providers;

use App\Models\Tenant\{
    SaleNotePayment,
    DocumentPayment,
    PurchasePayment,
};  
use Modules\Sale\Models\QuotationPayment;
use Modules\Sale\Models\ContractPayment;
use Modules\Sale\Models\TechnicalServicePayment;
use Modules\Expense\Models\ExpensePayment;
use Modules\Finance\Models\IncomePayment;
use Modules\Payment\Models\PaymentLinkPayment;
use Modules\Pos\Models\CashTransaction;
use Illuminate\Support\ServiceProvider;
use Modules\Hotel\Models\HotelRentItemPayment;


class GlobalPaymentServiceProvider extends ServiceProvider
{

    public function register()
    {
    }
    
    public function boot()
    {

        $this->deletingPayment(SaleNotePayment::class);
        $this->deletingPayment(DocumentPayment::class);
        $this->deletingPayment(PurchasePayment::class);
        $this->deletingPayment(QuotationPayment::class);
        $this->deletingPayment(ExpensePayment::class);
        $this->deletingPayment(ContractPayment::class);
        $this->deletingPayment(IncomePayment::class);
        $this->deletingPayment(CashTransaction::class);
        $this->deletingPayment(TechnicalServicePayment::class);
        $this->deletingPayment(HotelRentItemPayment::class);
        
        $this->paymentsPurchases(); 

    }

    private function deletingPayment($model)
    {

        $model::deleting(function ($record) {
            
            if($record->global_payment){
                $record->global_payment()->delete();
            }

            if($record->payment_file){
                $record->payment_file()->delete();
            }

            // eliminar link de pago
            if($record->payment_links)
            {
                if($record->payment_links->count() > 0)
                {
                    $record->payment_links()->delete();
                }
            }

            $this->revertPaymentLinkPayments($record);

        });

    }


    /**
     *
     * Devolver a pendiente el detalle del link de pago cuando se elimina el pago generado
     *
     * El link deja de estar pagado porque su pago ya no existe
     *
     * @param  mixed $record
     * @return void
     */
    private function revertPaymentLinkPayments($record)
    {

        $rows = PaymentLinkPayment::where('payment_id', $record->id)
                    ->where('payment_type', get_class($record))
                    ->get();

        foreach ($rows as $row) {

            $row->setAsPending();

            if(optional($row->payment_link)->is_paid) $row->payment_link->setAsPending();

        }

    }
 

    private function paymentsPurchases()
    {

        PurchasePayment::created(function ($purchase_payment) {
            $this->transaction_payment($purchase_payment);
        });
 
        PurchasePayment::deleted(function ($purchase_payment) {
            $this->transaction_payment($purchase_payment);
        });
        
    }
 
    private function transaction_payment($purchase_payment){

        $purchase = $purchase_payment->purchase;
        $total_payments = $purchase->payments->sum('payment');

        $balance = $purchase->total - $total_payments;

        if($balance <= 0){

            $purchase->total_canceled = true;
            $purchase->update();

        }else{
            
            $purchase->total_canceled = false;
            $purchase->update();
        }

    }
 
}
