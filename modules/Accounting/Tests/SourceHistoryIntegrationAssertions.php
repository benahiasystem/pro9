<?php

namespace Modules\Accounting\Tests;

use Illuminate\Support\Str;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Models\{SourceVersion, JournalEntry, Incident};
use Modules\Accounting\Services\{DocumentService, Pro9SourceReader, SourceHistoryService, AuditIntegrityService};
use Modules\Accounting\Support\TenantDB as DB;

trait SourceHistoryIntegrationAssertions
{
    public static function nativeSourceKinds(): array { return [['sales'], ['purchases']]; }

    /** @dataProvider nativeSourceKinds */
    public function test_native_source_uses_exact_snapshot_fractions_and_detects_snapshot_only_changes(string $kind): void
    {
        $fixture = $this->nativeSourceFixture(); $table = $kind === 'sales' ? 'documents' : 'purchases'; $id = $fixture['id'];
        if ($kind === 'purchases') {
            $row = (array) DB::table('documents')->where('id', $id)->first(); unset($row['id']); $row['supplier'] = $row['customer']; unset($row['customer']);
            $id = DB::table($table)->insertGetId($row);
        }
        $snapshot = \App\Support\Exact\Decimal::snapshot(['total' => '7/3', 'total_value' => '2', 'total_igv' => '1/3', 'total_taxes' => '1/3', 'exchange_rate_sale' => '13/7']);
        DB::table($table)->where('id', $id)->update(['calculation_snapshot' => json_encode($snapshot)]);
        $reader = app(Pro9SourceReader::class); $first = $reader->snapshot($kind, $id);
        $this->assertSame('2.333333333333', $first['amounts']['total']);
        $this->assertSame(\App\Support\Exact\Decimal::fraction('7/3'), $first['exact']['total']);
        $this->assertSame(\App\Support\Exact\Decimal::fraction('13/7'), $first['exact']['exchange_rate_sale']);
        $document = $reader->receive($kind, $id, $this->admin->id)['data']; $revision = $document->source_revision;
        $beforeColumns = (array) DB::table($table)->where('id', $id)->first(['total', 'total_value', 'total_igv', 'total_taxes', 'exchange_rate_sale', 'updated_at']);
        $snapshot['values']['total'] = \App\Support\Exact\Decimal::fraction('8/3');
        DB::table($table)->where('id', $id)->update(['calculation_snapshot' => json_encode($snapshot)]);
        $second = $reader->snapshot($kind, $id); $this->assertNotSame($first['hash'], $second['hash']);
        $received = $reader->receive($kind, $id, $this->admin->id);
        $this->assertSame($revision + 1, $received['data']->source_revision);
        $this->assertSame($beforeColumns, (array) DB::table($table)->where('id', $id)->first(['total', 'total_value', 'total_igv', 'total_taxes', 'exchange_rate_sale', 'updated_at']));
        $this->assertSame('2.666666666667', $received['data']->amounts['total']);
        $version = SourceVersion::where('source_document_id', $document->id)->where('revision', $revision)->firstOrFail();
        $this->assertSame('7', $version->snapshot['exact']['total']['n']);
        $this->assertSame('3', $version->snapshot['exact']['total']['d']);
        $this->assertSame('verified', app(SourceHistoryService::class)->integrity($version)['state']);
    }

    public function test_legacy_source_shapes_remain_readable_without_inventing_amounts_or_rewriting_evidence(): void
    {
        $config = $this->invoiceConfiguration(); $document = app(DocumentService::class)->create($config['input'], $this->admin->id);
        $snapshots = ['Estructura histórica incompleta', ['amounts' => 'No conservado', 'party_snapshot' => 'No conservado'], ['metadata' => 'No conservado', 'amounts' => ['total' => 1160]]];
        foreach ($snapshots as $offset => $snapshot) {
            $row = SourceVersion::create(['source_document_id' => $document->id, 'revision' => $offset + 2, 'snapshot' => $snapshot, 'hash' => str_repeat('1', 64)]);
            $raw = $row->fresh()->getRawOriginal('snapshot');
            $detail = app(SourceHistoryService::class)->show($document->id, $row->id);
            $this->assertSame('legacy', $detail['integrity']['state']);
            $this->assertNull($detail['summary']['amounts']['total']);
            $this->assertNull($detail['summary']['party']);
            $this->assertSame($raw, $row->fresh()->getRawOriginal('snapshot'));
        }
    }

    private function nativeSourceFixture(): array
    {
        $config = $this->invoiceConfiguration();
        DB::table('accounting_memberships')->where('company_id', $this->a->id)->where('user_id', $this->admin->id)->update(['roles' => json_encode(['admin', 'accountant'])]);
        $native = DB::table('companies')->insertGetId(['name' => 'Empresa nativa ficticia', 'number' => $this->a->tax_id]);
        $this->a->settings = [...$this->a->settings ?? [], 'source_company_id' => $native]; $this->a->save();
        app(CompanyContext::class)->set($this->a->fresh());
        $input = $config['input']; $party = \Modules\Accounting\Models\ThirdParty::findOrFail($input['third_party_id']);
        $id = DB::table('documents')->insertGetId(['series' => 'ACCT-HISTORY', 'number' => random_int(1, 10000000), 'document_type_id' => '01', 'date_of_issue' => $input['issue_date'], 'state_type_id' => '01', 'currency_type_id' => 'VES',
            'customer' => json_encode(['name' => $party->name, 'number' => $party->tax_id]), 'total' => $input['amounts']['total'], 'total_value' => $input['amounts']['base'], 'total_igv' => $input['amounts']['vat'], 'total_taxes' => $input['amounts']['vat'], 'exchange_rate_sale' => '1', 'created_at' => now(), 'updated_at' => now()]);
        $received = app(Pro9SourceReader::class)->receive('sales', $id, $this->admin->id);
        $document = $received['data'];
        return compact('id', 'document', 'config');
    }

    public function test_source_history_v2_verifies_after_mysql_json_normalization_and_preserves_actor_identity(): void
    {
        $config = $this->invoiceConfiguration(); $doc = app(DocumentService::class)->create($config['input'], $this->admin->id);
        $history = app(SourceHistoryService::class); $row = SourceVersion::where('source_document_id', $doc->id)->firstOrFail();
        $this->assertSame('verified', $history->integrity($row)['state']);
        $actor = $row->recorded_by_name; DB::table('users')->where('id', $this->admin->id)->update(['name' => 'Otro nombre ficticio posterior']);
        $detail = $history->show($doc->id, $row->id);
        $this->assertSame($actor, $detail['recorded_by_name']);
        $this->assertIsString($detail['origin_snapshot_json']);
        $this->assertIsString($detail['document_snapshot_json']);
        $this->assertSame('verified', $detail['integrity']['state']);
        $this->assertSame('verified', app(AuditIntegrityService::class)->verify()['state']);
    }

    public function test_source_history_rejects_modified_content_without_rewriting_legacy_rows(): void
    {
        $config = $this->invoiceConfiguration(); $doc = app(DocumentService::class)->create($config['input'], $this->admin->id);
        $history = app(SourceHistoryService::class); $row = SourceVersion::where('source_document_id', $doc->id)->firstOrFail();
        // An in-memory forged copy simulates a corrupted restore; immutable SQL
        // protections stay enabled throughout the test.
        $attributes = $row->getRawOriginal(); $data = json_decode($attributes['snapshot'], true); $data['number'] = 'CONTENIDO-CAMBIADO'; $attributes['snapshot'] = json_encode($data);
        $forged = new SourceVersion(); $forged->setRawAttributes($attributes, true);
        $this->assertSame('failed', $history->integrity($forged)['state']);
        $legacy = SourceVersion::create(['source_document_id' => $doc->id, 'revision' => 2, 'snapshot' => ['preservado' => '9007199254740993.12345678'], 'hash' => str_repeat('1', 64)]);
        $this->assertSame('legacy', $history->integrity($legacy)['state']);
        $this->assertSame(str_repeat('1', 64), $legacy->fresh()->hash);
        try { DB::table('accounting_source_versions')->where('id', $row->id)->update(['hash' => str_repeat('0', 64)]); $this->fail('El antecedente no admite UPDATE.'); }
        catch (\Illuminate\Database\QueryException $error) { $this->assertStringContainsString('inmutable', strtolower($error->getMessage())); }
    }

    public function test_source_history_is_scoped_to_both_the_document_and_company(): void
    {
        $config = $this->invoiceConfiguration(); $documents = app(DocumentService::class);
        $a = $documents->create($config['input'], $this->admin->id); $b = $documents->create([...$config['input'], 'number' => 'FACTURA-DISTINTA'], $this->admin->id);
        $version = SourceVersion::where('source_document_id', $a->id)->firstOrFail();
        try { app(SourceHistoryService::class)->show($b->id, $version->id); $this->fail('No se intercambian versiones entre documentos.'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { $this->assertTrue(true); }
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(CompanyContext::class)->run($this->b, fn() => app(SourceHistoryService::class)->show($a->id, $version->id));
    }

    public function test_receiving_changed_pro9_after_posting_preserves_every_registered_document_and_ledger_field(): void
    {
        $fixture = $this->nativeSourceFixture(); $doc = $fixture['document']; $documents = app(DocumentService::class);
        $doc = $documents->validate($doc->id, $doc->version, ['source_confirmed' => true, 'tax_components' => $fixture['config']['input']['tax_components'] ?? []], $this->admin->id);
        $entry = $documents->propose($doc->id, $doc->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id);
        $entry = $this->postPrepared($entry, 'native-source-original-' . Str::uuid()); $doc->refresh();
        $before = (array) DB::table('accounting_source_documents')->where('id', $doc->id)->first();
        $entryBefore = (array) DB::table('accounting_journal_entries')->where('id', $entry->id)->first();
        DB::table('documents')->where('id', $fixture['id'])->update(['total' => '2320', 'total_value' => '2000', 'total_igv' => '320', 'total_taxes' => '320', 'updated_at' => now()->addSecond()]);
        $result = app(Pro9SourceReader::class)->receive('sales', $fixture['id'], $this->admin->id);
        $this->assertTrue($result['requires_reconciliation']); $this->assertSame(2, $result['observed_revision']);
        $this->assertSame($before, (array) DB::table('accounting_source_documents')->where('id', $doc->id)->first());
        $this->assertSame($entryBefore, (array) DB::table('accounting_journal_entries')->where('id', $entry->id)->first());
        $this->assertSame(1, Incident::where('resource_id', $doc->id)->count());
        $version = SourceVersion::where('source_document_id', $doc->id)->where('revision', 2)->firstOrFail();
        $this->assertSame('verified', app(SourceHistoryService::class)->integrity($version)['state']);
        $this->assertSame('pending_reconciliation', $version->disposition);
        $this->assertSame(1, $version->document_snapshot['source_revision']);
        $again = app(Pro9SourceReader::class)->receive('sales', $fixture['id'], $this->admin->id);
        $this->assertTrue($again['unchanged']); $this->assertSame(2, SourceVersion::where('source_document_id', $doc->id)->count());
        $this->assertSame(1, Incident::where('resource_id', $doc->id)->count());
    }

    public function test_new_unposted_native_revision_still_invalidates_pending_approval_without_duplicate_receipts(): void
    {
        $fixture = $this->nativeSourceFixture(); $doc = $fixture['document']; $reader = app(Pro9SourceReader::class);
        $documents = app(DocumentService::class);
        $doc = $documents->validate($doc->id, $doc->version, ['source_confirmed' => true, 'tax_components' => $fixture['config']['input']['tax_components']], $this->admin->id);
        $entry = $documents->propose($doc->id, $doc->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id);
        $entries = app(\Modules\Accounting\Services\EntryService::class);
        $entry = $entries->transition($entry->id, $entry->version, $this->admin->id, 'submit');
        $entry = $entries->transition($entry->id, $entry->version, $this->admin->id, 'approve', 'Autocontrol explícito del caso ficticio de cambio de origen.');
        $entryVersion = $entry->version;
        $this->assertTrue($reader->receive('sales', $fixture['id'], $this->admin->id)['unchanged']);
        DB::table('documents')->where('id', $fixture['id'])->update(['state_type_id' => '09', 'updated_at' => now()->addSecond()]);
        $result = $reader->receive('sales', $fixture['id'], $this->admin->id);
        $this->assertFalse($result['unchanged']); $this->assertSame(2, $doc->fresh()->source_revision);
        $this->assertSame('09', $doc->fresh()->metadata['source_state']);
        $this->assertSame('observed', $entry->fresh()->status); $this->assertNull($entry->fresh()->approved_by); $this->assertSame($entryVersion + 1, $entry->fresh()->version);
        $this->assertTrue($reader->receive('sales', $fixture['id'], $this->admin->id)['unchanged']);
        $this->assertSame(2, SourceVersion::where('source_document_id', $doc->id)->count());
        try { $reader->assertCurrent($doc->fresh()); $this->fail('No se valida como vigente el origen anulado.'); }
        catch (ApiProblem $e) { $this->assertSame('PRO9_SOURCE_NOT_ACTIVE', $e->errorCode); }
    }

    public function test_source_integrity_failure_in_restored_data_blocks_fiscal_validation(): void
    {
        $fixture = $this->invoiceConfiguration(); $documents = app(DocumentService::class); $doc = $documents->create($fixture['input'], $this->admin->id);
        SourceVersion::create(['source_document_id' => $doc->id, 'revision' => 2, 'snapshot' => $doc->toArray(), 'hash' => str_repeat('1', 64), 'integrity_version' => 2, 'integrity_hash' => str_repeat('0', 64)]);
        $doc->source_revision = 2; $doc->save();
        try { $documents->validate($doc->id, $doc->version, [], $this->admin->id); $this->fail('Un antecedente corrupto no respalda la validación.'); }
        catch (ApiProblem $e) { $this->assertSame('SOURCE_HISTORY_INTEGRITY', $e->errorCode); }
        $this->assertSame('draft', $doc->fresh()->status);
    }

    public function test_source_integrity_migration_cannot_erase_existing_verifiable_history(): void
    {
        $fixture = $this->invoiceConfiguration(); app(DocumentService::class)->create($fixture['input'], $this->admin->id);
        $migration = require dirname(__DIR__) . '/Database/Migrations/2026_11_12_001300_preserve_source_version_integrity.php';
        $this->expectException(\RuntimeException::class); $this->expectExceptionMessage('eliminar su evidencia');
        $migration->down();
    }
}
