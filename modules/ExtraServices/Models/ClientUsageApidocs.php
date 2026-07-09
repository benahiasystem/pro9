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
     * Incrementa el contador de uso para el mes actual del cliente
     *
     * @param int $clientId
     * @return self
     */
    public static function incrementUsage($clientId)
    {
        $currentMonth = now()->format('Y-m');

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