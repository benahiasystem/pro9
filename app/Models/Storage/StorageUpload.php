<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Models\Storage;
use Illuminate\Database\Eloquent\Model;
use App\Tenancy\Traits\UsesTenantConnection;
final class StorageUpload extends Model {
    use UsesTenantConnection;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['path', 'sha256', 'targets'];
    protected $casts = ['expires_at'=>'datetime', 'consumed_at'=>'datetime', 'targets'=>'array'];
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
