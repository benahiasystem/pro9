<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Models\Storage;
use Illuminate\Database\Eloquent\Model;
use App\Tenancy\Traits\UsesSystemConnection;
final class StorageOperation extends Model {
    use UsesSystemConnection;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['input'=>'array', 'result'=>'array'];
    protected $hidden = ['input'];
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
