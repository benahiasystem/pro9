<?php

namespace Modules\ExtraServices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClientUsageApidocs extends Model
{
    use HasFactory;

    // Nombre de la tabla en la base de datos
    protected $table = 'extra_services_client_usage_apidocs';

    // Campos que se pueden llenar masivamente
    protected $fillable = [
        'client_id',
        'month',
        'quantity'
    ];

    /**
     * Relación: Un registro de uso pertenece a un cliente.
     */
    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id', 'id');
    }

    /**
     * Scope: solo el consumo del admin / reseller (sin cliente asociado).
     */
    public function scopeSystem($query)
    {
        return $query->whereNull('client_id');
    }

    /**
     * Incrementa el contador de uso para el mes actual.
     *
     * @param int|null $clientId  null = consumo del admin / reseller, es decir
     *                            consultas hechas desde el panel del sistema y
     *                            no desde un tenant.
     * @return self
     */
    public static function incrementUsage($clientId = null)
    {
        $currentMonth = now()->format('Y-m');

        // firstOrCreate con client_id null resuelve a "where client_id is null",
        // asi que todas las consultas del sistema caen en la misma fila del mes.
        $usage = static::firstOrCreate(
            [
                'client_id' => $clientId,
                'month' => $currentMonth,
            ],
            [
                'quantity' => 0,
            ]
        );

        $usage->increment('quantity');

        return $usage;
    }
}