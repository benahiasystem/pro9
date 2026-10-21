<?php

namespace Modules\Sale\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\SaleNotePayment;

use Exception;

class SaleNotePaymentProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->payments(); 
    }
 
    

    private function payments()
    {

        SaleNotePayment::created(function ($sale_note_payment) {
            $this->transaction_payment($sale_note_payment);
        });
 
        // SaleNotePayment::deleted(function ($sale_note_payment) {
        //     $this->transaction_payment($sale_note_payment);
        // });
        
    }
 
    private function transaction_payment($sale_note_payment){
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        // ModelTenant's saved hook owns exact settlement, including deferred batches.
        if (\App\Support\Exact\Persistence::enabled($sale_note_payment)) return;
        $sale_note = SaleNote::withoutEagerLoads()->findOrFail($sale_note_payment->sale_note_id);
        $values = \App\Support\Exact\SaleNoteBalance::values($sale_note);
        $sale_note->total_canceled = !$values['total_difference']->isPositive();
        $sale_note->save();
        // ######## FIN CALCULOS EXACTOS PRO9 ########
    }
}
