<?php

namespace App\Console\Commands;

use App\Models\Tenant\{Document, DocumentIvaRetentionOperation, ReceivedRetentionUpload};
use App\Services\Fiscal\{IvaRetentionService,HkaIvaRetention};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RecoverIvaRetentions extends Command
{
    protected $signature = 'iva-retentions:recover {--limit=50}';
    protected $description = 'Concilia operaciones IVA pendientes del tenant y limpia adjuntos temporales vencidos';

    public function handle(): int
    {
        if (!IvaRetentionService::available()) return 0;
        $limit = max(1,min(500,(int)$this->option('limit')));
        Document::whereNotNull('control_number')
            ->where(fn($q)=>$q->where('fiscal_emission_mode','!=','digital')->orWhereHas('emission',fn($q)=>$q->where('status','confirmed')))
            ->whereHas('received_retentions',fn($q)=>$q->where('tax_kind','IVA')->where('application_status','pending_validation'))
            ->limit($limit)->get()->each(fn($doc)=>IvaRetentionService::resume($doc));
        foreach (DocumentIvaRetentionOperation::whereIn('status',['waiting','uncertain','pending'])
            ->where(fn($q)=>$q->whereNull('next_attempt_at')->orWhere('next_attempt_at','<=',now()))->orderBy('id')->limit($limit)->get() as $op) {
            try { app(HkaIvaRetention::class)->process($op->retention); }
            catch (\Throwable $ignored) { /* Retain the durable operation and continue with other invoices. */ }
        }
        foreach (ReceivedRetentionUpload::where('expires_at','<',now())->limit(500)->get() as $candidate) {
            $candidate->getConnection()->transaction(function () use ($candidate) {
                // The same lock used for consumption prevents deleting a copy during invoice commit.
                $upload = ReceivedRetentionUpload::query()->lockForUpdate()->find($candidate->id);
                if (!$upload || !$upload->expires_at->isPast()) return;
                if (!$upload->document_id) {
                    // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                    foreach (app(\App\Services\Storage\TenantStorage::class)->disk()->allFiles('received_retentions') as $copy) {
                    // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                        if ($copy !== $upload->path && basename($copy) === basename($upload->path)
                            // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                            && !\App\Models\Tenant\DocumentReceivedRetention::where('attachment',$copy)->exists()) app(\App\Services\Storage\TenantStorage::class)->disk()->delete($copy);
                            // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                    }
                }
                // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                app(\App\Services\Storage\TenantStorage::class)->disk()->delete($upload->path);
                // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                $upload->delete();
            });
        }
        return 0;
    }
}
