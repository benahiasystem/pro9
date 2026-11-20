<?php

namespace Modules\Accounting\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Models\{Attachment, JournalEntry};
use Modules\Accounting\Services\{AttachmentService, AttachmentStorage, EntryService};
use Modules\Accounting\Support\{TenantDB as DB, PrivateStorage};

trait AttachmentIntegrationAssertions
{
    private function supportConfiguration(): void
    {
        app('config')->set('accounting.private_storage', sys_get_temp_dir() . '/accounting-support-core');
        app('config')->set('accounting.attachments.scanner_endpoint', 'tcp://accounting-av:3310');
        DB::table('accounting_memberships')->where('user_id', $this->admin->id)->whereIn('company_id', [$this->a->id, $this->b->id])->update(['roles' => json_encode(['admin', 'accountant'])]);
    }

    private function supportUpload(string $bytes, string $name = 'soporte-ficticio.txt'): UploadedFile
    {
        $path = sys_get_temp_dir() . '/support-fixture-' . Str::uuid();
        file_put_contents($path, $bytes);
        return new UploadedFile($path, $name, null, null, true);
    }

    private function attachSupport(JournalEntry $entry, UploadedFile $file, ?string $previous = null, ?string $key = null): array
    {
        return app(AttachmentService::class)->upload('journal_entry', $entry->id, $file, ['title' => 'Soporte ficticio conservado', 'reason' => 'Documento ficticio que respalda la operación de pruebas.', 'supersedes_id' => $previous], $entry->version, $this->admin->id, $key ?? 'support:' . Str::uuid());
    }

    /** @group accounting-attachments */
    public function test_support_upload_replays_its_receipt_without_duplicating_bytes_or_invalidating_again(): void
    {
        $this->supportConfiguration(); $entry = $this->draft();
        $file = $this->supportUpload("Soporte contable ficticio.\n"); $key = 'support-receipt-' . Str::uuid();
        $first = $this->attachSupport($entry, $file, null, $key);
        $again = $this->attachSupport($entry, $file, null, $key);
        $this->assertSame($first['attachment']['id'], $again['attachment']['id']);
        $this->assertSame(1, Attachment::count());
        $this->assertSame($entry->version + 1, $entry->fresh()->version);
        $this->assertArrayNotHasKey('path', $first['attachment']);
        $this->assertSame(hash('sha256', "Soporte contable ficticio.\n"), $first['attachment']['sha256']);
        $record = Attachment::firstOrFail();
        $this->assertSame('verified', app(AttachmentStorage::class)->check($record)['state']);
        $this->assertSame('confirmed', app(\Modules\Accounting\Services\OperationService::class)->status($this->admin->id, 'attachment.add', $key)['status']);
        $duplicate = $this->attachSupport($entry->fresh(), $file);
        $this->assertTrue($duplicate['duplicate']);
        $this->assertSame(1, Attachment::count());
        $this->assertSame($first['resource']['version'], $entry->fresh()->version);
    }

    /** @group accounting-attachments */
    public function test_changed_supports_require_new_approval_and_replaced_bytes_block_posting(): void
    {
        $this->supportConfiguration(); $entry = $this->approved(); $before = $entry->version;
        $result = $this->attachSupport($entry, $this->supportUpload('Soporte previo al asiento definitivo.'));
        $entry->refresh();
        $this->assertSame('observed', $entry->status);
        $this->assertNull($entry->approved_by);
        $this->assertSame($before + 1, $entry->version);
        $this->assertTrue($result['review_invalidated']);
        $entries = app(EntryService::class);
        $entry = $entries->transition($entry->id, $entry->version, $this->admin->id, 'submit');
        $entry = $entries->transition($entry->id, $entry->version, $this->admin->id, 'approve', 'Autocontrol explícito del caso ficticio con su soporte revisado.');
        $this->assertCount(1, $entry->metadata['support_manifest']['items']);
        $record = Attachment::firstOrFail(); $path = app(AttachmentStorage::class)->require($record); $original = file_get_contents($path);
        chmod($path, 0600); file_put_contents($path, 'contenido sustituido');
        try { $entries->post($entry->id, $entry->version, $this->admin->id, 'support-corrupted-post'); $this->fail('El soporte sustituido no acredita la aprobación.'); }
        catch (ApiProblem $error) { $this->assertSame('ATTACHMENT_INTEGRITY', $error->errorCode); }
        $this->assertNull($entry->fresh()->posted_at);
        file_put_contents($path, $original); chmod($path, 0400);
        $entries->post($entry->id, $entry->version, $this->admin->id, 'support-restored-post');
        $this->assertNotNull($entry->fresh()->posted_at);
        $this->assertSame('100.00000000', $entry->fresh()->total_debit);
    }

    /** @group accounting-attachments */
    public function test_support_successors_do_not_duplicate_active_bytes_and_can_restore_historical_content_explicitly(): void
    {
        $this->supportConfiguration(); $entry = $this->draft();
        $original = $this->supportUpload('Contenido original ficticio.');
        $changed = $this->supportUpload('Contenido aclarado ficticio.');
        $first = $this->attachSupport($entry, $original);
        $second = $this->attachSupport($entry->fresh(), $changed, $first['attachment']['id']);
        $version = $entry->fresh()->version;
        $duplicate = $this->attachSupport($entry->fresh(), $changed);
        $unchangedSuccessor = $this->attachSupport($entry->fresh(), $changed, $second['attachment']['id']);
        $this->assertTrue($duplicate['duplicate']);
        $this->assertTrue($unchangedSuccessor['duplicate']);
        $this->assertSame($second['attachment']['id'], $duplicate['attachment']['id']);
        $this->assertSame($version, $entry->fresh()->version);
        $this->assertSame(2, Attachment::count());
        $restored = $this->attachSupport($entry->fresh(), $original, $second['attachment']['id']);
        $this->assertFalse($restored['duplicate']);
        $this->assertSame($first['attachment']['sha256'], $restored['attachment']['sha256']);
        $this->assertSame(3, Attachment::count());
        $this->assertSame($version + 1, $entry->fresh()->version);
    }

    /** @group accounting-attachments */
    public function test_posted_entry_keeps_its_original_state_while_later_support_versions_and_loss_are_detected(): void
    {
        $this->supportConfiguration(); $entry = $this->postPrepared($this->draft(), 'support-posted-before-append');
        $before = (array) DB::table('accounting_journal_entries')->where('id', $entry->id)->first();
        $first = $this->attachSupport($entry, $this->supportUpload('Primera evidencia ficticia.'));
        $this->assertTrue($first['attachment']['after_posting']);
        $this->assertFalse($first['review_invalidated']);
        $second = $this->attachSupport($entry, $this->supportUpload('Segunda evidencia ficticia que aclara la anterior.'), $first['attachment']['id']);
        $this->assertSame($first['attachment']['id'], $second['attachment']['supersedes_id']);
        $this->assertSame(2, Attachment::count());
        $this->assertEquals($before, (array) DB::table('accounting_journal_entries')->where('id', $entry->id)->first());
        $record = Attachment::findOrFail($first['attachment']['id']); $path = app(AttachmentStorage::class)->require($record); unlink($path);
        $this->assertSame('missing', app(AttachmentStorage::class)->check($record)['state']);
        $case = app(AttachmentService::class)->index('journal_entry', $entry->id, $this->admin->id);
        $this->assertSame('missing', collect($case['data'])->firstWhere('id', $record->id)['integrity']);
        $this->assertEquals($before, (array) DB::table('accounting_journal_entries')->where('id', $entry->id)->first());
        try { DB::table('accounting_attachments')->where('id', $record->id)->update(['sha256' => str_repeat('0', 64)]); $this->fail('La huella de evidencia no se sustituye.'); }
        catch (\Illuminate\Database\QueryException $error) { $this->assertStringContainsString('Soporte conservado inmutable', $error->getMessage()); }
    }

    /** @group accounting-attachments */
    public function test_scanner_rejection_and_unavailability_leave_no_attachment_or_parent_change(): void
    {
        $this->supportConfiguration(); $entry = $this->draft();
        $sample = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
        try { $this->attachSupport($entry, $this->supportUpload($sample)); $this->fail('La muestra inerte de prueba EICAR debe rechazarse.'); }
        catch (ApiProblem $error) { $this->assertSame('ATTACHMENT_SCAN_REJECTED', $error->errorCode); }
        $this->assertSame(0, Attachment::count());
        $this->assertSame($entry->version, $entry->fresh()->version);
        app('config')->set('accounting.attachments.scanner_endpoint', 'tcp://accounting-av:1');
        try { $this->attachSupport($entry, $this->supportUpload('Archivo ficticio sin servicio de análisis.')); $this->fail('Sin analizador no se confirma el archivo.'); }
        catch (ApiProblem $error) { $this->assertSame('ATTACHMENT_SCANNER_UNAVAILABLE', $error->errorCode); }
        $this->assertSame(0, Attachment::count());
        $this->assertSame($entry->version, $entry->fresh()->version);
        $this->assertSame([], glob(app(PrivateStorage::class)->root() . '/staging/*/*/original.bin'));
    }

    /** @group accounting-attachments */
    public function test_support_download_resolves_the_company_before_opening_any_file(): void
    {
        $this->supportConfiguration(); $entry = $this->draft(); $saved = $this->attachSupport($entry, $this->supportUpload('Evidencia privada ficticia de empresa A.'));
        $id = $saved['attachment']['id'];
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(CompanyContext::class)->run($this->b, fn() => app(AttachmentService::class)->download($id, $this->admin->id));
    }

    /** @group accounting-attachments */
    public function test_support_permissions_are_checked_before_staging_or_scanning(): void
    {
        $this->supportConfiguration(); $entry = $this->draft();
        DB::table('accounting_memberships')->where('company_id', $this->a->id)->where('user_id', $this->admin->id)->update(['roles' => json_encode(['viewer'])]);
        try { $this->attachSupport($entry, $this->supportUpload('Una cuenta de consulta no adjunta evidencia.')); $this->fail('La consulta no autoriza cargas.'); }
        catch (ApiProblem $error) { $this->assertSame('FORBIDDEN_ACTION', $error->errorCode); }
        $this->assertSame(0, Attachment::count());
        $this->assertFalse(is_dir(app(PrivateStorage::class)->root() . '/staging'));
    }

    /** @group accounting-attachments */
    public function test_raster_preview_is_recoded_without_altering_the_original_evidence(): void
    {
        $this->supportConfiguration(); $entry = $this->draft();
        $path = sys_get_temp_dir() . '/support-image-' . Str::uuid() . '.png';
        $image = imagecreatetruecolor(3, 3); imagepng($image, $path); imagedestroy($image);
        $bytes = file_get_contents($path); $offset = strpos($bytes, "\0\0\0\0IEND");
        $text = "Comentario\0METADATO_FICTICIO_PRESERVADO";
        $chunk = pack('N', strlen($text)) . 'tEXt' . $text . pack('H*', hash('crc32b', 'tEXt' . $text));
        $original = substr($bytes, 0, $offset) . $chunk . substr($bytes, $offset);
        $saved = $this->attachSupport($entry, $this->supportUpload($original, 'comprobante-ficticio.png'));
        $record = Attachment::findOrFail($saved['attachment']['id']);
        $this->assertTrue($saved['attachment']['preview_available']);
        $this->assertSame($original, file_get_contents(app(AttachmentStorage::class)->require($record)));
        $preview = file_get_contents(app(AttachmentStorage::class)->require($record, true));
        $this->assertStringNotContainsString('METADATO_FICTICIO_PRESERVADO', $preview);
        $this->assertSame(hash('sha256', $preview), $record->preview_sha256);
        $this->assertNotSame($record->sha256, $record->preview_sha256);
    }

    /** @group accounting-attachments */
    public function test_document_support_changes_invalidate_pending_review_without_fabricating_a_new_source_revision(): void
    {
        $this->supportConfiguration(); $configuration = $this->invoiceConfiguration();
        $documents = app(\Modules\Accounting\Services\DocumentService::class);
        $document = $documents->create($configuration['input'], $this->admin->id);
        $document = $documents->validate($document->id, $document->version, [], $this->admin->id);
        $entry = $documents->propose($document->id, $document->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id);
        $entries = app(EntryService::class);
        $entry = $entries->transition($entry->id, $entry->version, $this->admin->id, 'submit');
        $entry = $entries->transition($entry->id, $entry->version, $this->admin->id, 'approve', 'Autocontrol explícito de la propuesta ficticia del documento.');
        $document->refresh(); $revision = $document->source_revision; $amounts = $document->amounts;
        app(AttachmentService::class)->upload('source_document', $document->id, $this->supportUpload('Factura ficticia que requiere revisar sus anexos.'), ['title' => 'Documento ficticio de soporte', 'reason' => 'Conservar el documento y revisar nuevamente sus soportes.'], $document->version, $this->admin->id, 'source-support-' . Str::uuid());
        $document->refresh();
        $this->assertSame($revision, $document->source_revision);
        $this->assertSame($amounts, $document->amounts);
        $this->assertSame('draft', $document->status);
        $this->assertNull($document->validated_by);
        $this->assertSame('observed', $entry->fresh()->status);
        $this->assertNull($entry->fresh()->approved_by);
        $document = $documents->validate($document->id, $document->version, [], $this->admin->id);
        $this->assertCount(1, $document->metadata['validation']['support_manifest']['items']);
        try { $documents->propose($document->id, $document->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id); $this->fail('Regenerar exige confirmar la evidencia anterior.'); }
        catch (ApiProblem $error) { $this->assertSame('PROPOSAL_REPLACEMENT_REQUIRED', $error->errorCode); }
        $entry = $documents->propose($document->id, $document->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id, ['replace_existing' => true, 'reason' => 'Revisar la propuesta ficticia después de incorporar el soporte.']);
        $this->postPrepared($entry, 'source-support-reapproved-post');
        $this->assertNotNull($entry->fresh()->posted_at);
    }

    /** @group accounting-attachments */
    public function test_later_document_support_does_not_rewrite_its_posted_fiscal_or_source_fields(): void
    {
        $this->supportConfiguration(); $configuration = $this->invoiceConfiguration();
        $documents = app(\Modules\Accounting\Services\DocumentService::class);
        $document = $documents->create($configuration['input'], $this->admin->id);
        $document = $documents->validate($document->id, $document->version, [], $this->admin->id);
        $entry = $documents->propose($document->id, $document->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id);
        $entry = $this->postPrepared($entry, 'posted-source-support-case');
        $document->refresh(); $before = (array) DB::table('accounting_source_documents')->where('id', $document->id)->first();
        $result = app(AttachmentService::class)->upload('source_document', $document->id, $this->supportUpload('Anexo ficticio posterior que no altera el documento.'), ['title' => 'Anexo posterior ficticio', 'reason' => 'Aportar una aclaración posterior sin modificar el documento.'], $document->version, $this->admin->id, 'posted-source-support-' . Str::uuid());
        $this->assertTrue($result['attachment']['after_posting']);
        $this->assertEquals($before, (array) DB::table('accounting_source_documents')->where('id', $document->id)->first());
        $case = app(AttachmentService::class)->index('source_document', $document->id, $this->admin->id);
        $this->assertSame($entry->id, $case['linked_resources'][0]['id']);
        $this->assertSame(1, $case['meta']['total']);
    }
}
