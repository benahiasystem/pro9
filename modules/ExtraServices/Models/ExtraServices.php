<?php

namespace Modules\ExtraServices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExtraServices extends Model
{
    // ########## INICIO CAMBIO POSTGRESQL 17
    use \App\Database\HasPostgresIdentity;
    // ######### FIN CAMBIO POSTGRESQL 17

    use HasFactory;

    protected $table = 'extra_services';

    protected $fillable = [
        'urlObtainApidocs',
        'urlServiceApidocs',
        'isActiveApidocs',
    ];

    protected $casts = [
        'isActiveApidocs' => 'boolean',
    ];
    
    protected static function newFactory()
    {
        return \Modules\ExtraServices\Database\factories\ExtraServiceFactory::new();
    }
}
