<?php

    namespace App\Traits;


    trait PaymentModelHelperTrait
    {

        /**
         * 
         * Filtros para obtener pagos al contado
         * Se determina la naturaleza desde el snapshot del movimiento registrado.
         * 
         * Usado en:
         * Models\Tenant\DocumentPayment
         * Models\Tenant\SaleNotePayment
         * Models\Tenant\QuotationPayment
         * Modules\Hotel\Models\HotelRentItemPayment
         *
         * @param  Builder $query
         * @return Builder
         */
        public function scopeWhereCashPaymentMethodType($query)
        {
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            return \App\Services\Payments\Classification::filterRecorded($query, 'cash');
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        }

    }
