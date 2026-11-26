<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Models\Storage;
use Illuminate\Database\Eloquent\Model;
use App\Tenancy\Traits\UsesSystemConnection;
final class StorageOperationFile extends Model {
    use UsesSystemConnection;
    public $timestamps=false;
    protected $guarded=[];
    protected $casts=['deleted'=>'boolean'];
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
