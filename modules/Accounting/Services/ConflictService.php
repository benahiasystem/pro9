<?php

namespace Modules\Accounting\Services;

use App\Models\Tenant\User;
use Modules\Accounting\Domain\{ApiProblem, Permissions, CompanyContext};
use Modules\Accounting\Models\{JournalEntry, SourceDocument};
use Modules\Accounting\Support\{EntryInput, DocumentInput, TenantDB as DB};

final class ConflictService
{
    public function __construct(private OperationService $operations, private EntryService $entries, private DocumentService $documents, private AuditService $audit)
    {
    }

    private function input(array $input, int $version): array
    {
        $reason = trim((string) ($input['reason'] ?? ''));
        if (!in_array($input['decision'] ?? null, ['apply_local', 'keep_server'], true) || mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw new ApiProblem('RESOLUTION_REQUIRED', 'Elige una versión y explica el motivo, entre 10 y 2000 caracteres.');
        }
        $base = $input['base_version'] ?? null;
        if ($base !== null && (!is_int($base) || $base < 1 || $base > $version)) {
            throw new ApiProblem('INVALID_BASE_VERSION', 'La versión de partida no puede ser posterior a la versión que revisaste.');
        }
        if (!is_array($input['local'] ?? null) || strlen(json_encode($input['local'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) > (isset($input['local']['local_selection']) ? 3 * 1024 * 1024 : 512 * 1024)) {
            throw new ApiProblem('LOCAL_EVIDENCE_REQUIRED', 'Conserva la alternativa local dentro del tamaño admitido antes de resolver.');
        }
        return ['base_version' => $base, 'decision' => $input['decision'], 'reason' => $reason, 'local' => $input['local']];
    }

    public function entry(int $user, string $id, int $version, array $input, ?string $key): array
    {
        $input = $this->input($input, $version);
        $selection = app(LocalSelectionService::class)->validate($input['local']['local_selection'] ?? null, 'entry', $input['local']['local_id'] ?? null);
        if ($selection !== null) {
            $input['local']['local_selection'] = $selection;
        }
        if ($input['decision'] === 'apply_local') {
            app(Permissions::class)->require(User::without('establishment')->findOrFail($user), 'draft');
        }
        return $this->operations->execute($user, 'entry.resolve', $key, ['id' => $id, 'version' => $version, ...$input], function () use ($user, $id, $version, $input, $key) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            $entry = JournalEntry::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->entries->version($entry, $version);
            $before = $this->entries->resource($entry);
            if ($input['decision'] === 'apply_local') {
                if ($entry->metadata['financial_effect'] ?? null) {
                    throw new ApiProblem('SPECIALIZED_ENTRY_REQUIRED', 'Esta propuesta se revisa desde su apertura o liquidación, conservando el auxiliar.');
                }
                $entry = $this->entries->edit($id, EntryInput::fromArray($input['local']), $version, $user);
            }
            $after = $this->entries->resource($entry->fresh());
            return $this->record($user, 'entry', $id, $input, $before, $after, $key);
        });
    }

    public function document(int $user, string $id, int $version, array $input, ?string $key): array
    {
        $input = $this->input($input, $version);
        $selection = app(LocalSelectionService::class)->validate($input['local']['local_selection'] ?? null, 'document');
        if ($selection !== null) {
            $input['local']['local_selection'] = $selection;
        }
        if ($input['decision'] === 'apply_local') {
            app(Permissions::class)->require(User::without('establishment')->findOrFail($user), 'documents');
        }
        return $this->operations->execute($user, 'document.resolve', $key, ['id' => $id, 'version' => $version, ...$input], function () use ($user, $id, $version, $input, $key) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            $document = SourceDocument::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->documents->version($document, $version);
            $before = $this->documents->resource($document);
            if ($input['decision'] === 'apply_local') {
                $document = $this->documents->edit($id, DocumentInput::fromArray($input['local']), $version, $user);
            }
            $after = $this->documents->resource($document->fresh());
            return $this->record($user, 'document', $id, $input, $before, $after, $key);
        });
    }

    private function record(int $user, string $kind, string $id, array $input, array $before, array $after, ?string $key): array
    {
        $localReceipts = app(LocalSelectionService::class)->record($user, $input['local']['local_selection'] ?? null, $kind, $id);
        $event = $this->audit->record($user, $kind . '.conflict_resolved', $kind === 'entry' ? 'journal_entry' : 'source_document', $id, [
            'decision' => $input['decision'], 'reason' => $input['reason'], 'reported_base_version' => $input['base_version'],
            'local_reported' => $input['local'], 'server_before' => $before, 'server_after' => $after, 'operation_key' => $key,
        ]);
        return ['entity' => $after, 'resolution' => ['id' => $event['id'], 'decision' => $input['decision'], 'reason' => $input['reason'], 'base_version' => $input['base_version'], 'reviewed_version' => $before['version'], 'resulting_version' => $after['version'], ...($localReceipts ? ['local_selection_receipts' => $localReceipts] : [])]];
    }

    public function currentEntry(string $id): array
    {
        return DB::transaction(fn() => $this->entries->resource(JournalEntry::whereKey($id)->lockForUpdate()->firstOrFail()), 5);
    }

    public function currentDocument(string $id): array
    {
        return DB::transaction(fn() => $this->documents->resource(SourceDocument::whereKey($id)->lockForUpdate()->firstOrFail()), 5);
    }
}
