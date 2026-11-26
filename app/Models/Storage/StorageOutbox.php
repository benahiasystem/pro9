<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Models\Storage;
use Illuminate\Database\Eloquent\Model;
use App\Tenancy\Traits\UsesTenantConnection;
final class StorageOutbox extends Model {
    use UsesTenantConnection;
    protected $table = 'storage_outbox';
    protected $guarded = [];
    protected $hidden = ['source', 'path', 'sha256'];
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
