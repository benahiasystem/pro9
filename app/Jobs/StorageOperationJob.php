<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
final class StorageOperationJob implements ShouldQueue {
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public $tries=3;
    public $timeout=900;
    public string $operationId;
    public function __construct(string $operationId){$this->operationId=$operationId;$this->onConnection('storage-admin');$this->onQueue('storage-admin');$this->afterCommit();}
    public function handle(): void {app(\App\Services\Storage\StorageAdministration::class)->run($this->operationId);}
    public function backoff(): array {return [15,60,180];}
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
