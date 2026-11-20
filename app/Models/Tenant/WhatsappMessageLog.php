<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class WhatsappMessageLog extends Model
{
    // ########## INICIO CAMBIO POSTGRESQL 17
    use \App\Database\HasPostgresIdentity;
    // ######### FIN CAMBIO POSTGRESQL 17

    use UsesTenantConnection;

    protected $table = 'whatsapp_message_logs';

    protected $fillable = [
        'phone',
        'filename',
        'status',
    ];
}
