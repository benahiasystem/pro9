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
            ->whereHas('received_retentions',fn($q)=>$q->where('application_status','pending_validation'))
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
                    foreach (Storage::disk('tenant')->allFiles('received_retentions') as $copy) {
                        if ($copy !== $upload->path && basename($copy) === basename($upload->path)
                            && !\App\Models\Tenant\DocumentReceivedRetention::where('attachment',$copy)->exists()) Storage::disk('tenant')->delete($copy);
                    }
                }
                Storage::disk('tenant')->delete($upload->path);
                $upload->delete();
            });
        }
        return 0;
    }
}
