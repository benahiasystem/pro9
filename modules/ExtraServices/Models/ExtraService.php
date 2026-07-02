<?php

namespace Modules\ExtraServices\Models; // O \Models según tu versión

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ExtraService extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo.
     * Al ser un modelo de System, apuntará a la BD central.
     *
     * @var string
     */
    protected $table = 'extra_services';

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array
     */
    protected $fillable = [
        'service',
        'is_active',
    ];

    /**
     * Los atributos que deben ser casteados a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean', // Convierte automáticamente el 1/0 de la BD a true/false en PHP
    ];

    /**
     * Obtener la configuración de un servicio.
     * @param string $serviceName Nombre del servicio a consultar
     * @return ExtraService|null Retorna un array con la configuración o null si no existe.
     */
    public static function getServiceConfig(string $serviceName): ?ExtraService
    {
        return self::where('service', $serviceName)->first();
    }

}