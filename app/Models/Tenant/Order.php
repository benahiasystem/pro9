<?php

namespace App\Models\Tenant;


use Illuminate\Database\Eloquent\SoftDeletes;
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
            'items' => $this->items,
            'total' => $this->total,
            'reference_payment' => strtoupper($this->reference_payment),
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
