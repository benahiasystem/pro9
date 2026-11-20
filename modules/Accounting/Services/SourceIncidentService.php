<?php

namespace Modules\Accounting\Services;

use App\Models\Tenant\User;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Permissions};
use Modules\Accounting\Models\{Incident, SourceDocument, SourceVersion, SourceResolution, JournalEntry, OpenItem, FiscalRecord};
use Modules\Accounting\Support\{TenantDB as DB, AuditHash, AuditPayload, SourceResolutionHash};

final class SourceIncidentService
{
    private function actor(int $id): User
    {
        $user = User::without('establishment')->whereKey($id)->where('active', true)->where('locked', false)->first();
        if (!$user) { throw new ApiProblem('FORBIDDEN_ACTION', 'La cuenta no puede resolver cambios de SAB.', 403); }
        foreach (['integration', 'review'] as $permission) { app(Permissions::class)->require($user, $permission); }
        return $user;
    }

    private function target(string $id, bool $lock = false): array
    {
        $incident = Incident::where('kind', 'source_changed')->where('resource_type', 'source_document')->when($lock, fn($q) => $q->lockForUpdate())->findOrFail($id);
        $document = SourceDocument::where('origin', 'pro9')->when($lock, fn($q) => $q->lockForUpdate())->findOrFail($incident->resource_id);
        $q = SourceVersion::where('source_document_id', $document->id);
        $version = !empty($incident->details['source_version_id']) ? $q->findOrFail($incident->details['source_version_id']) : $q->where('revision', $incident->details['revision'] ?? 0)->firstOrFail();
        return [$incident, $document, $version];
    }

    public function ledger(SourceDocument $document): array
    {
        $openings = OpenItem::where('source_document_id', $document->id)->where('is_opening', true)->get(['origin_entry_id', 'opening_entry_id', 'status']);
        $ids = $openings->pluck('origin_entry_id')->merge($openings->pluck('opening_entry_id'))->filter()->unique()->all();
        $rows = JournalEntry::whereNull('reversal_of')->whereNotNull('posted_at')->where(fn($q) => $q->where('source_document_id', $document->id)->orWhereIn('id', $ids))->orderBy('id')->get();
        $reversals = JournalEntry::whereIn('reversal_of', $rows->pluck('id'))->get()->keyBy('reversal_of');
        return $rows->map(function ($entry) use ($reversals) {
            $reverse = $reversals->get($entry->id);
            return [...$entry->only(['id', 'reference', 'source_revision', 'posted_at']), 'active_effect' => !$reverse?->posted_at, 'reversal' => $reverse?->only(['id', 'reference', 'posted_at', 'status'])];
        })->all();
    }

    private function businessHash(array $snapshot): string
    {
        unset($snapshot['hash'], $snapshot['source_updated_at']);
        return AuditHash::contentDigest(json_decode(json_encode($snapshot, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR));
    }

    private function basisUnchanged(SourceDocument $document, SourceVersion $observed, array $ledger): bool
    {
        if (!is_array($observed->snapshot) || app(SourceHistoryService::class)->integrity($observed)['state'] === 'failed') { return false; }
        $revisions = array_unique(array_map(fn($entry) => (int) ($entry['source_revision'] ?? $document->source_revision), array_filter($ledger, fn($entry) => $entry['active_effect'])));
        if (!$revisions) { $revisions = [$document->source_revision]; }
        $target = $this->businessHash($observed->snapshot);
        foreach ($revisions as $revision) {
            $old = SourceVersion::where('source_document_id', $document->id)->where('revision', $revision)->first();
            if (!$old || !is_array($old->snapshot) || app(SourceHistoryService::class)->integrity($old)['state'] === 'failed' || !hash_equals($target, $this->businessHash($old->snapshot))) { return false; }
        }
        return true;
    }

    public function effectiveHash(SourceDocument $document): string
    {
        $row = SourceResolution::join('accounting_source_versions as v', function ($join) { $join->on('v.id', '=', 'accounting_source_resolutions.source_version_id')->on('v.organization_id', '=', 'accounting_source_resolutions.organization_id')->on('v.company_id', '=', 'accounting_source_resolutions.company_id'); })
            ->where('accounting_source_resolutions.source_document_id', $document->id)->where('v.source_document_id', $document->id)->where('action', 'acknowledge_metadata')->where('base_revision', $document->source_revision)
            ->orderByDesc('v.revision')->select('accounting_source_resolutions.*')->first();
        if (!$row) { return (string) ($document->metadata['source_hash'] ?? ''); }
        $this->requireResolutionIntegrity($row);
        $version = SourceVersion::where('source_document_id', $document->id)->findOrFail($row->source_version_id);
        if (app(SourceHistoryService::class)->integrity($version)['state'] === 'failed') { throw new ApiProblem('SOURCE_HISTORY_INTEGRITY', 'La versión reconocida del origen no conserva su integridad.', 409); }
        return $version->hash;
    }

    private function requireResolutionIntegrity(SourceResolution $row): void
    {
        try { $valid = is_string($row->integrity_hash) && hash_equals($row->integrity_hash, SourceResolutionHash::digest($row->getRawOriginal())); }
        catch (\Throwable) { $valid = false; }
        if (!$valid) { throw new ApiProblem('SOURCE_RESOLUTION_INTEGRITY', 'La resolución conservada no coincide con su evidencia. Revisa la recuperación del historial.', 409); }
    }

    public function requireReplacementPermit(SourceDocument $document): void
    {
        $version = app(SourceHistoryService::class)->requireCurrent($document);
        $resolution = $document->origin === 'pro9' ? SourceResolution::where('source_document_id', $document->id)->where('source_version_id', $version->id)->where('action', 'apply_after_reversal')->first() : null;
        if (!$resolution || JournalEntry::where('source_document_id', $document->id)->whereNotNull('posted_at')->whereNull('reversal_of')->whereDoesntHave('reversal', fn($q) => $q->whereNotNull('posted_at'))->exists()) {
            throw new ApiProblem('SOURCE_CORRECTION_PENDING', 'Resuelve la corrección de origen y contabiliza sus reversos antes de preparar un reemplazo.', 409);
        }
        $this->requireResolutionIntegrity($resolution);
        $after = $resolution->evidence['after_document'] ?? [];
        if (($after['source_revision'] ?? null) !== $document->source_revision || ($after['metadata']['source_hash'] ?? null) !== ($document->metadata['source_hash'] ?? null)) {
            throw new ApiProblem('SOURCE_CORRECTION_PENDING', 'La propuesta no corresponde a la revisión de origen que se resolvió.', 409);
        }
    }

    private function resolution(SourceResolution $row): array
    {
        $this->requireResolutionIntegrity($row);
        return [...$row->only(['id', 'incident_id', 'source_document_id', 'source_version_id', 'action', 'reason', 'base_revision', 'reviewed_by', 'reviewed_by_name', 'integrity_hash']),
            'company_id' => app(CompanyContext::class)->id(), 'integrity' => 'verified', 'created_at' => $row->created_at->setTimezone('America/Caracas')->toIso8601String(), 'evidence_json' => AuditPayload::text($row->getRawOriginal('evidence'))];
    }

    public function listing(array $filters): array
    {
        return DB::readSnapshot(function () use ($filters) {
            $q = Incident::where('kind', 'source_changed')->where('resource_type', 'source_document');
            if (($filters['status'] ?? 'open') !== 'all') { $q->where('status', $filters['status'] ?? 'open'); }
            if (!empty($filters['document_id'])) { $q->where('resource_id', $filters['document_id']); }
            $page = $q->orderByDesc('created_at')->orderByDesc('id')->paginate(25, ['*'], 'page', $filters['page'] ?? 1);
            $documents = SourceDocument::whereIn('id', $page->getCollection()->pluck('resource_id'))->get(['id', 'number', 'source_revision', 'status'])->keyBy('id');
            return ['data' => $page->getCollection()->map(fn($row) => [...$row->only(['id', 'kind', 'title', 'status', 'version', 'resource_id', 'details', 'resolution', 'resolved_by']), 'document' => $documents->get($row->resource_id)?->only(['id', 'number', 'source_revision', 'status']), 'created_at' => $row->created_at->setTimezone('America/Caracas')->toIso8601String()])->all(),
                'meta' => ['company_id' => app(CompanyContext::class)->id(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]];
        });
    }

    public function show(string $id): array
    {
        return DB::readSnapshot(function () use ($id) {
            [$incident, $document, $version] = $this->target($id); $ledger = $this->ledger($document);
            $latest = SourceVersion::where('source_document_id', $document->id)->orderByDesc('revision')->value('id');
            $current = $latest === $version->id; $integrity = app(SourceHistoryService::class)->integrity($version); $same = $this->basisUnchanged($document, $version, $ledger);
            $activeOpening = OpenItem::where('source_document_id', $document->id)->where('is_opening', true)->where('status', '!=', 'reversed')->exists();
            $clear = !array_filter($ledger, fn($entry) => $entry['active_effect']) && !$activeOpening;
            $projectionIssues = is_array($version->snapshot) ? app(SabSourceReader::class)->projectionIssues($version->snapshot) : ['El antecedente no conserva la estructura de un documento de origen.'];
            $open = $incident->status === 'open' && $current && $integrity['state'] !== 'failed' && !$projectionIssues;
            $resolution = SourceResolution::where('incident_id', $incident->id)->first();
            return ['company_id' => app(CompanyContext::class)->id(), 'incident' => $incident->only(['id', 'kind', 'title', 'status', 'version', 'details', 'resolution', 'resolved_by', 'resolved_at']),
                'document' => $document->only(['id', 'number', 'status', 'version', 'source_revision', 'origin']), 'observed' => [...app(SourceHistoryService::class)->metadata($version, $document), 'integrity' => $integrity],
                'observed_snapshot_json' => AuditPayload::text($version->getRawOriginal('snapshot')), 'registered_snapshot_json' => AuditPayload::text(json_encode($document->toArray(), JSON_THROW_ON_ERROR)),
                'ledger' => $ledger, 'latest_observed' => $current, 'business_basis_unchanged' => $same, 'active_opening' => $activeOpening, 'projection_issues' => $projectionIssues,
                'actions' => ['acknowledge_metadata' => $open && $same && ($ledger || $document->source_revision !== $version->revision), 'apply_after_reversal' => $open && $clear && $document->source_revision !== $version->revision, 'review_received' => $open && $clear && $document->source_revision === $version->revision && in_array($document->status, ['validated', 'proposed'], true)],
                'resolution' => $resolution ? $this->resolution($resolution) : null];
        });
    }

    public function resolve(string $id, int $version, array $input, int $user, ?string $key): array
    {
        return DB::transaction(function () use ($id, $version, $input, $user, $key) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first(); $actor = $this->actor($user);
            [$incident, $document, $observed] = $this->target($id, true);
            return app(OperationService::class)->execute($user, 'source.resolve', $key, ['incident_id' => $id, 'incident_version' => $version, ...$input], function () use ($incident, $document, $observed, $version, $input, $user, $actor) {
                if ($incident->version !== $version || $incident->status !== 'open' || $document->version !== (int) $input['document_version']) { throw new ApiProblem('VERSION_CONFLICT', 'La incidencia o el documento cambiaron. Conserva el motivo y revisa la versión actual.', 412); }
                $latest = SourceVersion::where('source_document_id', $document->id)->orderByDesc('revision')->value('id');
                if ($observed->id !== $input['source_version_id'] || $latest !== $observed->id) { throw new ApiProblem('SOURCE_REVIEW_SUPERSEDED', 'Se recibió otra revisión. Resuelve el cambio más reciente y conserva los antecedentes.', 409); }
                if (app(SourceHistoryService::class)->integrity($observed)['state'] === 'failed') { throw new ApiProblem('SOURCE_HISTORY_INTEGRITY', 'El antecedente observado no conserva su integridad.', 409); }
                $reason = trim($input['reason']); $action = $input['action'];
                if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000 || !in_array($action, ['acknowledge_metadata', 'apply_after_reversal', 'review_received'], true)) { throw new ApiProblem('SOURCE_RESOLUTION_INPUT', 'Selecciona una resolución y explica su motivo entre 10 y 2000 caracteres.'); }
                $snapshot = app(SabSourceReader::class)->snapshot($document->metadata['source_kind'] ?? '', (int) ($document->metadata['source_record_id'] ?? 0), true);
                if (!hash_equals($observed->hash, $snapshot['hash'])) { throw new ApiProblem('SOURCE_REVIEW_STALE', 'SAB volvió a cambiar. Recibe su versión actual antes de confirmar esta revisión.', 409); }
                $ledger = $this->ledger($document); $clear = !array_filter($ledger, fn($entry) => $entry['active_effect']) && !OpenItem::where('source_document_id', $document->id)->where('is_opening', true)->where('status', '!=', 'reversed')->exists();
                if ($action === 'acknowledge_metadata' && (!$this->basisUnchanged($document, $observed, $ledger) || (!$ledger && $document->source_revision === $observed->revision))) { throw new ApiProblem('SOURCE_FINANCIAL_CHANGE', 'El cambio requiere validar la revisión recibida o corregir sus efectos anteriores antes de confirmar otra revisión.', 409); }
                if ($action === 'apply_after_reversal' && !$clear) { throw new ApiProblem('SOURCE_EFFECTS_ACTIVE', 'Contabiliza los reversos aprobados y resuelve los auxiliares vinculados antes de aplicar la nueva revisión.', 409); }
                if ($action === 'review_received' && (!$clear || $document->source_revision !== $observed->revision || !in_array($document->status, ['validated', 'proposed'], true))) { throw new ApiProblem('SOURCE_REVIEW_INCOMPLETE', 'Valida la revisión recibida y resuelve los efectos anteriores antes de cerrar esta incidencia.', 409); }
                $before = $document->toArray(); $fiscal = FiscalRecord::where('source_document_id', $document->id)->whereIn('status', ['validated', 'source_changed'])->get()->toArray();
                if ($action === 'apply_after_reversal') { $document = app(SabSourceReader::class)->applyObserved($document, $observed, $user); }
                $now = now('America/Caracas')->toDateTimeString(); $context = app(CompanyContext::class);
                $record = new SourceResolution(['id' => (string) Str::uuid(), 'incident_id' => $incident->id, 'source_document_id' => $document->id, 'source_version_id' => $observed->id, 'action' => $action, 'reason' => $reason,
                    'base_revision' => $before['source_revision'], 'reviewed_by' => $user, 'reviewed_by_name' => $actor->name, 'evidence' => ['before_document' => $before, 'after_document' => $document->toArray(), 'prior_fiscal_records' => $fiscal, 'ledger' => $ledger, 'observed_hash' => $observed->hash, 'observed_integrity' => app(SourceHistoryService::class)->integrity($observed), 'no_automatic_posting' => true], 'created_at' => $now, 'updated_at' => $now]);
                $record->forceFill(['organization_id' => $context->organizationId(), 'company_id' => $context->id()]); $record->integrity_hash = SourceResolutionHash::digest($record->getAttributes()); $record->save();
                $incident->fill(['status' => 'resolved', 'version' => $incident->version + 1, 'resolution' => $reason, 'resolved_by' => $user, 'resolved_at' => $now]); $incident->save();
                foreach (Incident::where('kind', 'source_changed')->where('resource_type', 'source_document')->where('resource_id', $document->id)->where('status', 'open')->where('id', '!=', $incident->id)->lockForUpdate()->get() as $older) {
                    $older->fill(['status' => 'superseded', 'version' => $older->version + 1, 'resolution' => 'La revisión posterior conserva la resolución ' . $record->id, 'resolved_by' => $user, 'resolved_at' => $now]); $older->save();
                    app(AuditService::class)->record($user, 'pro9.incident_superseded', 'incident', $older->id, ['resolution_id' => $record->id, 'latest_incident_id' => $incident->id]);
                }
                app(AuditService::class)->record($user, 'pro9.source_resolved', 'source_document', $document->id, ['incident_id' => $incident->id, 'resolution_id' => $record->id, 'action' => $action, 'reason' => $reason, 'source_version_id' => $observed->id, 'before_revision' => $before['source_revision'], 'after_revision' => $document->source_revision, 'before_version' => $before['version'], 'version' => $document->version, 'integrity_hash' => $record->integrity_hash]);
                return ['incident_id' => $incident->id, 'resolution' => $this->resolution($record), 'document' => $document->only(['id', 'version', 'source_revision', 'status'])];
            });
        }, 5);
    }
}
