<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\MercadoPago\Models;

use App\Models\Tenant\{
    ModelTenant,
    FiscalEnvironment,
};
use Modules\Payment\Models\{
    PaymentLink
};


class Transaction extends ModelTenant
{

    public const TRANSACTION_STATE_APPROVED = '01';

    protected $fillable = [
        'fiscal_environment',
        'date',
        'time',
        'uuid',
        'description',
        'payment_id',
        'amount',
        'transaction_state_id',
        'payment_link_id',
    ];

 
    public function payment_link()
    {
        return $this->belongsTo(PaymentLink::class);
    } 

    public function transaction_state()
    {
        return $this->belongsTo(TransactionState::class, 'transaction_state_id');
    } 

    public function transaction_queries()
    {
        return $this->hasMany(TransactionQuery::class);
    } 
 
    public function fiscal_environment_type()
    {
        return $this->belongsTo(FiscalEnvironment::class, 'fiscal_environment');
    }
     
    public function getRowResource()
    {
        return [
            'date' => $this->date,
            'time' => $this->time,
            'description' => $this->description,
            'amount' => $this->amount,
            'transaction_state_id' => $this->transaction_state_id,
            'transaction_state_description' => $this->getStateUserMessage(),
        ];
    }
    
    /**
     * 
     * Obtener descripción del estado de la transacción
     *
     * @return string
     */
    public function getStateUserMessage()
    {
        return $this->transaction_state->user_message;
    }

}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
