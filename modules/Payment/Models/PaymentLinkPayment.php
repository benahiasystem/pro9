<?php

namespace Modules\Payment\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Detalle de lo que se cobra con un link de pago
 *
 * Se registra en estado pendiente al generar el link, el pago (payment_id/payment_type)
 * se genera recien cuando el link es pagado
 */
class PaymentLinkPayment extends ModelTenant
{

    /**
     * Aun no se registró el pago porque el link no ha sido pagado
     */
    public const STATUS_PENDING = 'pending';

    /**
     * El link fue pagado y el pago ya fue registrado
     */
    public const STATUS_PAID = 'paid';


    protected $table = 'payment_link_payments';

    protected $fillable = [
        'payment_link_id',
        'record_id',
        'record_type',
        'payment_id',
        'payment_type',
        'total',
        'status',
    ];


    /**
     * @return BelongsTo
     */
    public function payment_link()
    {
        return $this->belongsTo(PaymentLink::class);
    }


    /**
     * Comprobante que se cobra con el link
     *
     * @return MorphTo
     */
    public function record()
    {
        return $this->morphTo();
    }


    /**
     * Pago generado al marcar el link como pagado
     *
     * @return MorphTo
     */
    public function payment()
    {
        return $this->morphTo();
    }


    /**
     * @param  Builder $query
     * @return Builder
     */
    public function scopeWherePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }


    /**
     * @param  Builder $query
     * @return Builder
     */
    public function scopeWherePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }


    /**
     * @return bool
     */
    public function getIsPaidAttribute()
    {
        return $this->status === self::STATUS_PAID;
    }


    /**
     * @return string
     */
    public function getStatusDescriptionAttribute()
    {
        return self::getStatusDescriptionById($this->status);
    }


    /**
     * @param  string $status
     * @return string
     */
    public static function getStatusDescriptionById($status)
    {
        $descriptions = [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_PAID => 'Pagado',
        ];

        return $descriptions[$status] ?? 'Pendiente';
    }


    /**
     * Marcar como pagado y asociar el pago generado
     *
     * @param  \Illuminate\Database\Eloquent\Model $payment
     * @return void
     */
    public function setAsPaid($payment)
    {
        $this->payment_id = $payment->id;
        $this->payment_type = get_class($payment);
        $this->status = self::STATUS_PAID;
        $this->save();
    }


    /**
     * Revertir a pendiente cuando se elimina el pago generado
     *
     * @return void
     */
    public function setAsPending()
    {
        $this->payment_id = null;
        $this->payment_type = null;
        $this->status = self::STATUS_PENDING;
        $this->save();
    }


    /**
     * @return array
     */
    public function getRowResource()
    {
        return [
            'id' => $this->id,
            'payment_link_id' => $this->payment_link_id,
            'record_id' => $this->record_id,
            'record_type' => $this->record_type,
            'payment_id' => $this->payment_id,
            'payment_type' => $this->payment_type,
            'total' => $this->total,
            'status' => $this->status,
            'status_description' => $this->status_description,
        ];
    }

}
