<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Str;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Models\{SourceDocument, SourceVersion};
use Modules\Accounting\Support\{AuditPayload, SourceHistoryHash, TenantDB as DB};

final class SourceHistoryService
{
    public function append(SourceDocument $document, array $snapshot, string $sourceHash, int $user, int $revision, string $disposition = 'received'): SourceVersion
    {
        $context = app(CompanyContext::class);
        if ($document->organization_id !== $context->organizationId() || $document->company_id !== $context->id()) { throw new \LogicException('El antecedente no pertenece al libro activo.'); }
        if (!preg_match('/^[0-9a-f]{64}$/D', $sourceHash) || !in_array($disposition, ['received', 'pending_reconciliation'], true)) { throw new \LogicException('Identidad de antecedente inválida.'); }
        $actor = DB::table('users')->where('id', $user)->where('active', true)->where('locked', false)->first(['id', 'name']);
        if (!$actor) { throw new ApiProblem('FORBIDDEN_ACTION', 'La cuenta no puede conservar versiones del origen.', 403); }
        $now = now('America/Caracas')->toDateTimeString();
        $record = new SourceVersion(['id' => (string) Str::uuid(), 'source_document_id' => $document->id, 'revision' => $revision,
            'snapshot' => $snapshot, 'hash' => $sourceHash, 'integrity_version' => 2, 'recorded_by' => $user, 'recorded_by_name' => $actor->name,
            'origin_kind' => $document->origin, 'disposition' => $disposition, 'document_version' => $document->version, 'document_snapshot' => $document->toArray(), 'created_at' => $now, 'updated_at' => $now]);
        $record->forceFill(['organization_id' => $context->organizationId(), 'company_id' => $context->id()]);
        $record->integrity_hash = SourceHistoryHash::digest($record->getAttributes());
        $record->save();
        return $record;
    }

    public function integrity(SourceVersion $version): array
    {
        if ((int) $version->integrity_version === 1) { return ['state' => 'legacy', 'version' => 1, 'note' => 'Antecedente histórico conservado. Su huella original no permite verificar toda la serialización JSON con este formato.']; }
        try {
            $valid = is_string($version->integrity_hash) && hash_equals($version->integrity_hash, SourceHistoryHash::digest($version->getRawOriginal()));
        } catch (\Throwable) { $valid = false; }
        return ['state' => $valid ? 'verified' : 'failed', 'version' => (int) $version->integrity_version, 'note' => $valid ? 'Contenido y contexto del registro comprobados. No equivale a una firma electrónica certificada.' : 'El contenido o contexto del antecedente no coincide con su huella conservada.'];
    }

    public function metadata(SourceVersion $row, SourceDocument $document): array
    {
        return [...$row->only(['id', 'revision', 'hash', 'integrity_version', 'integrity_hash', 'recorded_by', 'recorded_by_name', 'origin_kind', 'disposition', 'document_version']),
            'source_document_id' => $document->id, 'company_id' => app(CompanyContext::class)->id(), 'created_at' => $row->created_at->setTimezone('America/Caracas')->toIso8601String(),
            'current_document_revision' => (int) $document->source_revision, 'is_current_applied' => (int) $row->revision === (int) $document->source_revision];
    }

    public function requireCurrent(SourceDocument $document): SourceVersion
    {
        $version = SourceVersion::where('source_document_id', $document->id)->where('revision', $document->source_revision)->first();
        if (!$version) { throw new ApiProblem('SOURCE_HISTORY_MISSING', 'Falta el antecedente conservado de esta revisión. Revisa su recuperación antes de continuar.', 409); }
        if ($this->integrity($version)['state'] === 'failed' || $document->origin === 'pro9' && !hash_equals((string) ($document->metadata['source_hash'] ?? ''), $version->hash)) {
            throw new ApiProblem('SOURCE_HISTORY_INTEGRITY', 'El antecedente no coincide con su contenido o referencia conservados. Revisa la evidencia antes de continuar.', 409);
        }
        return $version;
    }

    public function listing(string $documentId, int $page = 1): array
    {
        return DB::readSnapshot(function () use ($documentId, $page) {
            $document = SourceDocument::findOrFail($documentId);
            $rows = SourceVersion::where('source_document_id', $document->id)->orderByDesc('revision')->paginate(25, ['id', 'source_document_id', 'revision', 'hash', 'integrity_version', 'integrity_hash', 'recorded_by', 'recorded_by_name', 'origin_kind', 'disposition', 'document_version', 'created_at'], 'page', $page);
            return ['data' => $rows->getCollection()->map(fn($row) => $this->metadata($row, $document))->all(), 'meta' => ['company_id' => app(CompanyContext::class)->id(), 'document_id' => $document->id, 'page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()]];
        });
    }

    public function show(string $documentId, string $versionId): array
    {
        return DB::readSnapshot(function () use ($documentId, $versionId) {
            $document = SourceDocument::findOrFail($documentId);
            $version = SourceVersion::where('source_document_id', $document->id)->findOrFail($versionId);
            $raw = $version->getRawOriginal();
            $snapshot = is_array($version->snapshot) ? $version->snapshot : []; $native = ($version->origin_kind ?? $document->origin) === 'pro9';
            $amounts = is_array($snapshot['amounts'] ?? null) ? $snapshot['amounts'] : [];
            $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];
            $candidate = $native ? ($snapshot['party'] ?? null) : ($snapshot['party_snapshot'] ?? $metadata['party_snapshot'] ?? null);
            $party = is_array($candidate) ? $candidate : null;
            $text = fn($value) => is_string($value) ? $value : '';
            $money = fn($value) => is_string($value) ? $value : null;
            $summary = ['kind' => $text($snapshot['kind'] ?? null), 'number' => $text($snapshot['number'] ?? null), 'issue_date' => $text($snapshot['issue_date'] ?? null), 'currency' => $text($snapshot['currency'] ?? null),
                'party' => $party ? ['name' => $text($party['name'] ?? null), 'tax_id' => $text($party['tax_id'] ?? null)] : null,
                'state' => $text($snapshot['state'] ?? $snapshot['status'] ?? null), 'amounts' => ['base' => $money($amounts[$native ? 'total_value' : 'base'] ?? null), 'vat' => $money($amounts[$native ? 'total_igv' : 'vat'] ?? null), 'total' => $money($amounts['total'] ?? null)]];
            $fiscal = \Modules\Accounting\Models\FiscalRecord::where('source_document_id', $document->id)->where('rule_snapshot->source_revision', $version->revision)->orderBy('version')->get()->toArray();
            $resolutions = \Modules\Accounting\Models\SourceResolution::where('source_document_id', $document->id)->where(fn($q) => $q->where('source_version_id', $version->id)->orWhere('base_revision', $version->revision))->orderBy('created_at')->get(['id', 'incident_id', 'action', 'reason', 'reviewed_by_name', 'source_version_id', 'base_revision', 'integrity_hash', 'created_at'])->toArray();
            return [...$this->metadata($version, $document), 'summary' => $summary, 'integrity' => $this->integrity($version), 'origin_snapshot_json' => AuditPayload::text($raw['snapshot']),
                'document_snapshot_json' => isset($raw['document_snapshot']) ? AuditPayload::text($raw['document_snapshot']) : null,
                'fiscal_records_json' => AuditPayload::text(json_encode($fiscal, JSON_THROW_ON_ERROR)), 'resolutions' => $resolutions,
                'posted_entries' => \Modules\Accounting\Models\JournalEntry::where('source_document_id', $document->id)->where('source_revision', $version->revision)->whereNotNull('posted_at')->get(['id', 'reference', 'source_revision', 'posted_at'])->toArray()];
        });
    }

    public function revision(string $documentId, int $revision): array
    {
        $document = SourceDocument::findOrFail($documentId);
        $version = SourceVersion::where('source_document_id', $document->id)->where('revision', $revision)->firstOrFail();
        return $this->show($document->id, $version->id);
    }
}
