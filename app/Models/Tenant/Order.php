<?php

namespace App\Models\Tenant;


use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Helpers\HeaderNotifications;
use App\Models\Tenant\Document;
use Modules\Ecommerce\Models\Tenant\DiscountCoupon;


class Order extends ModelTenant
{
    use SoftDeletes;

    protected $fillable = [
        'external_id',
        'customer',
        'shipping_address',
        'items',
        'total',
        'reference_payment',
        'document_external_id',
        'number_document',
        'status_order_id',
        'payment_status_order_id',
        'shipping_status_order_id',
        'purchase',
        'total_discount',
        'discount_coupon_code',
        'discount_coupon_id',
        'stock_discounted',
        'apply_restaurant'
    ];

    protected $casts = [
        'customer' => 'object',
        'items' => 'object',
        'purchase' => 'object',
        'stock_discounted' => 'boolean'
    ];

    public function status_order()
    {
        return $this->belongsTo(StatusOrder::class);
    }

    public function payment_status_order()
    {
        return $this->belongsTo(StatusOrder::class, 'payment_status_order_id');
    }

    public function shipping_status_order()
    {
        return $this->belongsTo(StatusOrder::class, 'shipping_status_order_id');
    }

    public function sale_note()
    {
        return $this->hasOne(SaleNote::class);
    }

    public function discount_coupon()
    {
        return $this->belongsTo(DiscountCoupon::class, 'discount_coupon_id');
    }

    /**
     * Pedidos que requieren atención del administrador (pago sin verificar u otros estados activos).
     */
    public function scopePendingForNotification($query)
    {
        return HeaderNotifications::pendingOrdersQuery($query);
    }

    /**
     * Retorna un standar de nomenclatura para el modelo
     *
     * @return array
     */
    public function getCollectionData()
    {
        $data = [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'number_document' => $this->number_document,
            'order_id' => str_pad($this->id, 6, "0", STR_PAD_LEFT),
            'customer' => $this->customer->apellidos_y_nombres_o_razon_social,
            'customer_email' => $this->customer->correo_electronico,
            'customer_telefono' => $this->customer->telefono,
            'customer_direccion' => $this->customer->direccion,
            'is_guest' => $this->isGuestCheckout(),
            'items' => $this->items,
            'total' => $this->total,
            'reference_payment' => strtoupper($this->reference_payment),
            'payment_transaction_id' => data_get($this->purchase, 'gateway_payment.charge_id'),
            'payment_gateway_status' => data_get($this->purchase, 'gateway_payment.panel_status'),
            'document_external_id' => $this->document_external_id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'status_order_id' => $this->status_order_id,
            'payment_status_order_id' => $this->payment_status_order_id,
            'shipping_status_order_id' => $this->shipping_status_order_id,
            'purchase' => $this->purchase,
            'status_order_description' => $this->status_order->description ?? null,
            'payment_status_order_description' => $this->payment_status_order->description ?? null,
            'shipping_status_order_description' => $this->shipping_status_order->description ?? null,
            'total_discount' => $this->total_discount,
            'discount_coupon_code' => $this->discount_coupon_code,
            'discount_coupon' => $this->discount_coupon ? $this->discount_coupon->getCollectionData() : null,
            'returns_blocked' => $this->returnsBlocked(),
            'is_voided' => $this->isVoided(),
        ];

        return $data;
    }

    /**
     * Indica si el pedido se realizó como invitado en la tienda virtual.
     *
     * Prioridad:
     * 1) Marca explícita purchase.checkout.is_guest (pedidos nuevos)
     * 2) ecommerce_customer_id guardado al comprar con sesión
     * 3) Pedidos antiguos: si el correo/documento del pedido coincide con
     *    una cuenta ecommerce (Person con password), se considera autenticado
     */
    public function isGuestCheckout(): bool
    {
        $flag = data_get($this->purchase, 'checkout.is_guest');
        if ($flag !== null) {
            return (bool) $flag;
        }

        if (data_get($this->purchase, 'checkout.ecommerce_customer_id')) {
            return false;
        }

        return ! $this->matchesEcommerceAccount();
    }

    /**
     * ¿El comprador del pedido corresponde a una cuenta ecommerce registrada?
     */
    protected function matchesEcommerceAccount(): bool
    {
        $email = strtolower(trim((string) (
            data_get($this->customer, 'correo_electronico')
            ?: data_get($this->customer, 'email')
            ?: ''
        )));
        $document = preg_replace(
            '/\D+/',
            '',
            (string) (
                data_get($this->customer, 'numero_documento')
                ?? data_get($this->customer, 'number')
                ?? ''
            )
        );

        if ($email === '' && ($document === '' || $document === '0')) {
            return false;
        }

        return Person::query()
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->where(function ($query) use ($email, $document) {
                if ($email !== '') {
                    $query->whereRaw('LOWER(email) = ?', [$email]);
                }
                if ($document !== '' && $document !== '0') {
                    $method = $email !== '' ? 'orWhere' : 'where';
                    $query->{$method}('number', $document);
                }
            })
            ->exists();
    }

    /**
     * Adjunta metadatos de checkout ecommerce al payload purchase.
     *
     * @param  mixed  $ecommerceUser  Cliente autenticado del guard ecommerce (Person) o null
     * @param  bool|null  $isGuestOverride  Si viene del front, manda sobre la sesión
     */
    public static function attachEcommerceCheckoutMeta(array $purchase, $ecommerceUser = null, ?bool $isGuestOverride = null): array
    {
        $isGuest = $isGuestOverride ?? ($ecommerceUser === null);

        $purchase['checkout'] = array_merge((array) ($purchase['checkout'] ?? []), [
            'channel' => 'ecommerce',
            'is_guest' => $isGuest,
            'ecommerce_customer_id' => $isGuest ? null : ($ecommerceUser->id ?? null),
        ]);

        return $purchase;
    }

    /**
     * Indica si el pedido está anulado: alguno de sus estados actuales tiene
     * activada la acción "Anular pedido".
     */
    public function isVoided(): bool
    {
        $statuses = static::orderStatusesCache();
        $currentIds = [$this->status_order_id, $this->payment_status_order_id, $this->shipping_status_order_id];

        foreach ($currentIds as $id) {
            $current = $id ? $statuses->get($id) : null;
            if ($current && $current->action_void_order) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indica si el pedido ya no acepta devoluciones: es true cuando el pedido
     * alcanzó (o superó) el estado marcado con "Bloquear devoluciones",
     * comparando por sort_order dentro del mismo grupo del estado bloqueador.
     */
    public function returnsBlocked(): bool
    {
        $statuses = static::orderStatusesCache();
        $blockers = $statuses->where('action_block_returns', true);

        foreach ($blockers as $blocker) {
            if ($blocker->is_payment_status) {
                $currentId = $this->payment_status_order_id;
            } elseif ($blocker->is_shipping_status) {
                $currentId = $this->shipping_status_order_id;
            } else {
                $currentId = $this->status_order_id;
            }

            $current = $currentId ? $statuses->get($currentId) : null;
            if ($current && $current->sort_order >= $blocker->sort_order) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cachea la colección de estados por request para evitar N+1 al transformar pedidos.
     */
    protected static $orderStatusesCache = null;

    protected static function orderStatusesCache()
    {
        if (static::$orderStatusesCache === null) {
            static::$orderStatusesCache = StatusOrder::get()->keyBy('id');
        }

        return static::$orderStatusesCache;
    }
}
