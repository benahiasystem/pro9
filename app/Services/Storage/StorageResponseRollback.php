<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;
final class StorageResponseRollback extends \RuntimeException {
    public $result;
    public function __construct($result) { parent::__construct('La operación no se confirmó.');$this->result=$result; }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
