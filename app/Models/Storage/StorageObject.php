<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Models\Storage;
use Illuminate\Database\Eloquent\Model;
use App\Tenancy\Traits\UsesTenantConnection;
final class StorageObject extends Model {
    use UsesTenantConnection;
    protected $primaryKey='path';
    public $incrementing=false;
    protected $keyType='string';
    protected $guarded=[];
    protected $hidden=['object_key','sha256'];
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
