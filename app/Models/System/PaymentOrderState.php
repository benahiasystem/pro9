<?php

namespace App\Models\System;

use App\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class PaymentOrderState extends Model
{
    // ########## INICIO CAMBIO POSTGRESQL 17
    use \App\Database\HasPostgresIdentity;
    // ######### FIN CAMBIO POSTGRESQL 17

    use UsesSystemConnection;

    public function payment_orders()
    {
        return $this->hasMany(PaymentOrder::class, 'order_state_id');
    }
}
