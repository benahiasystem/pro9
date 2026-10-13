<?php

namespace App\Console\Commands;

use App\Models\Tenant\{Document, DocumentIslrRetentionOperation, ReceivedRetentionUpload};
use App\Services\Fiscal\{IslrRetentionService,HkaIslrRetention};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RecoverIslrRetentions extends Command
{
    protected $signature = 'islr-retentions:recover {--limit=50}';
    protected $description = 'Concilia operaciones ISLR pendientes del tenant';

    public function handle(): int
    {
        if (!IslrRetentionService::available()) return 0;
        $limit = max(1,min(500,(int)$this->option('limit')));
        Document::whereNotNull('control_number')
            ->where(fn($q)=>$q->where('fiscal_emission_mode','!=','digital')->orWhereHas('emission',fn($q)=>$q->where('status','confirmed')))
            ->whereHas('received_retentions',fn($q)=>$q->where('tax_kind','ISLR')->where('application_status','pending_validation'))
            ->limit($limit)->get()->each(fn($doc)=>IslrRetentionService::resume($doc));
        foreach (DocumentIslrRetentionOperation::whereIn('status',['waiting','uncertain','pending'])
            ->where(fn($q)=>$q->whereNull('next_attempt_at')->orWhere('next_attempt_at','<=',now()))->orderBy('id')->limit($limit)->get() as $op) {
            try { app(HkaIslrRetention::class)->process($op->retention); }
            catch (\Throwable $ignored) { /* Retain the durable operation and continue with other invoices. */ }
        }
        return 0;
    }
}
