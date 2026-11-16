<?php

namespace Modules\Accounting\Services;

use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Permissions};
use Modules\Accounting\Models\{Attachment, JournalEntry, SourceDocument, OpenItem, FiscalRecord};
use Modules\Accounting\Support\{TenantDB as DB, PrivateStorage, AttachmentFormat};

final class AttachmentService
{
    private function actor(int $id, string $permission = 'read'): User
    {
        $user = User::without('establishment')->whereKey($id)->where('active', true)->where('locked', false)->first();
        if (!$user) { throw new ApiProblem('FORBIDDEN_ACTION', 'La cuenta de SAB no puede consultar estos soportes.', 403); }
        app(Permissions::class)->require($user, $permission);
        return $user;
    }

    public function target(string $type, string $id, bool $lock = false): mixed
    {
        $class = ['journal_entry' => JournalEntry::class, 'source_document' => SourceDocument::class][$type] ?? null;
        if (!$class) { throw new ApiProblem('EVIDENCE_RESOURCE', 'Este tipo de registro no admite soportes en esta ruta.'); }
        return $class::whereKey($id)->when($lock, fn($q) => $q->lockForUpdate())->firstOrFail();
    }

    private function posted(string $type, mixed $record): bool
    {
        return $type === 'journal_entry' ? (bool) $record->posted_at : ($record->status === 'opening_antecedent' || JournalEntry::where('source_document_id', $record->id)->where('source_revision', $record->source_revision)->whereNotNull('posted_at')->exists());
    }

    private function references(string $type, mixed $target): array
    {
        if ($type === 'journal_entry') { return app(EvidenceManifest::class)->references($target); }
        $refs = [['type' => $type, 'id' => $target->id]];
        $ids = JournalEntry::where('source_document_id', $target->id)->pluck('id')->merge(OpenItem::where('source_document_id', $target->id)->pluck('origin_entry_id'))->filter()->unique();
        foreach ($ids as $id) { $refs[] = ['type' => 'journal_entry', 'id' => $id]; }
        return $refs;
    }

    private function info(string $type, mixed $record): array
    {
        return ['type' => $type, 'id' => $record->id, 'version' => (int) $record->version, 'reference' => $record->reference ?? $record->number,
            'concept' => $record->concept ?? null, 'status' => $record->status, 'posted' => $this->posted($type, $record), 'source_revision' => $record->source_revision];
    }

    private function ownedQuery(array $references): mixed
    {
        return Attachment::where(function ($q) use ($references) {
            foreach ($references as $reference) { $q->orWhere(fn($q) => $q->where('resource_type', $reference['type'])->where('resource_id', $reference['id'])); }
        });
    }

    public function resource(Attachment $file): array
    {
        $integrity = app(AttachmentStorage::class)->check($file)['state'];
        return [...$file->only(['id', 'resource_type', 'resource_id', 'title', 'reason', 'original_name', 'mime', 'size', 'sha256', 'uploaded_by', 'uploaded_by_name', 'resource_version', 'resource_snapshot', 'after_posting', 'supersedes_id', 'scan_status', 'scan_engine', 'scan_database', 'scanned_at', 'created_at']),
            'company_id' => app(CompanyContext::class)->id(), 'created_at' => $file->created_at->setTimezone('America/Caracas')->toIso8601String(),
            'preview_available' => $file->preview_path !== null && app(AttachmentStorage::class)->check($file, true)['state'] === 'verified', 'preview_sha256' => $file->preview_sha256,
            'integrity' => $integrity, 'superseded_by' => Attachment::where('resource_type', $file->resource_type)->where('resource_id', $file->resource_id)->where('supersedes_id', $file->id)->value('id')];
    }

    public function index(string $type, string $id, int $user, int $page = 1): array
    {
        $this->actor($user);
        return DB::readSnapshot(function () use ($type, $id, $page) {
            $record = $this->target($type, $id); $refs = $this->references($type, $record);
            $rows = $this->ownedQuery($refs)->orderByDesc('created_at')->orderByDesc('id')->paginate(25, ['*'], 'page', $page);
            $approved = $type === 'journal_entry' ? ($record->metadata['support_manifest'] ?? null) : ($record->metadata['validation']['support_manifest'] ?? null);
            $linked = [];
            foreach ($refs as $ref) {
                if ($ref['type'] === $type && $ref['id'] === $id) { continue; }
                $target = $this->target($ref['type'], $ref['id']); $linked[] = $this->info($ref['type'], $target);
            }
            return ['resource' => $this->info($type, $record), 'approved_manifest' => $approved, 'linked_resources' => $linked,
                'data' => $rows->getCollection()->map(fn($file) => $this->resource($file))->all(), 'meta' => ['company_id' => app(CompanyContext::class)->id(), 'total' => $rows->total(), 'page' => $rows->currentPage(), 'last_page' => $rows->lastPage()],
                'constraints' => ['max_bytes' => 5 * 1024 * 1024, 'extensions' => array_keys(AttachmentFormat::TYPES), 'server_only' => true, 'original_bytes_preserved' => true]];
        });
    }

    private function copy(string $source, string $target): void
    {
        $input = fopen($source, 'rb'); $output = fopen($target, 'xb');
        if (!$input || !$output) { if ($input) { fclose($input); } if ($output) { fclose($output); } throw new \RuntimeException('No se pudo conservar el archivo recibido.'); }
        try {
            $expected = filesize($source);
            if (stream_copy_to_stream($input, $output) !== $expected || !fflush($output) || function_exists('fsync') && !fsync($output)) { throw new \RuntimeException('El soporte no se escribió completamente.'); }
        } finally { fclose($input); fclose($output); }
        chmod($target, 0400);
    }

    private function touchEntry(JournalEntry $entry, int $user, string $file): void
    {
        if ($entry->posted_at || $entry->status === 'withdrawn') { return; }
        $before = $entry->version;
        $entry->fill(['version' => $before + 1, 'status' => $entry->status === 'draft' ? 'draft' : 'observed', 'approved_by' => null, 'approved_at' => null, 'approved_version' => null, 'approval_reason' => null, 'metadata' => [...$entry->metadata ?? [], 'support_manifest' => null]]);
        $entry->save();
        app(AuditService::class)->record($user, 'entry.supports_changed', 'journal_entry', $entry->id, ['before_version' => $before, 'version' => $entry->version, 'attachment_id' => $file, 'approval_invalidated' => true]);
    }

    private function invalidate(string $type, mixed $target, int $user, string $file): bool
    {
        $invalidated = false;
        if ($type === 'source_document' && !$this->posted($type, $target) && $target->status !== 'excluded') {
            $target->fill(['status' => 'draft', 'version' => $target->version + 1, 'validated_by' => null, 'validated_at' => null, 'validated_version' => null, 'metadata' => [...$target->metadata ?? [], 'validation' => null]]);
            $target->save();
            FiscalRecord::where('source_document_id', $target->id)->whereIn('status', ['validated', 'source_changed'])->update(['status' => 'superseded']);
            $invalidated = true;
        }
        JournalEntry::whereNull('posted_at')->where('status', '!=', 'withdrawn')->orderBy('id')->chunkById(100, function ($entries) use ($type, $target, $user, $file, &$invalidated) {
            foreach ($entries as $entry) {
                foreach (app(EvidenceManifest::class)->references($entry) as $ref) {
                    if ($ref['type'] === $type && $ref['id'] === $target->id) { $this->touchEntry($entry, $user, $file); $invalidated = true; break; }
                }
            }
        });
        return $invalidated;
    }

    public function upload(string $type, string $id, UploadedFile $file, array $input, int $version, int $user, ?string $key): array
    {
        $this->actor($user, 'attachments'); $this->target($type, $id);
        if (!$file->isValid() || $file->getSize() < 1 || $file->getSize() > 5 * 1024 * 1024) { throw new ApiProblem('ATTACHMENT_TOO_LARGE', 'Adjunta un archivo válido de hasta 5 MiB.', 413); }
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if (!isset(AttachmentFormat::TYPES[$extension])) { throw new ApiProblem('ATTACHMENT_FORMAT', 'Selecciona PDF, PNG, JPG, XLSX, CSV, TXT o XML.'); }
        $directory = app(PrivateStorage::class)->directory('staging/' . $user . '/' . Str::uuid());
        $stage = $directory . '/original.bin'; $written = [];
        try {
            $this->copy($file->getRealPath(), $stage);
            $scan = app(AttachmentScanner::class)->scan($stage);
            $format = app(AttachmentFormat::class)->inspect($stage, $extension, $directory);
            $sha = hash_file('sha256', $stage); $size = filesize($stage);
            $name = preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName())));
            $name = mb_substr(pathinfo($name ?: 'soporte', PATHINFO_FILENAME) ?: 'soporte', 0, 160) . '.' . $extension;
            $previous = !empty($input['supersedes_id']) ? $input['supersedes_id'] : null;
            $identity = hash('sha256', json_encode([$type, $id, $sha, $previous], JSON_THROW_ON_ERROR));
            $payload = ['resource_type' => $type, 'resource_id' => $id, 'resource_version' => $version, 'title' => $input['title'], 'reason' => $input['reason'], 'supersedes_id' => $previous, 'original_name' => $name, 'mime' => $format['mime'], 'size' => $size, 'sha256' => $sha];
            return DB::transaction(function () use ($type, $id, $version, $user, $key, $input, $previous, $identity, $payload, $stage, $format, $scan, $name, $sha, $size, &$written) {
                $c = app(CompanyContext::class);
                DB::table('accounting_companies')->where('organization_id', $c->organizationId())->where('id', $c->id())->lockForUpdate()->first();
                $actor = $this->actor($user, 'attachments'); $target = $this->target($type, $id, true);
                return app(OperationService::class)->execute($user, 'attachment.add', $key, $payload, function () use ($type, $id, $version, $user, $input, $previous, $identity, $target, $actor, $stage, $format, $scan, $name, $sha, $size, &$written) {
                    if ($duplicate = Attachment::where('resource_type', $type)->where('resource_id', $id)->where('identity_hash', $identity)->first()) {
                        app(AttachmentStorage::class)->require($duplicate);
                        return ['attachment' => $this->resource($duplicate), 'resource' => $this->info($type, $target), 'duplicate' => true, 'review_invalidated' => false];
                    }
                    // A new client identity must not create another active copy
                    // of the same original after an interrupted response. An
                    // explicit successor may still restore historical content.
                    $same = app(EvidenceManifest::class)->current([['type' => $type, 'id' => $id]])
                        ->first(fn($row) => hash_equals($row->sha256, $sha) && (!$previous || $row->id === $previous));
                    if ($same) {
                        app(AttachmentStorage::class)->require($same);
                        return ['attachment' => $this->resource($same), 'resource' => $this->info($type, $target), 'duplicate' => true, 'review_invalidated' => false];
                    }
                    if ((int) $target->version !== $version) { throw new ApiProblem('VERSION_CONFLICT', 'El registro cambió. Actualiza sus datos antes de adjuntar un soporte.', 412); }
                    if ($previous) {
                        Attachment::whereKey($previous)->where('resource_type', $type)->where('resource_id', $id)->firstOrFail();
                        if (Attachment::where('supersedes_id', $previous)->exists()) { throw new ApiProblem('ATTACHMENT_ALREADY_REVISED', 'Ese soporte ya tiene una versión posterior. Selecciona la versión actual.', 409); }
                    }
                    $after = $this->posted($type, $target); $before = $this->info($type, $target);
                    $attachmentId = (string) Str::uuid();
                    $folder = app(PrivateStorage::class)->directory('attachments/' . $attachmentId);
                    $written[$attachmentId] = $folder;
                    $this->copy($stage, $folder . '/original.bin');
                    if (!hash_equals($sha, hash_file('sha256', $folder . '/original.bin'))) { throw new \RuntimeException('La copia no conserva la integridad del original.'); }
                    $previewSha = null;
                    if ($format['preview']) { $this->copy($format['preview'], $folder . '/preview.png'); $previewSha = hash_file('sha256', $folder . '/preview.png'); }
                    $willChange = !$after && !in_array($target->status, ['withdrawn', 'excluded'], true);
                    $record = Attachment::create(['id' => $attachmentId, 'resource_type' => $type, 'resource_id' => $id, 'title' => $input['title'], 'reason' => $input['reason'], 'original_name' => $name,
                        'path' => app(AttachmentStorage::class)->originalRelative($attachmentId), 'mime' => $format['mime'], 'size' => $size, 'sha256' => $sha, 'identity_hash' => $identity,
                        'scan_status' => 'verified', 'scan_engine' => $scan['engine'], 'scan_database' => $scan['database'], 'scanned_at' => $scan['scanned_at'],
                        'preview_path' => $previewSha ? app(AttachmentStorage::class)->previewRelative($attachmentId) : null, 'preview_sha256' => $previewSha,
                        'uploaded_by' => $user, 'uploaded_by_name' => $actor->name, 'resource_version' => $version + ($willChange ? 1 : 0), 'resource_snapshot' => $before, 'after_posting' => $after, 'supersedes_id' => $previous]);
                    $invalidated = $this->invalidate($type, $target, $user, $record->id);
                    $target = $this->target($type, $id);
                    app(AuditService::class)->record($user, 'attachment.added', $type, $id, ['attachment_id' => $record->id, 'reason' => $input['reason'], 'title' => $input['title'], 'sha256' => $sha, 'size' => $size, 'before_version' => $version, 'version' => $target->version, 'source_revision' => $target->source_revision, 'after_posting' => $after, 'supersedes_id' => $previous, 'review_invalidated' => $invalidated]);
                    return ['attachment' => $this->resource($record), 'resource' => $this->info($type, $target), 'duplicate' => false, 'review_invalidated' => $invalidated];
                });
            }, 5);
        } finally {
            foreach ($written as $attachmentId => $folder) {
                // A failed commit may be uncertain. Preserve its bytes if the
                // database cannot confirm whether a record exists.
                try { $exists = Attachment::whereKey($attachmentId)->exists(); } catch (\Throwable) { continue; }
                if (!$exists) { foreach (['original.bin', 'preview.png'] as $part) { if (is_file($folder . '/' . $part)) { unlink($folder . '/' . $part); } } @rmdir($folder); }
            }
            foreach (['original.bin', 'preview.png'] as $part) { if (is_file($directory . '/' . $part)) { unlink($directory . '/' . $part); } } @rmdir($directory);
        }
    }

    public function download(string $id, int $user, bool $preview = false): array
    {
        $this->actor($user); $file = Attachment::findOrFail($id); $this->target($file->resource_type, $file->resource_id);
        $path = app(AttachmentStorage::class)->require($file, $preview);
        DB::transaction(function () use ($user, $file, $preview) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first(); $this->actor($user);
            app(AuditService::class)->record($user, 'attachment.downloaded', $file->resource_type, $file->resource_id, ['attachment_id' => $file->id, 'preview' => $preview, 'sha256' => $preview ? $file->preview_sha256 : $file->sha256]);
        });
        return ['path' => $path, 'filename' => $preview ? 'vista-soporte.png' : $file->original_name, 'mime' => $preview ? 'image/png' : $file->mime, 'sha256' => $preview ? $file->preview_sha256 : $file->sha256];
    }
}
