<?php

namespace Modules\Payment\Models;

use Carbon\Carbon;
use Eloquent;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Tenant\{
    Document,
    DocumentPayment,
    ModelTenant,
    Person,
    SoapType,
    User,
};
use Modules\MercadoPago\Models\Transaction;


/**
 * Se elimina el registro al borrar el pago relacionado
 * Usa GlobalPaymentServiceProvider para el evento deleting del modelo
 */
class PaymentLink extends ModelTenant
{

    /**
     * El link aun no fue pagado, no se generaron los pagos
     */
    public const STATUS_PENDING = 'pending';

    /**
     * El link fue pagado y se generaron los pagos de los comprobantes asociados
     */
    public const STATUS_PAID = 'paid';


    protected $fillable = [
        'soap_type_id',
        'uuid',
        'user_id',
        'person_id',
        'payment_link_type_id',
        'payment_id',
        'payment_type',
        'total',
        'status',
        'paid_at',
        'uploaded_filename',
        'query_transaction',
    ];


    protected $casts = [
        'query_transaction' => 'bool',
        'paid_at' => 'datetime',
    ];


    /**
     * @return BelongsTo
     */
    public function soap_type()
    {
        return $this->belongsTo(SoapType::class);
    }
 
    
    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }


    /**
     * Cliente asociado al link de pago
     *
     * @return BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class);
    }


    /**
     * @return BelongsTo
     */
    public function type()
    {
        return $this->belongsTo(PaymentLinkType::class, 'payment_link_type_id');
    }


    /**
     * @return MorphTo
     */
    public function payment()
    {
        return $this->morphTo();
    }


    /**
     * Pagos asociados al link (tabla intermedia)
     *
     * @return HasMany
     */
    public function payments()
    {
        return $this->hasMany(PaymentLinkPayment::class);
    }

    /**
     * @return mixed
     */
    public function doc_payments()
    {
        return $this->belongsTo(DocumentPayment::class, 'payment_id')->wherePaymentType(DocumentPayment::class);
    }


    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }


    /**
     * @return string
     */
    public function getInstanceTypeAttribute()
    {
        $instance_type = [
            DocumentPayment::class => 'document',
        ];

        return $instance_type[$this->payment_type] ?? null;
    }


    public function getInstanceTypeDescriptionAttribute()
    {

        $description = null;

        switch ($this->instance_type) {
            case 'document':
                $description = 'CPE';
                break;
        }

        return $description;
    }


    public function getDataPersonAttribute()
    {

        $record = $this->payment->associated_record_payment;

        switch ($this->instance_type) {

            case 'document':
                $person['name'] = $record->customer->name;
                $person['number'] = $record->customer->number;
                break;

        }

        return (object)$person;
    }
 
    
    /**
     * @return string
     */
    public function getUserPaymentLinkAttribute()
    {
        return url("pagos/{$this->uuid}/{$this->payment_link_type_id}/{$this->total}");
    }


    /**
     * @return string
     */
    public function getImageUrlUploadedFilenameAttribute()
    {
        return $this->uploaded_filename ? asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'payment_links'.DIRECTORY_SEPARATOR.$this->uploaded_filename) : null;
    }

    
    /**
     * Usado para mostrar el link de pago al generarlo desde pagos (cpe)
     * 
     * @return array
     */
    public function getRowResource()
    {

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'payment_link_type_id' => $this->payment_link_type_id,
            'payment_id' => $this->payment_id,
            'payment_type' => $this->payment_type,
            'total' => $this->total,
            'uploaded_filename' => $this->uploaded_filename,
            'instance_type' => $this->instance_type,
            'user_payment_link' => $this->user_payment_link,
            'image_url_uploaded_filename' => $this->image_url_uploaded_filename,
            'query_transaction' => $this->query_transaction,
            'transaction' => $this->getShowDataTransactionApproved(),
        ];

    }


    /**
     * Usado para mostrar el link de pago al generarlo desde el listado
     * 
     * @return array
     */
    public function getRowResourceWithoutPayment()
    {

        return [
            'id' => $this->id,
            'payment_link_type_id' => $this->payment_link_type_id,
            'total' => $this->total,
            'without_payment' => true,
            'status' => $this->status,
            'is_paid' => $this->is_paid,
            'customer_id' => $this->person_id,
            // usado para recargar la busqueda remota del cliente en el formulario
            'customer_number' => format_person_identity_document($this->person),
            'documents' => $this->getDocumentsResource(),
        ];

    }


    /**
     *
     * Obtener las facturas asociadas al link de pago
     *
     * Se arma en base a los pagos de tipo DocumentPayment registrados en la tabla intermedia
     *
     * @return array
     */
    public function getDocumentsResource()
    {

        return $this->payments()
                    ->whereNotNull('record_id')
                    ->get()
                    ->map(function ($row) {

                        /** @var Document $document */
                        $document = $row->record;

                        if(!$document) return null;

                        $total_payments = (float) $document->payments()->sum('payment');
                        $payment = (float) $row->total;

                        // si el link ya fue pagado, su monto esta incluido en los pagos del comprobante
                        $applied = $row->is_paid ? $payment : 0;

                        return [
                            'instance_type' => self::getInstanceTypeByRecordType($row->record_type),
                            'document_id' => $document->id,
                            'number_full' => $document->number_full,
                            'currency_type_id' => $document->currency_type_id,
                            'total' => (float) $document->total,
                            // se devuelve el monto aplicado por este link para poder editarlo
                            'pending' => round((float) $document->total - $total_payments + $applied, 2),
                            'payment' => $payment,
                            'status' => $row->status,
                            'status_description' => $row->status_description,
                        ];

                    })
                    ->filter()
                    ->values()
                    ->toArray();

    }


    /**
     *
     * Obtener el tipo de instancia a partir del modelo del comprobante
     *
     * @param  string|null $record_type
     * @return string|null
     */
    public static function getInstanceTypeByRecordType($record_type)
    {
        $instance_types = [
            Document::class => 'document',
        ];

        return $instance_types[$record_type] ?? null;
    }


    /**
     * @return array
     */
    public function getRowCollection()
    {

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'total' => $this->total,
            'user_payment_link' => $this->user_payment_link,
            'query_transaction' => $this->query_transaction,
            'has_payment' => $this->has_payment,
            'payment_number_full' => $this->getPaymentNumberFull(),
            'customer_name' => optional($this->person)->name,
            'status' => $this->status,
            'status_description' => $this->status_description,
            'is_paid' => $this->is_paid,
            'paid_at' => optional($this->paid_at)->format('Y-m-d H:i:s'),
        ];
    }


    /**
     * Validar si el link ya fue pagado
     *
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
        $descriptions = [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_PAID => 'Pagado',
        ];

        return $descriptions[$this->status] ?? 'Pendiente';
    }


    /**
     *
     * Marcar el link como pagado
     *
     * Los pagos de los comprobantes se registran aparte (PaymentLinkPaymentTrait)
     *
     * @return void
     */
    public function setAsPaid()
    {
        $this->status = self::STATUS_PAID;
        $this->paid_at = Carbon::now();
        $this->save();
    }


    /**
     *
     * Devolver el link a pendiente, se usa cuando se elimina el pago generado
     *
     * @return void
     */
    public function setAsPending()
    {
        $this->status = self::STATUS_PENDING;
        $this->paid_at = null;
        $this->save();
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
     * 
     * Obtener descripción del pago asociado
     *
     * @return string
     */
    public function getPaymentNumberFull()
    {
        if($this->has_payment)
        {
            return "PAGO-{$this->payment->id}";
        }

        return null;
    }


    /**
     * 
     * Obtener path del modelo en base al tipo de instancia definida
     *
     * @param  string $instance_type
     * @return string
     */
    public static function getModelByType($instance_type)
    {
        $model = null;

        switch ($instance_type) {
            case 'document':
                $model = DocumentPayment::class;
                break;
        }

        return $model;
    }
    

    /**
     * 
     * Filtros para buscar link de pago en url publica
     *
     * @param $query
     */
    public function scopeWhereFilterPublicData($query, $payment_link_type_id, $uuid)
    {
        return $query->where('payment_link_type_id', $payment_link_type_id)->where('uuid', $uuid);
    }
    

    /**
     * Validar si tiene asociado un pago
     *
     * @return bool
     */
    public function getHasPaymentAttribute()
    {
        return !is_null($this->payment);
    }

    
    /**
     * 
     * Obtener datos de registro origen del pago
     *
     * @param  bool $has_payment
     * @return array|null
     */
    public function getAssociatedRecordPaymentData($has_payment)
    {
        if($has_payment)
        {
            $associated_record_payment = $this->payment->associated_record_payment;

            return [
                'currency_type_id' => $associated_record_payment->currency_type_id,
                'exchange_rate_sale' => $associated_record_payment->exchange_rate_sale,
            ];
        }

        return null;
    }

        
    /**
     * 
     * Obtener datps del link de pago para url publica
     *
     * @return array
     */
    public function getFormPublicData()
    {

        $has_payment = $this->has_payment;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'number_full' => $this->number_full,
            'total' => $this->total,
            'has_payment' => $has_payment,
            'associated_record_payment' => $this->getAssociatedRecordPaymentData($has_payment),
            'customer_name' => optional($this->person)->name,
            'customer' => $this->getPublicCustomerResource(),
            'date_of_issue' => optional($this->created_at)->format('d/m/Y H:i'),
            'is_paid' => $this->is_paid,
            'status_description' => $this->status_description,
            'documents' => $this->getPublicDocumentsResource(),
        ];

    }


    /**
     *
     * Datos del cliente para la pasarela de pago
     *
     * Se separa el nombre porque las pasarelas piden nombre y apellido por separado
     *
     * @return array
     */
    public function getPublicCustomerResource()
    {

        $person = $this->person;

        $parts = $person ? preg_split('/\s+/', trim($person->name), 2) : [];

        return [
            'name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
            'full_name' => optional($person)->name,
            'email' => optional($person)->email,
            'phone' => optional($person)->telephone,
            'number' => optional($person)->number,
        ];

    }


    /**
     *
     * Identificador visible del link de pago
     *
     * @return string
     */
    public function getNumberFullAttribute()
    {
        return 'PG-'.str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }


    /**
     *
     * Comprobantes que se cobran con el link, para mostrar en la url publica
     *
     * @return array
     */
    public function getPublicDocumentsResource()
    {

        return $this->payments()
                    ->whereNotNull('record_id')
                    ->get()
                    ->map(function ($row) {

                        $record = $row->record;

                        if(!$record) return null;

                        return [
                            'number_full' => $record->number_full,
                            'total' => (float) $row->total,
                        ];

                    })
                    ->filter()
                    ->values()
                    ->toArray();

    }
        
    
    /**
     * 
     * Datos para mostrar transacción en vista 
     *
     * @return array
     */
    public function getShowDataTransactionApproved()
    {
        $transaction_state_message =  null;
        $transaction_total =  null;

        if($this->query_transaction)
        {
            $transaction = $this->getTransactionApproved();
            $transaction_state_message =  $transaction->getStateUserMessage();
            $transaction_total =  $transaction->amount;
        }

        return [
            'transaction_state_message' => $transaction_state_message,
            'transaction_total' => $transaction_total,
        ];
    }

    
    /**
     * 
     * Obtener transacción aceptada
     *
     * @param  bool $with_select
     * @return Transaction
     */
    public function getTransactionApproved($with_select = false)
    {
        $transaction = $this->transactions()->where('transaction_state_id', Transaction::TRANSACTION_STATE_APPROVED);

        if($with_select) $transaction->select('id');

        return $transaction->first();
    }


    /**
     * 
     * Validar si el link de pago tiene transaccion aceptada de mercado pago
     *
     * @return bool
     */
    public function isTransactionApproved()
    {
        return !is_null($this->getTransactionApproved(true));
    }


}
