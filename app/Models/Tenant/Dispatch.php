<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\Models\Tenant;

use App\Traits\ApiResourceFindTrait;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Tenant\DownloadController;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Models\Tenant\Catalogs\TransferReasonType;
use App\Models\Tenant\Catalogs\TransportModeType;
use App\Models\Tenant\Catalogs\UnitType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Modules\Dispatch\Models\DispatchAddress;
use Modules\Order\Models\OrderForm;
use Modules\Inventory\Models\InventoryKardex;
use Modules\Order\Models\OrderNote;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Models\Tenant\Catalogs\RelatedDocumentType;
use App\Models\Tenant\Catalogs\IdentityDocumentType;

/**
 * Class Dispatch
 *
 * @package App\Models\Tenant
 * @mixin ModelTenant
 * @property DocumentType $document_type
 * @property \App\Models\Tenant\Establishment $establishment
 * @property \App\Models\Tenant\Document|null $generate_document
 * @property mixed $customer
 * @property mixed $data_affected_document
 * @property mixed $delivery
 * @property mixed $dispatcher
 * @property string $download_external_cdr
 * @property string $download_external_pdf
 * @property string $download_external_xml
 * @property mixed $driver
 * @property mixed $legends
 * @property string $number_full
 * @property mixed $origin
 * @property mixed $secondary_license_plates
 * @property \Illuminate\Database\Eloquent\Collection|InventoryKardex[] $inventory_kardex
 * @property int|null $inventory_kardex_count
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Tenant\DispatchItem[] $items
 * @property int|null $items_count
 * @property OrderForm $order_form
 * @property OrderNote $order_note
 * @property \App\Models\Tenant\Person $person
 * @property \App\Models\Tenant\Document $reference_document
 * @property \App\Models\Tenant\SaleNote $sale_note
 * @property \App\Models\Tenant\FiscalEnvironment $fiscal_environment_type
 * @property \App\Models\Tenant\StateType $state_type
 * @property TransferReasonType $transfer_reason_type
 * @property TransportModeType $transport_mode_type
 * @property UnitType $unit_type
 * @property \App\Models\Tenant\User $user
 * @method static Builder|Dispatch newModelQuery()
 * @method static Builder|Dispatch newQuery()
 * @method static Builder|Dispatch query()
 * @method static Builder|Dispatch whereStateTypeAccepted()
 * @method static Builder|Dispatch whereTypeUser()
 * @method static Builder|Dispatch whereValuedKardexFormatSunat($params)
 */
class Dispatch extends ModelTenant
{
    use ApiResourceFindTrait;

    protected $with = ['user', 'fiscal_environment_type', 'state_type', 'document_type', 'unit_type', 'transport_mode_type','transfer_reason_type', 'items', 'reference_document'];

    protected $fillable = [
        'user_id',
        'external_id',
        'establishment_id',
        'establishment',
        'fiscal_environment',
        'state_type_id',
        'ubl_version',
        'document_type_id',
        'series',
        'number',
        'date_of_issue',
        'time_of_issue',
        'customer_id',
        'customer',
        'observations',
        'transport_mode_type_id',
        'transfer_reason_type_id',
        'transfer_reason_type',
        'transfer_reason_description',
        'date_of_shipping',
        'transshipment_indicator',
        'port_code',
        'unit_type_id',
        'total_weight',
        'packages_number',
        'container_number',
        'origin',
        'delivery',
        'has_transport_driver_01',
        'dispatcher_id',
        'dispatcher',
        'driver_id',
        'driver',
        'transport_id',
        'license_plate',
        'legends',
        'filename',
        'hash',
        'has_xml',
        'has_pdf',
        'has_cdr',
        'reference_document_id',
        'reference_order_note_id',
        'reference_quotation_id',
        'reference_order_note_id',
        'reference_order_form_id',
        'secondary_license_plates',
        'reference_sale_note_id',
        'data_affected_document',
        'related',
        'order_form_external',
        'terms_condition',
        'additional_data',
        'ticket',
        'reception_date',
        'qr_url',
        'origin_address_id',
        'delivery_address_id',
        'transport_data',
        'sender_id',
        'sender_data',
        'receiver_id',
        'receiver_data',
        'sender_address_id',
        'sender_address_data',
        'receiver_address_id',
        'receiver_address_data',
        'date_delivery_to_transport',
        'secondary_transports',
        'secondary_drivers',
        'payer',
        'is_transport_m1l',
        'license_plate_m1l',
        'reference_documents',
        'buyer_id',
        'buyer',
        'custom_fields_data',
        'sunat_error_response'
    ];

    protected $casts = [
        'date_of_issue' => 'date',
        'date_of_shipping' => 'date',
        'transport_data' => 'array',
        'receiver_data' => 'array',
        'sender_data' => 'array',
        'sender_address_data' => 'array',
        'receiver_address_data' => 'array',
        'establishment' => 'json',
        'date_delivery_to_transport' => 'date',
        'secondary_transports' => 'array',
        'secondary_drivers' => 'array',
        'payer' => 'array',
        'reference_documents' => 'array',
        'custom_fields_data' => 'array',
        'sunat_error_response' => 'array',
    ];

    public function getAdditionalDataAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    public function setAdditionalDataAttribute($value)
    {
        $this->attributes['additional_data'] = (is_null($value)) ? null : json_encode($value);
    }

    public function getEstablishmentAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    public function setEstablishmentAttribute($value)
    {
        $this->attributes['establishment'] = (is_null($value)) ? null : json_encode($value);
    }

    public function getCustomerAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    public function setCustomerAttribute($value)
    {
        $this->attributes['customer'] = (is_null($value)) ? null : json_encode($value);
    }

    public function getOriginAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    public function setOriginAttribute($value)
    {
        $this->attributes['origin'] = (is_null($value)) ? null : json_encode($value);
    }

    public function getDeliveryAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    public function setDeliveryAttribute($value)
    {
        $this->attributes['delivery'] = (is_null($value)) ? null : json_encode($value);
    }

    public function getDispatcherAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    public function setDispatcherAttribute($value)
    {
        $this->attributes['dispatcher'] = (is_null($value)) ? null : json_encode($value);
    }

    public function getDriverAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    public function setDriverAttribute($value)
    {
        $this->attributes['driver'] = (is_null($value)) ? null : json_encode($value);
    }

    public function getLegendsAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    public function setLegendsAttribute($value)
    {
        $this->attributes['legends'] = (is_null($value)) ? null : json_encode($value);
    }



    public function setBuyerAttribute($value)
    {
        $this->attributes['buyer'] = (is_null($value)) ? null : json_encode($value);
    }

    public function getBuyerAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }
    /**
     * Datos del DAM
     *
     * @param $value
     * @return object
     */
    public function getRelatedAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    /**
     * Datos del DAM
     *
     * @param $value
     * @return void
     */
    public function setRelatedAttribute($value)
    {
        $this->attributes['related'] = (is_null($value)) ? null : json_encode($value);
    }

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo
     */
    public function establishment()
    {
        return $this->belongsTo(Establishment::class);
    }

    /**
     * @return BelongsTo
     */
    public function fiscal_environment_type()
    {
        return $this->belongsTo(FiscalEnvironment::class, 'fiscal_environment');
    }

    /**
     * @return BelongsTo
     */
    public function state_type()
    {
        return $this->belongsTo(StateType::class);
    }

    /**
     * @return BelongsTo
     */
    public function document_type()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }


    /**
     * @return BelongsTo
     */
    public function buyer()
    {
        return $this->belongsTo(Buyer::class, 'buyer_id');
    }

    /**
     * @return BelongsTo
     */
    public function reference_document()
    {
        return $this->belongsTo(Document::class, 'reference_document_id');
    }

    /**
     * @return BelongsTo
     */
    public function reference_quotation()
    {
        return $this->belongsTo(Quotation::class, 'reference_quotation_id');
    }

    /**
     * @return BelongsTo
     */
    public function unit_type()
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    /**
     * @return BelongsTo
     */
    public function transport_mode_type()
    {
        return $this->belongsTo(TransportModeType::class, 'transport_mode_type_id');
    }

    /**
     * @return BelongsTo
     */
    public function transfer_reason_type()
    {
        return $this->belongsTo(TransferReasonType::class, 'transfer_reason_type_id');
    }

    /**
     * @return HasMany
     */
    /**
     * Datos esenciales de la orden de entrega para consumo por API.
     *
     * A diferencia de document / sale-note / quotation / purchase, la orden de entrega no tiene
     * importes: lo relevante es el traslado (motivo, fechas, pesos, bultos, origen,
     * destino y transporte). Por eso las lineas solo llevan cantidad y descripcion.
     *
     * @return array
     */
    public function getApiResourceFind()
    {
        $person = $this->customer_id ? Person::find($this->customer_id) : null;
        $person_ubigeo = $this->resolvePersonUbigeo($person);

        $items = collect($this->items)->map(function ($row) {
            return [
                'quantity'     => (float) $row->quantity,
                'unit_type_id' => optional($row->item)->unit_type_id,
                'description'  => $row->name_product_pdf ?: optional($row->item)->description,
            ];
        })->values()->all();

        return [
            'series'                      => $this->series,
            'number'                      => $this->number,
            'document_type_id'            => $this->document_type_id,
            'date_of_issue'               => optional($this->date_of_issue)->format('Y-m-d'),
            'time_of_issue'               => $this->time_of_issue,
            'date_of_shipping'            => optional($this->date_of_shipping)->format('Y-m-d'),

            'customer_name'               => optional($this->customer)->name,
            'customer_number'             => format_person_identity_document($this->customer),
            'customer_address'            => $this->buildApiResourcePersonAddress($this->customer, $person_ubigeo),
            'department_id'               => $person_ubigeo['department_id'],
            'province_id'                 => $person_ubigeo['province_id'],
            'district_id'                 => $person_ubigeo['district_id'],

            // Datos del traslado
            'transfer_reason_type_id'     => $this->transfer_reason_type_id,
            'transfer_reason_description' => $this->transfer_reason_description,
            'transport_mode_type_id'      => $this->transport_mode_type_id,
            'unit_type_id'                => $this->unit_type_id,
            'total_weight'                => (float) $this->total_weight,
            'packages_number'             => $this->packages_number,
            'container_number'            => $this->container_number,
            'origin'                      => $this->origin,
            'delivery'                    => $this->delivery,
            'license_plate'               => $this->license_plate,
            'driver'                      => $this->driver,
            'dispatcher'                  => $this->dispatcher,

            'items'                       => $items,
            // clave unificada con purchase/find y sale-note/find; en dispatches la
            // columna se llama observations (plural).
            'observations'                => $this->observations,
            'terms_condition'             => $this->terms_condition,
            'legends'                     => $this->legends,
            // La orden de entrega no genera 'qr' como Document; expone qr_url y hash.
            'qr_url'                      => $this->qr_url,
            'hash'                        => $this->hash,
        ];
    }

    public function items()
    {
        return $this->hasMany(DispatchItem::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function generate_document()
    {
        return $this->hasOne(Document::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'receiver_id');
    }

    public function sender_address(): BelongsTo
    {
        return $this->belongsTo(DispatchAddress::class, 'sender_address_id');
    }

    public function receiver_address(): BelongsTo
    {
        return $this->belongsTo(DispatchAddress::class, 'receiver_address_id');
    }

    /**
     * @return string
     */
    public function getNumberFullAttribute()
    {
        return $this->series . '-' . $this->number;
    }


    /**
     * @return string
     */
    public function getDownloadExternalXmlAttribute()
    {
        return $this->buildDownloadExternalUrl('xml');
    }

    /**
     * @return string
     */
    public function getDownloadExternalPdfAttribute()
    {
        return $this->buildDownloadExternalUrl('pdf');
    }

    /**
     * @return string
     */
    public function getDownloadExternalCdrAttribute()
    {
        return $this->buildDownloadExternalUrl('cdr');
    }

    /**
     * En jobs/cola no hay hostname HTTP: las rutas tenant de web.php no se registran.
     * Evita "Route [tenant.download.external_id] not defined" al exportar reportes.
     */
    protected function buildDownloadExternalUrl(string $type): string
    {
        if (!\Illuminate\Support\Facades\Route::has('tenant.download.external_id')) {
            return '';
        }

        return route('tenant.download.external_id', [
            'model' => 'dispatch',
            'type' => $type,
            'external_id' => $this->external_id,
        ]);
    }

    /**
     * @return BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'customer_id');
    }

    /**
     * @return BelongsTo
     */
    public function order_form()
    {
        return $this->belongsTo(OrderForm::class, 'reference_order_form_id');
    }

    /**
     * Se usa en la relacion con el inventario kardex en modules/Inventory/Traits/InventoryTrait.php.
     * Tambien se debe tener en cuenta modules/Inventory/Providers/InventoryKardexServiceProvider.php y
     * app/Providers/KardexServiceProvider.php para la correcta gestion de kardex
     *
     * @return MorphMany
     */
    public function inventory_kardex()
    {
        return $this->morphMany(InventoryKardex::class, 'inventory_kardexable');
    }

    public function getSecondaryLicensePlatesAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    public function setSecondaryLicensePlatesAttribute($value)
    {
        $this->attributes['secondary_license_plates'] = (is_null($value)) ? null : json_encode($value);
    }

    /**
     * @param \Illuminate\Database\Query\Builder|Builder $query
     *
     * @return \Illuminate\Database\Query\Builder|Builder|null
     */
    public function scopeWhereTypeUser($query, $params = [])
    {
        if (isset($params['user_id'])) {
            $user_id = (int)$params['user_id'];
            $user = User::find($user_id);
            if (!$user) {
                $user = new User();
            }
        } else {
            $user = auth()->user();
        }
        return ($user->type === 'admin') ? null : $query->where('user_id', $user->id)->orWhere('seller_id', $user->id)->latest();
    }

    /**
     * @return BelongsTo
     */
    public function sale_note()
    {
        return $this->belongsTo(SaleNote::class, 'reference_sale_note_id');
    }

    /**
     * @return BelongsTo
     */
    public function order_note()
    {
        return $this->belongsTo(OrderNote::class, 'reference_order_note_id');
    }


    /**
     *
     * Obtener orden de pedido externa o relacionada de order form
     *
     * @return string
     */
    public function getOrderFormDescription()
    {

        $order_form_description = null;

        if ($this->order_form) {
            $order_form_description = $this->order_form->number_full;
        } else if ($this->order_form_external) {
            $order_form_description = $this->order_form_external;
        }

        return $order_form_description;

    }


    /**
     * Indica si la orden de entrega descontó stock físico al crearse.
     * Misma regla que InventoryKardexServiceProvider::dispatch().
     */
    public function discountsPhysicalStock(): bool
    {
        if ($this->document_type_id !== '09') {
            return false;
        }

        $transferReason = $this->getRelationValue('transfer_reason_type');
        if (!$transferReason || !$transferReason->discount_stock) {
            return false;
        }

        return !$this->reference_sale_note_id
            && !$this->reference_order_note_id
            && !$this->reference_document_id;
    }

    /**
     * Retorna un standar de nomenclatura para el modelo
     *
     * @return array
     */
    public function getCollectionData()
    {

        $has_cdr = false;

        if (in_array($this->state_type_id, ['05', '07'])) {
            $has_cdr = true;
        }

        $documents = [];

        if ($this->generate_document) $documents [] = ['description' => $this->generate_document->number_full];
        if ($this->reference_document) $documents [] = ['description' => $this->reference_document->number_full];


        $btn_pdf = true;
        $btn_send = false;
        $btn_options = false;
        $btn_status_ticket = false;
        $btn_edit = false;
        $btn_generate_document = config('tenant.internal_dispatch') ? config('tenant.internal_dispatch') : false;

        if ($this->state_type_id === '01') {
            $btn_send = true;
        }
        if ($this->state_type_id === '03') {
            $btn_status_ticket = true;
        }
        if ($this->state_type_id === '05') {
            //$btn_pdf = true;
            $btn_options = true;
            $btn_generate_document = true;
        }

        if ($this->state_type_id !== '05') {
            $btn_edit = true;
        }

        $btn_voided = false;
        if (
            $this->discountsPhysicalStock()
            && !in_array($this->state_type_id, ['09', '11'], true)
        ) {
            $btn_voided = true;
        }

//        if(!is_null($this->reference_sale_note_id) || !is_null($this->reference_document_id) ||
//            !is_null($this->reference_quotation_id) || !is_null($this->reference_order_form_id) ||
//            !is_null($this->reference_order_note_id) ) {
//            $btn_edit = false;
//        }
        $customer_name = null;
        $customer_number = null;
        if($this->customer) {
            $customer_name= $this->customer->name;
            $customer_number = format_person_identity_document($this->customer);
        }

        $sender_name = null;
        $sender_number = null;
        if($this->sender_data) {
            $sender_name= $this->sender_data['name'];
            $sender_number = $this->sender_data['number'];
        }

        $receiver_name = null;
        $receiver_number = null;
        if($this->receiver_data) {
            $receiver_name= $this->receiver_data['name'];
            $receiver_number = $this->receiver_data['number'];
        }

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'group_id' => $this->group_id,
            'fiscal_environment' => $this->fiscal_environment,
            'date_of_issue' => $this->date_of_issue->format('Y-m-d'),
            'number' => $this->number_full,
            'customer_id' => $this->customer_id,
            'customer_name' => $customer_name,
            'customer_number' => $customer_number,
            'custom_fields_data' => $this->custom_fields_data,
            'sender_name' => $sender_name,
            'sender_number' => $sender_number,
            'receiver_name' => $receiver_name,
            'receiver_number' => $receiver_number,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'date_of_shipping' => $this->date_of_shipping->format('Y-m-d'),
            'state_type_id' => $this->state_type_id,
            'state_type_description' => $this->state_type->description,
            'has_xml' => $this->has_xml,
            'has_pdf' => $this->has_pdf,
            // 'has_cdr' => $this->has_cdr,
            'dispatcher' => $this->dispatcher,
            'type_disparcher' => $this->getTypeDispatcher(),
            'has_cdr' => $has_cdr,
            'download_external_xml' => $this->download_external_xml,
            'download_external_pdf' => $this->download_external_pdf,
            'download_external_cdr' => $this->download_external_cdr,
            'reference_document_id' => $this->reference_document_id,
            'reference_order_note_id' => $this->reference_order_note_id,
            'order_notes' => $this->order_note,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'btn_generate_document' => $this->generate_document || $this->reference_document_id || !$btn_generate_document ? false : true,
            'transfer_reason_type' => $this->transfer_reason_type,
            'transfer_reason_description' => $this->transfer_reason_description,
            'documents' => $documents,
            'order_form_description' => $this->getOrderFormDescription(),
            'btn_status_ticket' => $btn_status_ticket,
            'btn_send' => $btn_send,
            'btn_pdf' => $btn_pdf,
            'btn_options' => $btn_options,
            'btn_edit' => $btn_edit,
            'btn_voided' => $btn_voided,
            'has_transport_driver_01'=> $this->has_transport_driver_01,
            'sunat_error_response' => $this->sunat_error_response,
        ];
    }


    /**
     * Devuelve la clase Facturalo con los elementos cargados
     *
     * @return Facturalo
     */
    public function getFacturalo()
    {

        $model = $this;
        return DB::connection('tenant')->transaction(function () use ($model) {
            $facturalo = new Facturalo();
            return $facturalo->loadDocument($model->id, 'dispatch');
        });

    }

    public function getTypeDispatcher()
    {
        return IdentityDocumentType::where('id', optional($this->dispatcher)->identity_document_type_id)->get();
    }

    /**
     * @return bool
     */

    public function getDataAffectedDocumentAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    public function setDataAffectedDocumentAttribute($value)
    {
        $this->attributes['data_affected_document'] = (is_null($value)) ? null : json_encode($value);
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeWhereStateTypeAccepted($query)
    {
        return $query->whereIn('state_type_id', ['01', '03', '05', '07', '13']);
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeWhereValuedKardexFormatSunat($query, $params)
    {
        return $query->whereIn('transfer_reason_type_id', ['01', '02', '04', '13'])
            ->whereStateTypeAccepted()
            ->whereTypeUser()
            ->whereBetween('date_of_issue', [$params->date_start, $params->date_end]);
    }


    /**
     * Obtiene el pdf basado en la impresion url/print/dispatch/{external_id}
     *
     * @return BinaryFileResponse|null
     */
    public function getPdf()
    {
        return DownloadController::getPdf(self::class, $this->external_id);

    }


    /**
     * Retornar descripción del documento relacionado (DAM)
     *
     * @return string|null
     */
    public function getRelatedDocumentTypeDescription()
    {

        if ($this->related) {
            $related_document = RelatedDocumentType::available()->firstWhere('id', $this->related->document_type_id);
            if ($related_document) return $related_document->description;
        }

        return null;
    }









    /**
     *
     * Retornar registro relacionado
     *
     * Orden de entrega generada desde: Cot, Nv, Ped
     *
     */
    public function getRelationExternalDocument()
    {
        if (!is_null($this->reference_quotation_id)) return $this->reference_quotation;

        if (!is_null($this->reference_sale_note_id)) return $this->sale_note;

        if (!is_null($this->reference_order_note_id)) return $this->order_note;

        return null;
    }


    /**
     *
     * Validar si existe relación
     *
     * @param $relation_external_document
     * @return bool
     */
    public function isGeneratedFromExternalDocument($relation_external_document)
    {
        return !is_null($relation_external_document);
    }


    /**
     *
     * Filtro para no incluir relaciones en consulta
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWhereFilterWithOutRelations($query)
    {
        return $query->withOut(['user', 'fiscal_environment_type', 'state_type', 'document_type', 'unit_type', 'transport_mode_type', 'transfer_reason_type', 'items', 'reference_document']);
    }

}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
