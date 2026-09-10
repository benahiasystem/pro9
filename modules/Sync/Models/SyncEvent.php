<?php

namespace Modules\Sync\Models;

use App\Models\Tenant\ModelTenant;

class SyncEvent extends ModelTenant
{
    protected $table = 'sync_events';

    protected $fillable = [
        'machine_id',
        'user_id',
        'seq',
        'type',
        'external_id',
        'occurred_at',
        'payload',
        'status',
        'message',
        'document_id',
        'cash_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function machine()
    {
        return $this->belongsTo(OfflineMachine::class, 'machine_id');
    }

    /** Resultado con el que se responde a la máquina (también en reintentos). */
    public function toResult(): array
    {
        return [
            'external_id' => $this->external_id,
            'status' => $this->status,
            'message' => $this->message,
            'document_id' => $this->document_id,
        ];
    }
}
