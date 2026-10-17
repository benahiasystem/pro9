<?php

namespace App\Models\System;

use Carbon\Carbon;
use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class PaymentOrder extends ModelSystem
{
    use UsesSystemConnection;

    public $timestamps = false;
    protected $fillable = [
        'uuid',
        'order',
        'date_of_due',
        'notifications',
        'amount',
        'order_state_id',
        'client_id',
        'plan_id',
        'date_of_payment',
        'date_of_notification',
        'description',
        'created_by'
    ];

    protected static function booted()
    {
        static::creating(function (PaymentOrder $order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    protected $casts = [
        'date_of_due' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function order_state()
    {
        return $this->belongsTo(PaymentOrderState::class, 'order_state_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function getCollectionData()
    {
        $date_of_payment = $this->date_of_payment ? Carbon::parse($this->date_of_payment)->format('Y-m-d'): null;
        $date_of_notification = $this->date_of_notification ? Carbon::parse($this->date_of_notification)->diffForHumans() : 'Sin notificar';
        return [
            'id' => $this->id,
            'order' => $this->order,
            'due_date' => $this->date_of_due->format('Y-m-d'),
            'notifications' => $this->notifications,
            'amount' => $this->amount,
            'calculation_snapshot' => $this->calculation_snapshot,
            'order_state_id' => $this->order_state_id,
            'order_state' => $this->order_state ? $this->order_state->name : '',
            'client' => $this->client ? $this->client->name : '',
            'date_of_payment' => $date_of_payment,
            'diff_notification' => $date_of_notification, 
            'created_by' => $this->created_by,
            'description' => $this->description ?? 'Pago de servicio',
            // La ruta solo existe en el dominio del sistema; en contexto tenant no esta registrada
            'url_view' => ($this->order_state_id !== 2 && Route::has('system.payments-view.index'))
                ? route('system.payments-view.index', ['uuid' => $this->uuid])
                : null
        ]; 

    }

    /**
     *
     * @param  builder $query
     * @return builder
     */
    public static function exactPaymentsTotals($client_id = null, bool $byClient = false)
    {
        $query=static::query()->select(['id','client_id','order_state_id','amount','calculation_snapshot']);
        if ($client_id) $query->where('client_id',$client_id);
        $totals=[];
        foreach ($query->lazyById(500) as $order) {
            $key=($byClient ? $order->client_id.':' : '').$order->order_state_id;
            if (!isset($totals[$key])) $totals[$key]=['id'=>$order->order_state_id,'client_id'=>$byClient ? $order->client_id : null,'value'=>\App\Support\Exact\Decimal::of(0)];
            $totals[$key]['value']=$totals[$key]['value']->plus($order->exactValue('amount'))->simplified();
        }
        return collect(array_values($totals))->map(fn($row)=>(object)[
            'id'=>$row['id'],'client_id'=>$row['client_id'],
            'total'=>\App\Support\Exact\Decimal::represent($row['value']),
            'calculation_snapshot'=>\App\Support\Exact\Decimal::snapshot(['total'=>$row['value']]),
        ]);
    }


}
