<?php

namespace Modules\Accounting\Tests;

use Illuminate\Support\Str;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Models\{Incident, SourceVersion, SourceResolution};
use Modules\Accounting\Services\{DocumentService, EntryService, Pro9SourceReader, SourceIncidentService, AuditIntegrityService};
use Modules\Accounting\Support\TenantDB as DB;

trait SourceIncidentIntegrationAssertions
{
    public function test_unposted_received_change_requires_validation_instead_of_metadata_acknowledgment(): void
    {
        $fixture = $this->nativeSourceFixture(); $observed = $this->observeNative($fixture, ['total' => '2320', 'total_value' => '2000', 'total_igv' => '320', 'total_taxes' => '320']); $case = $observed['case'];
        $this->assertFalse(in_array(true, $case['actions'], true));
        try { app(SourceIncidentService::class)->resolve($case['incident']['id'], $case['incident']['version'], [...$observed['input'], 'action' => 'acknowledge_metadata'], $this->admin->id, 'source-unvalidated-' . Str::uuid()); $this->fail('La validación del documento recibido no se omite al reconocer un cambio.'); }
        catch (ApiProblem $problem) { $this->assertSame('SOURCE_FINANCIAL_CHANGE', $problem->errorCode); }
        $document = $fixture['document']->fresh(); $components = $fixture['config']['input']['tax_components']; $components[0]['base'] = '2000'; $components[0]['amount'] = '320';
        app(DocumentService::class)->validate($document->id, $document->version, ['source_confirmed' => true, 'tax_components' => $components], $this->admin->id);
        $this->assertTrue(app(SourceIncidentService::class)->show($case['incident']['id'])['actions']['review_received']);
    }

    public static function unsupportedNativeChanges(): array
    {
        return [[['currency_type_id' => 'XXX']], [['document_type_id' => '99']], [['customer' => json_encode(['name' => 'Tercero incompleto', 'number' => ''])]]];
    }

    /** @dataProvider unsupportedNativeChanges */
    public function test_unsupported_native_changes_are_observed_once_without_replacing_registered_values(array $change): void
    {
            $fixture = $this->postedNativeCase(); $document = $fixture['document'];
            $before = (array) DB::table('accounting_source_documents')->where('id', $document->id)->first();
            $observed = $this->observeNative($fixture, $change); $case = $observed['case'];
            $this->assertSame($before, (array) DB::table('accounting_source_documents')->where('id', $document->id)->first());
            $this->assertNotEmpty($case['projection_issues']);
            $this->assertFalse(in_array(true, $case['actions'], true));
            $again = app(Pro9SourceReader::class)->receive('sales', $fixture['id'], $this->admin->id);
            $this->assertTrue($again['unchanged']);
            $this->assertSame($case['incident']['id'], $again['incident_id']);
            $this->assertSame(2, SourceVersion::where('source_document_id', $document->id)->count());
            $this->assertSame(1, Incident::where('resource_id', $document->id)->where('status', 'open')->count());
        $this->assertSame(1, app(Pro9SourceReader::class)->status()['pending']);
    }

    public function test_disappeared_native_source_is_an_idempotent_observation_without_inferring_annulment(): void
    {
        $fixture = $this->postedNativeCase(); $document = $fixture['document'];
        $before = (array) DB::table('accounting_source_documents')->where('id', $document->id)->first();
        $entryBefore = (array) DB::table('accounting_journal_entries')->where('id', $fixture['entry']->id)->first();
        DB::table('documents')->where('id', $fixture['id'])->delete();
        $received = app(Pro9SourceReader::class)->receive('sales', $fixture['id'], $this->admin->id);
        $again = app(Pro9SourceReader::class)->receive('sales', $fixture['id'], $this->admin->id);
        $case = app(SourceIncidentService::class)->show($received['incident_id']);
        $this->assertTrue($again['unchanged']); $this->assertSame($received['incident_id'], $again['incident_id']);
        $this->assertFalse(in_array(true, $case['actions'], true));
        $this->assertSame('missing', json_decode($case['observed_snapshot_json'], true)['availability']);
        $this->assertSame('verified', $case['observed']['integrity']['state']);
        $this->assertSame($before, (array) DB::table('accounting_source_documents')->where('id', $document->id)->first());
        $this->assertSame($entryBefore, (array) DB::table('accounting_journal_entries')->where('id', $fixture['entry']->id)->first());
    }

    public function test_source_resolution_receipts_recheck_audit_permission_before_returning_history(): void
    {
        $fixture = $this->postedNativeCase(); $observed = $this->observeNative($fixture, []); $case = $observed['case']; $key = 'source-private-receipt-' . Str::uuid();
        $input = [...$observed['input'], 'action' => 'acknowledge_metadata'];
        app(SourceIncidentService::class)->resolve($case['incident']['id'], $case['incident']['version'], $input, $this->admin->id, $key);
        $operations = app(\Modules\Accounting\Services\OperationService::class);
        $this->assertSame('confirmed', $operations->status($this->admin->id, 'source.resolve', $key)['status']);
        DB::table('accounting_memberships')->where('company_id', $this->a->id)->where('user_id', $this->admin->id)->update(['roles' => json_encode(['viewer'])]);
        foreach (['status', 'cancel'] as $method) {
            try { $operations->$method($this->admin->id, 'source.resolve', $key, 'Cancelar un envío ya revisado requiere autorización vigente.'); $this->fail('La recuperación respeta el permiso actual de auditoría.'); }
            catch (ApiProblem $problem) { $this->assertSame('FORBIDDEN_ACTION', $problem->errorCode); }
        }
    }

    private function postedNativeCase(): array
    {
        $fixture = $this->nativeSourceFixture(); $documents = app(DocumentService::class); $document = $fixture['document'];
        $document = $documents->validate($document->id, $document->version, ['source_confirmed' => true, 'tax_components' => $fixture['config']['input']['tax_components']], $this->admin->id);
        $entry = $documents->propose($document->id, $document->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id);
        $entry = $this->postPrepared($entry, 'source-case-original-' . Str::uuid());
        return [...$fixture, 'document' => $document->fresh(), 'entry' => $entry];
    }

    private function observeNative(array $fixture, array $change): array
    {
        DB::table('documents')->where('id', $fixture['id'])->update([...$change, 'updated_at' => now()->addMinute()]);
        $received = app(Pro9SourceReader::class)->receive('sales', $fixture['id'], $this->admin->id);
        $case = app(SourceIncidentService::class)->show($received['incident_id']);
        return ['case' => $case, 'input' => ['source_version_id' => $case['observed']['id'], 'document_version' => $case['document']['version'], 'reason' => 'Revisión explícita del cambio de origen ficticio con su evidencia conservada.']];
    }

    public function test_source_incident_rejects_applying_monetary_change_with_active_posted_effects(): void
    {
        $fixture = $this->postedNativeCase(); $observed = $this->observeNative($fixture, ['total' => '2320', 'total_value' => '2000', 'total_igv' => '320', 'total_taxes' => '320']);
        $case = $observed['case']; $this->assertFalse($case['business_basis_unchanged']); $this->assertFalse($case['actions']['apply_after_reversal']);
        try { app(SourceIncidentService::class)->resolve($case['incident']['id'], $case['incident']['version'], [...$observed['input'], 'action' => 'apply_after_reversal'], $this->admin->id, 'source-block-' . Str::uuid()); $this->fail('La corrección no sustituye efectos activos.'); }
        catch (ApiProblem $e) { $this->assertSame('SOURCE_EFFECTS_ACTIVE', $e->errorCode); }
        $this->assertSame(0, SourceResolution::count()); $this->assertSame('open', Incident::findOrFail($case['incident']['id'])->status);
        $this->assertSame('1160.000000000000', $fixture['document']->fresh()->amounts['total']);
    }

    public function test_metadata_only_source_acknowledgment_keeps_registered_document_and_entry_unchanged_and_replays_receipt(): void
    {
        $fixture = $this->postedNativeCase();
        $before = (array) DB::table('accounting_source_documents')->where('id', $fixture['document']->id)->first();
        $entryBefore = (array) DB::table('accounting_journal_entries')->where('id', $fixture['entry']->id)->first();
        $observed = $this->observeNative($fixture, []); $case = $observed['case'];
        $this->assertTrue($case['business_basis_unchanged']); $this->assertTrue($case['actions']['acknowledge_metadata']);
        $key = 'source-ack-' . Str::uuid(); $input = [...$observed['input'], 'action' => 'acknowledge_metadata'];
        $service = app(SourceIncidentService::class); $resolved = $service->resolve($case['incident']['id'], $case['incident']['version'], $input, $this->admin->id, $key);
        $again = $service->resolve($case['incident']['id'], $case['incident']['version'], $input, $this->admin->id, $key);
        $this->assertSame($resolved['resolution']['id'], $again['resolution']['id']); $this->assertSame(1, SourceResolution::count());
        $this->assertSame('verified', $resolved['resolution']['integrity']);
        $this->assertSame($before, (array) DB::table('accounting_source_documents')->where('id', $fixture['document']->id)->first());
        $this->assertSame($entryBefore, (array) DB::table('accounting_journal_entries')->where('id', $fixture['entry']->id)->first());
        app(Pro9SourceReader::class)->assertCurrent($fixture['document']->fresh());
        $this->assertFalse(app(Pro9SourceReader::class)->receive('sales', $fixture['id'], $this->admin->id)['requires_reconciliation']);
        $this->assertSame('resolved', $service->show($case['incident']['id'])['incident']['status']);
        $this->assertSame('verified', app(AuditIntegrityService::class)->verify()['state']);
    }

    public function test_monetary_source_correction_requires_posted_reversal_then_creates_a_fresh_reviewable_projection(): void
    {
        $fixture = $this->postedNativeCase(); $observed = $this->observeNative($fixture, ['total' => '2320', 'total_value' => '2000', 'total_igv' => '320', 'total_taxes' => '320']);
        $case = $observed['case']; $entries = app(EntryService::class); $date = now('America/Caracas')->format('Y-m-d');
        $reverse = $entries->prepareReversal($fixture['entry']->id, $date, 'Corregir el cambio monetario ficticio con su reverso revisado.', $this->admin->id);
        $this->assertFalse(app(SourceIncidentService::class)->show($case['incident']['id'])['actions']['apply_after_reversal']);
        $reverse = $this->postPrepared($reverse, 'source-case-reversal-' . Str::uuid());
        $this->assertTrue(app(SourceIncidentService::class)->show($case['incident']['id'])['actions']['apply_after_reversal']);
        $entryBefore = (array) DB::table('accounting_journal_entries')->where('id', $fixture['entry']->id)->first();
        $reverseBefore = (array) DB::table('accounting_journal_entries')->where('id', $reverse->id)->first();
        $result = app(SourceIncidentService::class)->resolve($case['incident']['id'], $case['incident']['version'], [...$observed['input'], 'action' => 'apply_after_reversal'], $this->admin->id, 'source-apply-' . Str::uuid());
        $document = $fixture['document']->fresh(); $this->assertSame(2, $document->source_revision); $this->assertSame('received', $document->status); $this->assertNull($document->validated_by);
        $this->assertSame('2320.000000000000', $document->amounts['total']);
        $this->assertSame($entryBefore, (array) DB::table('accounting_journal_entries')->where('id', $fixture['entry']->id)->first());
        $this->assertSame($reverseBefore, (array) DB::table('accounting_journal_entries')->where('id', $reverse->id)->first());
        $evidence = json_decode($result['resolution']['evidence_json'], true);
        $this->assertSame('1160.000000000000', $evidence['before_document']['amounts']['total']);
        $this->assertSame('2320.000000000000', $evidence['after_document']['amounts']['total']);
        $this->assertCount(1, $evidence['prior_fiscal_records']);
        $this->assertSame(2, SourceVersion::where('source_document_id', $document->id)->count());
        $components = $fixture['config']['input']['tax_components']; $components[0]['base'] = '2000'; $components[0]['amount'] = '320';
        $documents = app(DocumentService::class); $document = $documents->validate($document->id, $document->version, ['source_confirmed' => true, 'tax_components' => $components], $this->admin->id);
        $replacement = $documents->propose($document->id, $document->version, $date, $this->invoiceAccounts(), $this->admin->id);
        $this->assertNotSame($fixture['entry']->id, $replacement->id);
        $replacement = $this->postPrepared($replacement, 'source-case-replacement-' . Str::uuid());
        $this->assertSame(2, $replacement->source_revision); $this->assertSame('2320.00000000', $replacement->total_debit);
    }

    public function test_voided_native_source_after_reversal_is_excluded_without_posting_another_invoice(): void
    {
        $fixture = $this->postedNativeCase(); $observed = $this->observeNative($fixture, ['state_type_id' => '09']); $case = $observed['case'];
        $reverse = app(EntryService::class)->prepareReversal($fixture['entry']->id, now('America/Caracas')->format('Y-m-d'), 'Reversar la anulación ficticia recibida de SAB.', $this->admin->id);
        $this->postPrepared($reverse, 'source-void-reversal-' . Str::uuid());
        app(SourceIncidentService::class)->resolve($case['incident']['id'], $case['incident']['version'], [...$observed['input'], 'action' => 'apply_after_reversal'], $this->admin->id, 'source-void-apply-' . Str::uuid());
        $doc = $fixture['document']->fresh(); $this->assertSame('excluded', $doc->status); $this->assertSame('09', $doc->metadata['source_state']);
        try { app(DocumentService::class)->validate($doc->id, $doc->version, ['source_confirmed' => true], $this->admin->id); $this->fail('Un origen anulado no se vuelve a validar como factura vigente.'); }
        catch (ApiProblem $e) { $this->assertSame('DOCUMENT_STATE', $e->errorCode); }
    }

    public function test_source_resolution_rejects_a_native_change_that_happened_after_the_reviewed_snapshot(): void
    {
        $fixture = $this->postedNativeCase(); $observed = $this->observeNative($fixture, []); $case = $observed['case'];
        DB::table('documents')->where('id', $fixture['id'])->update(['state_type_id' => '09', 'updated_at' => now()->addMinutes(2)]);
        try { app(SourceIncidentService::class)->resolve($case['incident']['id'], $case['incident']['version'], [...$observed['input'], 'action' => 'acknowledge_metadata'], $this->admin->id, 'source-stale-' . Str::uuid()); $this->fail('No se reconoce un origen distinto del revisado.'); }
        catch (ApiProblem $e) { $this->assertSame('SOURCE_REVIEW_STALE', $e->errorCode); }
        $this->assertSame(0, SourceResolution::count()); $this->assertSame('open', Incident::findOrFail($case['incident']['id'])->status);
    }

    public function test_source_resolution_rechecks_review_permission_and_company_ownership(): void
    {
        $fixture = $this->postedNativeCase(); $observed = $this->observeNative($fixture, []); $case = $observed['case'];
        DB::table('accounting_memberships')->where('company_id', $this->a->id)->where('user_id', $this->admin->id)->update(['roles' => json_encode(['admin'])]);
        try { app(SourceIncidentService::class)->resolve($case['incident']['id'], $case['incident']['version'], [...$observed['input'], 'action' => 'acknowledge_metadata'], $this->admin->id, 'source-no-review-' . Str::uuid()); $this->fail('Administrar el vínculo no autoriza revisar su efecto contable.'); }
        catch (ApiProblem $e) { $this->assertSame('FORBIDDEN_ACTION', $e->errorCode); }
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(CompanyContext::class)->run($this->b, fn() => app(SourceIncidentService::class)->show($case['incident']['id']));
    }

    public function test_source_resolution_record_and_its_before_after_evidence_are_immutable(): void
    {
        $fixture = $this->postedNativeCase(); $observed = $this->observeNative($fixture, []); $case = $observed['case'];
        $result = app(SourceIncidentService::class)->resolve($case['incident']['id'], $case['incident']['version'], [...$observed['input'], 'action' => 'acknowledge_metadata'], $this->admin->id, 'source-immutable-' . Str::uuid());
        try { DB::table('accounting_source_resolutions')->where('id', $result['resolution']['id'])->update(['reason' => 'Otra explicación posterior']); $this->fail('La resolución no se sobrescribe.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('inmutable', strtolower($e->getMessage())); }
        $migration = require dirname(__DIR__) . '/Database/Migrations/2026_11_12_001400_preserve_source_resolutions.php';
        $this->expectException(\RuntimeException::class); $this->expectExceptionMessage('eliminar resoluciones'); $migration->down();
    }
}
