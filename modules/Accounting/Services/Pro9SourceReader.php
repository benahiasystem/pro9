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
        return $this->company() ?? throw new ApiProblem('PRO9_BOOK_NOT_LINKED', 'Este libro no está vinculado a la empresa de PRO9 del dominio actual.', 409);
    }
    public function status(): array
    {
        $source = $this->company();
        return ['linked' => (bool) $source, 'source' => $source ? ['name' => $source->name, 'tax_id' => $source->number] : null, 'available' => $source ? ['sales' => DB::table('documents')->count(), 'purchases' => DB::table('purchases')->whereNull('deleted_at')->count()] : null, 'received' => SourceDocument::where('origin', 'pro9')->count(), 'pending' => SourceDocument::where('origin', 'pro9')->whereIn('status', ['received', 'changed'])->count()];
    }
    private function query(string $kind): \Illuminate\Database\Query\Builder
    {
        $table = self::TABLES[$kind] ?? throw new ApiProblem('SOURCE_KIND_NOT_SUPPORTED', 'El tipo de documento de PRO9 no está disponible.', 404);
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
            throw new ApiProblem('SOURCE_NOT_FOUND', 'El documento no está disponible en PRO9.', 404);
        }
        return $this->normalize($kind, $row);
    }
    public function assertCurrent(SourceDocument $document): void
    {
        if ($document->origin !== 'pro9') {
            return;
        }
        $kind = $document->metadata['source_kind'] ?? '';
        $id = $document->metadata['source_record_id'] ?? 0;
        $snapshot = $this->snapshot($kind, (int) $id, true);
        if (!hash_equals($document->metadata['source_hash'] ?? '', $snapshot['hash'])) {
            throw new ApiProblem('PRO9_SOURCE_CHANGED', 'El documento cambió en PRO9. Recibe su versión actual y resuelve las observaciones antes de continuar.', 409);
        }
        if (in_array($snapshot['state'], \App\Models\Tenant\ModelTenant::VOIDED_REJECTED_IDS, true)) {
            throw new ApiProblem('PRO9_SOURCE_NOT_ACTIVE', 'El documento está rechazado o anulado en PRO9. Conserva su historial y revisa la corrección correspondiente.');
        }
        if (!in_array($snapshot['state'], \App\Models\Tenant\ModelTenant::STATE_TYPES_ACCEPTED, true)) {
            throw new ApiProblem('PRO9_SOURCE_STATE_UNSUPPORTED', 'El estado del documento de PRO9 requiere revisión antes de preparar el efecto contable.');
        }
        if ($kind === 'sales') {
            $row = DB::table('documents')->where('id', $id)->lockForUpdate()->first();
            if (($row->fiscal_emission_mode ?? '') === 'digital') {
                $emission = DB::table('document_emissions')->where('document_id', $id)->lockForUpdate()->first();
                if (!$emission || $emission->status !== 'confirmed') {
                    throw new ApiProblem('PRO9_SOURCE_NOT_FINAL', 'La emisión digital en PRO9 todavía no está confirmada. Conserva el documento recibido pendiente de validación.');
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
            $snapshot = $this->snapshot($kind, $id);
            if (str_ends_with($snapshot['kind'], '_unsupported')) {
                throw new ApiProblem('SOURCE_KIND_NOT_SUPPORTED', 'Este tipo de documento requiere una equivalencia antes de recibirse.');
            }
            if (!in_array($snapshot['currency'], ['VES', 'USD', 'EUR'], true)) {
                throw new ApiProblem('UNSUPPORTED_CURRENCY', 'Revisa la moneda del documento de origen.');
            }
            $tax = (string) $snapshot['party']['tax_id'];
            if ($tax === '' || mb_strlen($tax) > 30) {
                throw new ApiProblem('PARTY_IDENTIFICATION_REQUIRED', 'Completa la identificación del tercero en PRO9 antes de recibir este documento.');
            }
            $doc = SourceDocument::where('source_instance_id', 'pro9')->where('source_company_id', (string) $source->id)->where('source_id', $snapshot['source_id'])->lockForUpdate()->first();
            if ($doc && ($doc->metadata['source_hash'] ?? null) === $snapshot['hash']) {
                return ['data' => $doc, 'unchanged' => true];
            }
            $naturalKey = app(DocumentService::class)->identity($snapshot['kind'], $snapshot['number'], $tax, $snapshot['control_number'], $snapshot['issue_date']);
            app(DocumentService::class)->ensureIdentity($naturalKey, $doc?->id);
            $party = ThirdParty::firstOrCreate(['tax_id' => $tax], ['name' => $snapshot['party']['name'], 'kind' => $kind === 'sales' ? 'customer' : 'supplier']);
            $changed = (bool) $doc;
            $revision = $doc ? $doc->source_revision + 1 : 1;
            $values = ['origin' => 'pro9', 'source_instance_id' => 'pro9', 'source_company_id' => (string) $source->id, 'source_id' => $snapshot['source_id'], 'kind' => $snapshot['kind'], 'number' => $snapshot['number'], 'fiscal_control' => $snapshot['control_number'], 'issue_date' => $snapshot['issue_date'], 'fiscal_date' => $snapshot['issue_date'], 'third_party_id' => $party->id, 'currency' => $snapshot['currency'], 'exchange_rate' => $snapshot['currency'] === 'VES' ? '1' : $snapshot['amounts']['exchange_rate_sale'], 'exchange_source' => $snapshot['exchange_source'] ?? 'PRO9 · pendiente de verificar', 'exchange_date' => $snapshot['exchange_date'] ?? $snapshot['issue_date'], 'amounts' => ['total' => $snapshot['amounts']['total'], 'base' => $snapshot['amounts']['total_value'], 'vat' => $snapshot['amounts']['total_igv'], 'taxes' => $snapshot['amounts']['total_taxes']], 'fiscal_amounts' => [], 'status' => $changed ? 'changed' : 'received', 'source_revision' => $revision, 'metadata' => ['source_hash' => $snapshot['hash'], 'source_kind' => $kind, 'source_record_id' => $id, 'source_state' => $snapshot['state'], 'exact' => $snapshot['exact']], 'issues' => ['Requiere validación contable y fiscal antes de preparar el asiento.']];
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
            SourceVersion::create(['source_document_id' => $doc->id, 'revision' => $revision, 'snapshot' => $snapshot, 'hash' => $snapshot['hash']]);
            if ($changed) {
                \Modules\Accounting\Models\FiscalRecord::where('source_document_id', $doc->id)->where('status', 'validated')->update(['status' => 'source_changed']);
                foreach (JournalEntry::where('source_document_id', $doc->id)->whereNull('posted_at')->lockForUpdate()->get() as $entry) {
                    $entry->fill(['status' => 'observed', 'approved_by' => null, 'approved_at' => null, 'approved_version' => null, 'approval_reason' => null, 'version' => $entry->version + 1]);
                    $entry->save();
                }
                Incident::create(['kind' => 'source_changed', 'resource_type' => 'source_document', 'resource_id' => $doc->id, 'title' => 'El documento de PRO9 cambió', 'details' => ['revision' => $revision, 'has_posted_entry' => JournalEntry::where('source_document_id', $doc->id)->whereNotNull('posted_at')->exists()]]);
            }
            app(AuditService::class)->record($user, $changed ? 'pro9.source_changed' : 'pro9.received', 'source_document', $doc->id, ['source_id' => $snapshot['source_id'], 'revision' => $revision, 'hash' => $snapshot['hash']]);
            return ['data' => $doc, 'unchanged' => false];
        }, 5);
    }
}
