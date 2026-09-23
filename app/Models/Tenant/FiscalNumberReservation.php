<?php

namespace App\Models\Tenant;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
/** Read model. Fiscal services own all mutations and locking. */
class FiscalNumberReservation extends ModelTenant
{
    protected $table = 'fiscal_number_reservations';
    protected $guarded = ['*'];
    protected $hidden = ['operation_key', 'payload_fingerprint', 'fiscal_snapshot', 'provider_result'];

    public function replacement()
    {
        return $this->hasOne(self::class, 'parent_reservation_id');
    }

    public function sequence()
    {
        return $this->belongsTo(FiscalSequence::class, 'sequence_id');
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
