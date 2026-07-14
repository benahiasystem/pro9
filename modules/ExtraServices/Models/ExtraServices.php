<?php

namespace Modules\ExtraServices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExtraServices extends Model
{
    use HasFactory;

    protected $table = 'extra_services';

    protected $fillable = [
        'isActiveApidocs',
        'urlApidocs',
    ];

    protected $casts = [
        'isActiveApidocs' => 'boolean',
    ];
    
    protected static function newFactory()
    {
        return \Modules\ExtraServices\Database\factories\ExtraServiceFactory::new();
    }
}
