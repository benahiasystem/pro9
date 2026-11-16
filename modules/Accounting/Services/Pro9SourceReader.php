<?php

namespace Modules\Accounting\Services;

use App\Support\Exact\Decimal;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Support\TenantDB as DB;
use Modules\Accounting\Models\{SourceDocument, SourceVersion, ThirdParty, Incident, JournalEntry};
final class Pro9SourceReader
{
    private const TABLES = ['sales' => 'documents', 'purchases' => 'purchases'];
    private function company(): ?object
    {
        $book = app(CompanyContext::class)->company();
        $id = $book->settings['source_company_id'] ?? null;
        if (!$id) {
            return null;
        }
        $source = DB::table('companies')->where('id', $id)->first(['id', 'name', 'number']);
        $normalize = fn($s) => preg_replace('/[^A-Z0-9]/', '', strtoupper($s));
        return $source && $normalize($source->number) === $normalize($book->tax_id) ? $source : null;
    }
    private function requireLink(): object
    {
        return $this->company() ?? throw new ApiProblem('PRO9_BOOK_NOT_LINKED', 'Este libro no está vinculado a la empresa de SAB del dominio actual.', 409);
    }
    public function status(): array
    {
        $source = $this->company();
        $pending = SourceDocument::where('origin', 'pro9')->where(fn($q) => $q->whereIn('status', ['received', 'changed'])->orWhereIn('id', Incident::where('kind', 'source_changed')->where('resource_type', 'source_document')->where('status', 'open')->select('resource_id')))->count();
        return ['linked' => (bool) $source, 'source' => $source ? ['name' => $source->name, 'tax_id' => $source->number] : null, 'available' => $source ? ['sales' => DB::table('documents')->count(), 'purchases' => DB::table('purchases')->whereNull('deleted_at')->count()] : null, 'received' => SourceDocument::where('origin', 'pro9')->count(), 'pending' => $pending];
    }
    private function query(string $kind): \Illuminate\Database\Query\Builder
    {
        $table = self::TABLES[$kind] ?? throw new ApiProblem('SOURCE_KIND_NOT_SUPPORTED', 'El tipo de documento de SAB no está disponible.', 404);
        $query = DB::table($table);
        if ($kind === 'purchases') {
            $query->whereNull('deleted_at');
        }
        return $query;
    }
    public function listing(string $kind, array $filters): array
    {
        $this->requireLink();
        $query = $this->query($kind);
        if (!empty($filters['from'])) {
            $query->where('date_of_issue', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('date_of_issue', '<=', $filters['to']);
        }
        $page = $query->orderByDesc('date_of_issue')->orderByDesc('id')->paginate(25, ['*'], 'page', $filters['page'] ?? 1);
        $page->setCollection($page->getCollection()->map(fn($row) => $this->normalize($kind, $row)));
        return $page->toArray();
    }
    public function snapshot(string $kind, int $id, bool $lock = false): array
    {
        $this->requireLink();
        $query = $this->query($kind)->where('id', $id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();
        if (!$row) {
            throw new ApiProblem('SOURCE_NOT_FOUND', 'El documento no está disponible en SAB.', 404);
        }
        return $this->normalize($kind, $row);
    }
    public function assertCurrent(SourceDocument $document): void
    {
        if ($document->origin !== 'pro9') {
            return;
        }
        app(SourceHistoryService::class)->requireCurrent($document);
        $kind = $document->metadata['source_kind'] ?? '';
        $id = $document->metadata['source_record_id'] ?? 0;
        $snapshot = $this->snapshot($kind, (int) $id, true);
        if (!hash_equals(app(SourceIncidentService::class)->effectiveHash($document), $snapshot['hash'])) {
            throw new ApiProblem('PRO9_SOURCE_CHANGED', 'El documento cambió en SAB. Recibe su versión actual y resuelve las observaciones antes de continuar.', 409);
        }
        if (in_array($snapshot['state'], \App\Models\Tenant\ModelTenant::VOIDED_REJECTED_IDS, true)) {
            throw new ApiProblem('PRO9_SOURCE_NOT_ACTIVE', 'El documento está rechazado o anulado en SAB. Conserva su historial y revisa la corrección correspondiente.');
        }
        if (!in_array($snapshot['state'], \App\Models\Tenant\ModelTenant::STATE_TYPES_ACCEPTED, true)) {
            throw new ApiProblem('PRO9_SOURCE_STATE_UNSUPPORTED', 'El estado del documento de SAB requiere revisión antes de preparar el efecto contable.');
        }
        if ($kind === 'sales') {
            $row = DB::table('documents')->where('id', $id)->lockForUpdate()->first();
            if (($row->fiscal_emission_mode ?? '') === 'digital') {
                $emission = DB::table('document_emissions')->where('document_id', $id)->lockForUpdate()->first();
                if (!$emission || $emission->status !== 'confirmed') {
                    throw new ApiProblem('PRO9_SOURCE_NOT_FINAL', 'La emisión digital en SAB todavía no está confirmada. Conserva el documento recibido pendiente de validación.');
                }
            }
        }
    }
    private function normalize(string $kind, object $row): array
    {
        $party = json_decode(($kind === 'sales' ? $row->customer : $row->supplier) ?? '{}', true, 512, JSON_THROW_ON_ERROR);
        $fields = ['total', 'total_value', 'total_igv', 'total_taxes', 'exchange_rate_sale'];
        $amounts = [];
        $exact = [];
        foreach ($fields as $field) {
            $value = Decimal::readRequired($row, $field);
            $amounts[$field] = Decimal::represent($value, 12, true);
            $exact[$field] = Decimal::fraction($value);
        }
        $type = (string) $row->document_type_id;
        $prefix = $kind === 'sales' ? 'sale' : 'purchase';
        $snapshot = ['source_id' => self::TABLES[$kind] . ':' . $row->id, 'id' => $row->id, 'source_kind' => $kind, 'kind' => $prefix . match ($type) {
            '01' => '_invoice',
            '07' => '_credit_note',
            '08' => '_debit_note',
            default => '_unsupported',
        }, 'number' => trim(($row->series ?? '') . '-' . $row->number, '-'), 'document_type' => $type, 'control_number' => $row->control_number ?? null, 'issue_date' => (string) $row->date_of_issue, 'state' => (string) $row->state_type_id, 'currency' => (string) $row->currency_type_id, 'party' => ['name' => $party['name'] ?? '', 'tax_id' => $party['number'] ?? ''], 'amounts' => $amounts, 'exact' => $exact, 'exchange_source' => $row->exchange_rate_source ?? null, 'exchange_date' => $row->exchange_rate_date ?? null, 'source_updated_at' => $row->updated_at ?? null];
        $snapshot['hash'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $snapshot;
    }
    public function receive(string $kind, int $id, int $user): array
    {
        return DB::transaction(function () use ($kind, $id, $user) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            $source = $this->requireLink();
            $actor = \App\Models\Tenant\User::without('establishment')->whereKey($user)->where('active', true)->where('locked', false)->first();
            if (!$actor) { throw new ApiProblem('FORBIDDEN_ACTION', 'La cuenta no puede recibir documentos de SAB.', 403); }
            app(\Modules\Accounting\Domain\Permissions::class)->require($actor, 'integration');
            $table = self::TABLES[$kind] ?? throw new ApiProblem('SOURCE_KIND_NOT_SUPPORTED', 'El tipo de documento de SAB no está disponible.', 404);
            $doc = SourceDocument::where('source_instance_id', 'pro9')->where('source_company_id', (string) $source->id)->where('source_id', $table . ':' . $id)->lockForUpdate()->first();
            try { $snapshot = $this->snapshot($kind, $id, true); }
            catch (ApiProblem $problem) {
                if (!$doc || $problem->errorCode !== 'SOURCE_NOT_FOUND') { throw $problem; }
                // Absence is an observation, never an inferred fiscal annulment.
                $snapshot = ['availability' => 'missing', 'source_id' => $table . ':' . $id, 'source_kind' => $kind, 'id' => $id];
                $snapshot['hash'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            }
            $issues = $this->projectionIssues($snapshot);
            $latest = $doc ? SourceVersion::where('source_document_id', $doc->id)->orderByDesc('revision')->first() : null;
            if ($doc && ($latest?->hash ?? $doc->metadata['source_hash'] ?? null) === $snapshot['hash']) {
                $incident = Incident::where('resource_type', 'source_document')->where('resource_id', $doc->id)->where('kind', 'source_changed')->where('status', 'open')->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(details, '$.revision')) AS UNSIGNED) DESC")->orderByDesc('created_at')->orderByDesc('id')->first();
                return ['data' => $doc, 'unchanged' => true, 'requires_reconciliation' => (bool) $incident, 'observed_revision' => $latest?->revision ?? $doc->source_revision, 'incident_id' => $incident?->id];
            }
            $revision = $doc ? max((int) ($latest?->revision ?? 0), (int) $doc->source_revision) + 1 : 1;
            if ($doc && ($issues || JournalEntry::where('source_document_id', $doc->id)->whereNotNull('posted_at')->exists() || \Modules\Accounting\Models\OpenItem::where('source_document_id', $doc->id)->where('status', '!=', 'reversed')->where('is_opening', true)->exists())) {
                $version = app(SourceHistoryService::class)->append($doc, $snapshot, $snapshot['hash'], $user, $revision, 'pending_reconciliation');
                $this->invalidatePending($doc, $user, $version->id);
                $incident = Incident::create(['kind' => 'source_changed', 'resource_type' => 'source_document', 'resource_id' => $doc->id,
                    'title' => 'El origen de SAB requiere revisión', 'details' => ['revision' => $revision, 'source_version_id' => $version->id, 'applied_revision' => $doc->source_revision, 'projection_issues' => $issues,
                        'observed_hash' => $snapshot['hash'], 'applied_hash' => $doc->metadata['source_hash'] ?? null, 'has_posted_entry' => JournalEntry::where('source_document_id', $doc->id)->whereNotNull('posted_at')->exists(),
                        'has_opening_item' => \Modules\Accounting\Models\OpenItem::where('source_document_id', $doc->id)->where('is_opening', true)->where('status', '!=', 'reversed')->exists(), 'financial_projection_preserved' => true]]);
                app(AuditService::class)->record($user, 'pro9.source_change_observed', 'source_document', $doc->id, ['source_version_id' => $version->id, 'revision' => $revision, 'applied_revision' => $doc->source_revision, 'hash' => $snapshot['hash'], 'incident_id' => $incident->id, 'financial_projection_preserved' => true]);
                return ['data' => $doc, 'unchanged' => false, 'requires_reconciliation' => true, 'observed_revision' => $revision, 'incident_id' => $incident->id];
            }
            if ($issues) { throw new ApiProblem('SOURCE_PROJECTION_UNSUPPORTED', implode(' ', $issues)); }
            $tax = (string) $snapshot['party']['tax_id'];
            $naturalKey = app(DocumentService::class)->identity($snapshot['kind'], $snapshot['number'], $tax, $snapshot['control_number'], $snapshot['issue_date']);
            app(DocumentService::class)->ensureIdentity($naturalKey, $doc?->id);
            $party = ThirdParty::firstOrCreate(['tax_id' => $tax], ['name' => $snapshot['party']['name'], 'kind' => $kind === 'sales' ? 'customer' : 'supplier']);
            $changed = (bool) $doc;
            $values = ['origin' => 'pro9', 'source_instance_id' => 'pro9', 'source_company_id' => (string) $source->id, 'source_id' => $snapshot['source_id'], 'kind' => $snapshot['kind'], 'number' => $snapshot['number'], 'fiscal_control' => $snapshot['control_number'], 'issue_date' => $snapshot['issue_date'], 'fiscal_date' => $snapshot['issue_date'], 'third_party_id' => $party->id, 'currency' => $snapshot['currency'], 'exchange_rate' => $snapshot['currency'] === 'VES' ? '1' : $snapshot['amounts']['exchange_rate_sale'], 'exchange_source' => $snapshot['exchange_source'] ?? 'SAB · pendiente de verificar', 'exchange_date' => $snapshot['exchange_date'] ?? $snapshot['issue_date'], 'amounts' => ['total' => $snapshot['amounts']['total'], 'base' => $snapshot['amounts']['total_value'], 'vat' => $snapshot['amounts']['total_igv'], 'taxes' => $snapshot['amounts']['total_taxes']], 'fiscal_amounts' => [], 'status' => $changed ? 'changed' : 'received', 'source_revision' => $revision, 'metadata' => ['source_hash' => $snapshot['hash'], 'source_kind' => $kind, 'source_record_id' => $id, 'source_state' => $snapshot['state'], 'exact' => $snapshot['exact']], 'issues' => ['Requiere validación contable y fiscal antes de preparar el asiento.']];
            if ($doc) {
                $values['validated_by'] = null;
                $values['validated_at'] = null;
                $values['validated_version'] = null;
                $doc->fill($values);
                $doc->version++;
                $doc->save();
            } else {
                $values['created_by'] = $user;
                $doc = SourceDocument::create($values);
            }
            $doc->natural_key = $naturalKey;
            $doc->save();
            $sourceVersion = app(SourceHistoryService::class)->append($doc, $snapshot, $snapshot['hash'], $user, $revision);
            $incident = null;
            if ($changed) {
                \Modules\Accounting\Models\FiscalRecord::where('source_document_id', $doc->id)->where('status', 'validated')->update(['status' => 'source_changed']);
                $this->invalidatePending($doc, $user);
                $incident = Incident::create(['kind' => 'source_changed', 'resource_type' => 'source_document', 'resource_id' => $doc->id, 'title' => 'El documento de SAB cambió', 'details' => ['revision' => $revision, 'source_version_id' => $sourceVersion->id, 'has_posted_entry' => JournalEntry::where('source_document_id', $doc->id)->whereNotNull('posted_at')->exists(), 'has_opening_item' => \Modules\Accounting\Models\OpenItem::where('source_document_id', $doc->id)->where('is_opening', true)->where('status', '!=', 'reversed')->exists()]]);
            }
            app(AuditService::class)->record($user, $changed ? 'pro9.source_changed' : 'pro9.received', 'source_document', $doc->id, ['source_id' => $snapshot['source_id'], 'revision' => $revision, 'hash' => $snapshot['hash']]);
            return ['data' => $doc, 'unchanged' => false, 'requires_reconciliation' => (bool) $incident, 'observed_revision' => $revision, 'incident_id' => $incident?->id];
        }, 5);
    }

    public function projectionIssues(array $snapshot): array
    {
        if (($snapshot['availability'] ?? '') === 'missing') { return ['El documento ya recibido no está disponible en SAB. Revisa su recuperación; no se infiere una anulación.']; }
        $issues = [];
        if (!in_array($snapshot['kind'] ?? '', ['sale_invoice', 'sale_credit_note', 'sale_debit_note', 'purchase_invoice', 'purchase_credit_note', 'purchase_debit_note'], true)) { $issues[] = 'El tipo de documento necesita una equivalencia admitida.'; }
        if (!in_array($snapshot['currency'] ?? '', ['VES', 'USD', 'EUR'], true)) { $issues[] = 'La moneda del origen necesita revisión.'; }
        $party = is_array($snapshot['party'] ?? null) ? $snapshot['party'] : [];
        $tax = $party['tax_id'] ?? null;
        if (!is_string($tax) || $tax === '' || mb_strlen($tax) > 30) { $issues[] = 'Completa la identificación del tercero en SAB.'; }
        return $issues;
    }

    private function invalidatePending(SourceDocument $document, int $user, ?string $sourceVersion = null): void
    {
        foreach (JournalEntry::where('source_document_id', $document->id)->whereNull('posted_at')->where('status', '!=', 'withdrawn')->orderBy('id')->lockForUpdate()->get() as $entry) {
            $before = $entry->version;
            $entry->fill(['status' => 'observed', 'approved_by' => null, 'approved_at' => null, 'approved_version' => null, 'approval_reason' => null, 'version' => $before + 1]);
            $entry->save();
            app(AuditService::class)->record($user, 'entry.source_changed', 'journal_entry', $entry->id, ['before_version' => $before, 'version' => $entry->version, 'source_document_id' => $document->id, 'source_version_id' => $sourceVersion, 'approval_invalidated' => true]);
        }
    }

    /** Apply a received revision only after its former effects were reversed.
     * This does not validate, approve or post the replacement document.
     */
    public function applyObserved(SourceDocument $document, SourceVersion $observed, int $user): SourceDocument
    {
        $actor = \App\Models\Tenant\User::without('establishment')->whereKey($user)->where('active', true)->where('locked', false)->first();
        if (!$actor) { throw new ApiProblem('FORBIDDEN_ACTION', 'La cuenta no puede aplicar una revisión de SAB.', 403); }
        foreach (['integration', 'review'] as $permission) { app(\Modules\Accounting\Domain\Permissions::class)->require($actor, $permission); }
        $source = $this->requireLink();
        if ($document->origin !== 'pro9' || $observed->source_document_id !== $document->id || (string) $source->id !== (string) $document->source_company_id) { throw new ApiProblem('SOURCE_CONTEXT_CHANGED', 'La versión no corresponde al vínculo y documento actuales.', 409); }
        if (app(SourceHistoryService::class)->integrity($observed)['state'] === 'failed') { throw new ApiProblem('SOURCE_HISTORY_INTEGRITY', 'El antecedente observado no conserva su integridad.', 409); }
        $kind = $document->metadata['source_kind'] ?? ''; $id = (int) ($document->metadata['source_record_id'] ?? 0); $snapshot = $this->snapshot($kind, $id, true);
        if (!hash_equals($observed->hash, $snapshot['hash'])) { throw new ApiProblem('SOURCE_REVIEW_STALE', 'SAB cambió desde la revisión recibida. Recibe su versión actual.', 409); }
        if (JournalEntry::where('source_document_id', $document->id)->whereNotNull('posted_at')->whereNull('reversal_of')->whereDoesntHave('reversal', fn($q) => $q->whereNotNull('posted_at'))->exists() || \Modules\Accounting\Models\OpenItem::where('source_document_id', $document->id)->where('is_opening', true)->where('status', '!=', 'reversed')->exists()) { throw new ApiProblem('SOURCE_EFFECTS_ACTIVE', 'Los efectos anteriores requieren reversos aprobados y contabilizados antes de aplicar otra revisión.', 409); }
        if (str_ends_with($snapshot['kind'], '_unsupported') || !in_array($snapshot['currency'], ['VES', 'USD', 'EUR'], true)) { throw new ApiProblem('SOURCE_KIND_NOT_SUPPORTED', 'Corrige la equivalencia y la moneda del origen antes de aplicar esta revisión.', 409); }
        $tax = (string) $snapshot['party']['tax_id'];
        if ($tax === '' || mb_strlen($tax) > 30) { throw new ApiProblem('PARTY_IDENTIFICATION_REQUIRED', 'Revisa la identificación del tercero en SAB.'); }
        $key = app(DocumentService::class)->identity($snapshot['kind'], $snapshot['number'], $tax, $snapshot['control_number'], $snapshot['issue_date']);
        app(DocumentService::class)->ensureIdentity($key, $document->id);
        $party = ThirdParty::firstOrCreate(['tax_id' => $tax], ['name' => $snapshot['party']['name'], 'kind' => $kind === 'sales' ? 'customer' : 'supplier']);
        $void = in_array($snapshot['state'], \App\Models\Tenant\ModelTenant::VOIDED_REJECTED_IDS, true);
        $document->fill(['kind' => $snapshot['kind'], 'number' => $snapshot['number'], 'natural_key' => $key, 'fiscal_control' => $snapshot['control_number'], 'issue_date' => $snapshot['issue_date'], 'fiscal_date' => $snapshot['issue_date'], 'received_on' => null, 'due_date' => null, 'related_document_id' => null,
            'third_party_id' => $party->id, 'currency' => $snapshot['currency'], 'exchange_rate' => $snapshot['currency'] === 'VES' ? '1' : $snapshot['amounts']['exchange_rate_sale'], 'exchange_source' => $snapshot['exchange_source'] ?? 'SAB · pendiente de verificar', 'exchange_date' => $snapshot['exchange_date'] ?? $snapshot['issue_date'], 'rate_evidence_reference' => null,
            'amounts' => ['total' => $snapshot['amounts']['total'], 'base' => $snapshot['amounts']['total_value'], 'vat' => $snapshot['amounts']['total_igv'], 'taxes' => $snapshot['amounts']['total_taxes']], 'fiscal_amounts' => [], 'tax_components' => [], 'source_revision' => $observed->revision, 'version' => $document->version + 1,
            'status' => $void ? 'excluded' : 'received', 'validated_by' => null, 'validated_at' => null, 'validated_version' => null,
            'metadata' => ['source_hash' => $snapshot['hash'], 'source_kind' => $kind, 'source_record_id' => $id, 'source_state' => $snapshot['state'], 'exact' => $snapshot['exact']],
            'issues' => [$void ? 'Origen anulado o rechazado. La corrección y sus reversos se conservan en el historial.' : 'Revisión recibida después de corregir los efectos anteriores. Requiere nueva validación contable y fiscal.']]);
        $document->save();
        \Modules\Accounting\Models\FiscalRecord::where('source_document_id', $document->id)->whereIn('status', ['validated', 'source_changed'])->update(['status' => 'superseded']);
        $this->invalidatePending($document, $user, $observed->id);
        return $document;
    }
}
