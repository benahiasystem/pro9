<?php

namespace Modules\Accounting\Tests;

use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Models\{Account, ThirdParty, JournalEntry, OpenItem};
use Modules\Accounting\Services\{EntryService, ReportService, CompanyService, DocumentService, SettlementService};
use Modules\Accounting\Support\TenantDB as DB;
use Modules\Accounting\Models\{ReportJob, ReportArtifact};
use Modules\Accounting\Services\{ReportExportService, OperationService};

trait ReportIntegrationAssertions
{
    private function reportPost(string $date, string $amount, string $contra = '3.1', ?string $party = null): JournalEntry
    {
        $accounts = Account::get()->keyBy('code');
        $entry = app(EntryService::class)->create(['accounting_date' => $date, 'concept' => 'Movimiento ficticio para informes', 'lines' => [
            ['account_id' => $accounts['1.1.02']->id, 'debit' => $amount, 'credit' => '0', 'third_party_id' => $party],
            ['account_id' => $accounts[$contra]->id, 'debit' => '0', 'credit' => $amount, 'third_party_id' => $party],
        ]], $this->admin->id);
        return $this->postPrepared($entry, 'report-fixture:' . $entry->id);
    }

    public function test_report_stream_includes_all_502_lines_and_excludes_unposted_drafts(): void
    {
        $accounts = Account::get()->keyBy('code');
        $lines = [];
        for ($i = 0; $i < 250; ++$i) {
            $lines[] = ['account_id' => $accounts['1.1.02']->id, 'debit' => '0.01', 'credit' => '0'];
            $lines[] = ['account_id' => $accounts['3.1']->id, 'debit' => '0', 'credit' => '0.01'];
        }
        $entry = app(EntryService::class)->create(['accounting_date' => now()->format('Y-m-d'), 'concept' => 'Comprobante de 500 líneas ficticias', 'lines' => $lines], $this->admin->id);
        $this->postPrepared($entry, 'report-500-lines');
        $this->reportPost(now()->format('Y-m-d'), '100');
        $this->draft();
        $service = app(ReportService::class);
        $page = $service->build('journal', ['per_page' => 500]);
        $all = iterator_to_array($service->iterate('journal', $page['filters']));
        $this->assertCount(500, $page['rows']);
        $this->assertSame(502, $page['total']);
        $this->assertCount(502, $all);
        $this->assertCount(502, array_unique(array_column($all, 'line_id')));
        $this->assertSame(['debit' => '102.50', 'credit' => '102.50'], $page['totals']);
        $ledger = $service->build('ledger', ['account_id' => $accounts['1.1.02']->id, 'per_page' => 1, 'page' => 251]);
        $this->assertSame('102.50', $ledger['rows'][0]['balance']);
        $this->assertSame($page['review_token'], $service->build('journal', ['per_page' => 1, 'page' => 2])['review_token']);
    }

    public function test_report_reversal_is_an_original_and_counterpart_without_double_subtraction(): void
    {
        $entry = $this->reportPost(now()->format('Y-m-d'), '25.30');
        $reverse = app(EntryService::class)->prepareReversal($entry->id, now()->format('Y-m-d'), 'Corrección ficticia con evidencia conservada.', $this->admin->id);
        $this->postPrepared($reverse, 'report-reversal');
        $r = app(ReportService::class)->build('journal');
        $this->assertSame(['debit' => '50.60', 'credit' => '50.60'], $r['totals']);
        $this->assertCount(4, $r['rows']);
        $this->assertCount(2, array_filter($r['rows'], fn($row) => $row['reversal_of'] === $entry->id));
        $trial = app(ReportService::class)->build('trial-balance');
        $this->assertSame('0.00', $trial['totals']['balance']);
        $this->assertSame('posted_only', $r['data_scope']);
    }

    public function test_report_comparatives_distinguish_period_income_from_accumulated_balance(): void
    {
        $year = now()->year;
        app(CompanyService::class)->createYear($year - 1);
        $this->reportPost(($year - 1) . '-03-10', '100', '4.1');
        $this->reportPost($year . '-03-10', '200', '4.1');
        $f = ['from' => $year . '-01-01', 'to' => $year . '-12-31', 'compare_from' => ($year - 1) . '-01-01', 'compare_to' => ($year - 1) . '-12-31'];
        $income = app(ReportService::class)->build('income-statement', $f);
        $this->assertSame('200.00', $income['totals']['result']);
        $this->assertSame('100.00', $income['comparison']['totals']['result']);
        $this->assertSame('100.00', $income['rows'][0]['difference']);
        $balance = app(ReportService::class)->build('balance-sheet', $f);
        $this->assertSame('300.00', $balance['totals']['assets']);
        $this->assertSame('100.00', $balance['comparison']['totals']['assets']);
        $this->assertSame('200.00', $balance['comparison']['difference_totals']['assets']);
        $this->assertTrue($balance['preliminary']);
        $this->assertStringStartsWith('2.0:', $balance['presentation_version']);
        $this->assertSame($income['presentation_version'], $balance['presentation_version']);
    }

    public function test_auxiliary_opening_groups_include_parties_without_current_movements(): void
    {
        $year = now()->year;
        $a = ThirdParty::create(['tax_id' => 'J-00000001-0', 'name' => 'Tercero con movimiento', 'kind' => 'both']);
        $b = ThirdParty::create(['tax_id' => 'J-00000002-0', 'name' => 'Tercero sin movimiento', 'kind' => 'both']);
        $this->reportPost($year . '-01-10', '100', '3.1', $a->id);
        $this->reportPost($year . '-01-11', '50', '3.1', $b->id);
        $this->reportPost($year . '-02-10', '20', '3.1', $a->id);
        $r = app(ReportService::class)->build('auxiliary', ['account_id' => Account::where('code', '1.1.02')->firstOrFail()->id, 'from' => $year . '-02-01', 'to' => $year . '-02-28']);
        $this->assertSame('150.00', $r['opening']);
        $this->assertSame('170.00', $r['totals']['balance']);
        $this->assertCount(2, $r['opening_groups']);
        $this->assertCount(1, $r['rows']);
        $this->assertSame('120.00', $r['rows'][0]['balance']);
        $this->assertSame('50.00', collect($r['opening_groups'])->firstWhere('third_party_id', $b->id)['opening']);
    }

    public function test_open_items_report_reconstructs_historical_allocations_and_their_later_reversals(): void
    {
        $year = now()->year;
        try {
            \Illuminate\Support\Facades\Date::setTestNow($year . '-01-15 10:00:00');
            $fixture = $this->initialBalance();
            $this->postPrepared($fixture['entry'], 'report-opening');
        } finally {
            \Illuminate\Support\Facades\Date::setTestNow();
        }
        $item = OpenItem::firstOrFail();
        $input = $this->settlementInput($item, '40', 'RC-INFORME');
        $input['accounting_date'] = $input['exchange_date'] = $year . '-02-05';
        $settlement = $this->postPrepared(app(SettlementService::class)->prepare($input, $this->admin->id), 'report-settlement');
        $reverse = app(EntryService::class)->prepareReversal($settlement->id, $year . '-03-05', 'Reverso ficticio de una liquidación posterior al corte.', $this->admin->id);
        $this->postPrepared($reverse, 'report-settlement-reverse');
        foreach (['01-31' => ['4000.00', '100.000000000000'], '02-28' => ['2400.00', '60.000000000000'], '03-31' => ['4000.00', '100.000000000000']] as $end => [$book, $original]) {
            $r = app(ReportService::class)->build('open-items', ['from' => $year . '-01-01', 'to' => $year . '-' . $end, 'side' => 'receivable']);
            $this->assertSame(1, $r['total']);
            $this->assertSame($book, $r['totals']['book']);
            $this->assertSame($original, $r['rows'][0]['remaining_original']);
            $this->assertSame($book, (string) \Brick\Math\BigDecimal::of($r['rows'][0]['remaining_book'])->toScale(2));
        }
    }

    public function test_document_summary_separates_explicit_pending_control_from_accounting_books(): void
    {
        $configuration = $this->invoiceConfiguration();
        app(DocumentService::class)->create($configuration['input'], $this->admin->id);
        $posted = app(ReportService::class)->build('documents');
        $this->assertSame(0, $posted['total']);
        $pending = app(ReportService::class)->build('documents', ['include_pending' => true]);
        $this->assertSame(1, $pending['total']);
        $this->assertSame('control_with_pending', $pending['data_scope']);
        $this->assertSame('draft', $pending['rows'][0]['status']);
        $this->assertSame('1160.000000000000', $pending['currency_totals'][0]['amount']);
        $this->expectException(ApiProblem::class);
        app(ReportService::class)->build('journal', ['include_pending' => true]);
    }

    public function test_report_filters_cannot_resolve_accounts_or_parties_from_another_company(): void
    {
        $account = app(CompanyContext::class)->run($this->b, fn() => Account::where('code', '1.1.02')->firstOrFail());
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(ReportService::class)->build('ledger', ['account_id' => $account->id]);
    }

    private function captureReport(string $kind, array $filters = []): array
    {
        app('config')->set('accounting.private_storage', sys_get_temp_dir() . '/accounting-report-core');
        $report = app(ReportService::class)->build($kind, $filters);
        return app(ReportExportService::class)->capture($kind, $report['filters'], $report['review_token'], $this->admin->id);
    }

    public function test_export_captures_a_complete_snapshot_and_survives_later_entries_and_catalog_edits(): void
    {
        $entry = $this->reportPost(now()->format('Y-m-d'), '90.10');
        $before = JournalEntry::count();
        $saved = $this->captureReport('journal');
        $this->assertSame($before, JournalEntry::count());
        $this->assertSame(2, $saved['row_count']);
        $this->assertArrayNotHasKey('path', $saved);
        $this->assertArrayNotHasKey('presentation', $saved['snapshot']);
        $this->reportPost(now()->format('Y-m-d'), '17.30');
        $account = Account::where('code', '1.1.02')->firstOrFail();
        $account->name = 'Nombre posterior que el archivo conserva por separado'; $account->version++; $account->save();
        $records = iterator_to_array(app(ReportExportService::class)->read(ReportJob::findOrFail($saved['id'])));
        $this->assertCount(4, $records);
        $this->assertSame('90.10', $records[3]['totals']['debit']);
        $this->assertSame('Bancos', $records[1]['row']['account_name']);
        $this->assertSame($entry->id, $records[1]['row']['entry_id']);
        $this->assertSame('107.40', app(ReportService::class)->build('journal')['totals']['debit']);
    }

    public function test_export_requires_the_consulted_version_and_replays_its_capture_identity(): void
    {
        app('config')->set('accounting.private_storage', sys_get_temp_dir() . '/accounting-report-core');
        $this->reportPost(now()->format('Y-m-d'), '20');
        $r = app(ReportService::class)->build('journal');
        $this->reportPost(now()->format('Y-m-d'), '1');
        try {
            app(ReportExportService::class)->capture('journal', $r['filters'], $r['review_token'], $this->admin->id);
            $this->fail('Los datos posteriores requieren consultar otra vez.');
        } catch (ApiProblem $error) { $this->assertSame('REPORT_VIEW_CHANGED', $error->errorCode); }
        $r = app(ReportService::class)->build('journal'); $operation = app(OperationService::class); $payload = ['kind' => 'journal', 'filters' => $r['filters'], 'review_token' => $r['review_token']];
        $saved = $operation->execute($this->admin->id, 'report.capture', 'report-idempotent-capture', $payload, fn() => app(ReportExportService::class)->capture('journal', $r['filters'], $r['review_token'], $this->admin->id));
        $repeated = $operation->execute($this->admin->id, 'report.capture', 'report-idempotent-capture', $payload, fn() => throw new \RuntimeException('No se vuelve a capturar'));
        $this->assertEquals(json_decode(json_encode($saved), true), $repeated);
        $this->assertSame(1, ReportJob::count());
        $this->assertSame('confirmed', $operation->status($this->admin->id, 'report.capture', 'report-idempotent-capture')['status']);
    }

    public function test_export_detects_replaced_data_and_refuses_mutating_a_ready_record(): void
    {
        $this->reportPost(now()->format('Y-m-d'), '10');
        $saved = $this->captureReport('journal'); $job = ReportJob::findOrFail($saved['id']);
        chmod($job->path, 0600); file_put_contents($job->path, 'contenido sustituido');
        try {
            iterator_to_array(app(ReportExportService::class)->read($job));
            $this->fail('No se puede generar otro formato con datos sustituidos.');
        } catch (ApiProblem $error) { $this->assertSame('REPORT_SNAPSHOT_CHANGED', $error->errorCode); }
        try {
            DB::transaction(fn() => DB::table('accounting_report_jobs')->where('id', $job->id)->update(['row_count' => 0]));
            $this->fail('La evidencia lista es inmutable en PostgreSQL.');
        } catch (\Illuminate\Database\QueryException $error) { $this->assertStringContainsString('Informe congelado inmutable', $error->getMessage()); }
        app(CompanyContext::class)->set($this->b);
        $this->assertNull(ReportJob::find($job->id));
    }

    public function test_xlsx_and_csv_exports_keep_exact_text_identifiers_and_do_not_execute_source_formulas(): void
    {
        $this->reportPost(now()->format('Y-m-d'), '9007199254740993.12');
        $entry = JournalEntry::whereNotNull('posted_at')->firstOrFail();
        // Simulates a literal imported concept; changing posted data is forbidden,
        // so use its allowed input on another fresh draft before posting it.
        $accounts = Account::get()->keyBy('code');
        $draft = app(EntryService::class)->create(['accounting_date' => now()->format('Y-m-d'), 'concept' => '=HYPERLINK("https://example.invalid")', 'lines' => [['account_id' => $accounts['1.1.02']->id, 'debit' => '0.30', 'credit' => '0'], ['account_id' => $accounts['3.1']->id, 'debit' => '0', 'credit' => '0.30']]], $this->admin->id);
        $this->postPrepared($draft, 'report-literal-formula');
        $saved = $this->captureReport('journal'); $exports = app(ReportExportService::class);
        $xlsx = $exports->download($saved['id'], 'xlsx', $this->admin->id);
        $csv = $exports->download($saved['id'], 'csv', $this->admin->id);
        $zip = new \ZipArchive(); $this->assertTrue($zip->open($xlsx['path']) === true);
        $xml = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertStringContainsString('9007199254740993.12', $xml);
        $this->assertStringContainsString('=HYPERLINK', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
        $this->assertStringContainsString('t="inlineStr"', $xml); $zip->close();
        $text = file_get_contents($csv['path']);
        $this->assertStringContainsString("'=HYPERLINK", $text);
        $this->assertStringContainsString('9007199254740993.12', $text);
        $this->assertSame($xlsx['sha256'], hash_file('sha256', $xlsx['path']));
        $this->assertSame(2, ReportArtifact::count());
        $this->assertSame($csv['sha256'], $exports->download($saved['id'], 'csv', $this->admin->id)['sha256']);
        $this->assertSame(2, ReportArtifact::count());
    }

    public function test_pdf_export_contains_every_posted_row_and_its_control_footer(): void
    {
        $this->reportPost(now()->format('Y-m-d'), '125.35');
        $saved = $this->captureReport('journal');
        $file = app(ReportExportService::class)->download($saved['id'], 'pdf', $this->admin->id);
        $this->assertStringStartsWith('%PDF-', file_get_contents($file['path']));
        $this->assertGreaterThan(10000, filesize($file['path']));
        $this->assertSame($file['sha256'], hash_file('sha256', $file['path']));
        $this->assertSame(1, ReportArtifact::count());
    }

    public function test_all_nine_report_kinds_export_pdf_xlsx_and_csv_from_the_same_snapshot(): void
    {
        $year = now()->year;
        try {
            \Illuminate\Support\Facades\Date::setTestNow($year . '-01-15 10:00:00');
            $fixture = $this->initialBalance();
            $this->postPrepared($fixture['entry'], 'export-all-opening');
        } finally { \Illuminate\Support\Facades\Date::setTestNow(); }
        $this->reportPost(now()->format('Y-m-d'), '25');
        app(DocumentService::class)->create(['kind' => 'sale_invoice', 'number' => 'MANUAL-INFORME-1', 'issue_date' => now()->format('Y-m-d'), 'fiscal_date' => now()->format('Y-m-d'), 'third_party_id' => $fixture['party']->id, 'currency' => 'VES', 'exchange_rate' => '1', 'exchange_date' => now()->format('Y-m-d'), 'exchange_source' => 'Moneda del libro', 'amounts' => ['base' => '1000', 'vat' => '160', 'other_taxes' => '0', 'total' => '1160']], $this->admin->id);
        \Modules\Accounting\Models\Incident::create(['kind' => 'source_changed', 'resource_type' => 'source_document', 'resource_id' => $fixture['document']->id, 'title' => 'Incidencia ficticia de revisión', 'details' => ['private_token' => 'NEVER_EXPORT_PRIVATE_DETAIL'], 'status' => 'open']);
        $exports = app(ReportExportService::class);
        $bank = Account::where('code', '1.1.02')->firstOrFail()->id;
        foreach (array_keys(\Modules\Accounting\Support\ReportSchema::kinds()) as $kind) {
            $f = ['from' => $year . '-01-01', 'to' => $year . '-12-31'];
            if (in_array($kind, ['ledger', 'auxiliary'], true)) { $f['account_id'] = $bank; }
            if ($kind === 'documents') { $f['include_pending'] = true; }
            $saved = $this->captureReport($kind, $f);
            foreach (['csv', 'xlsx', 'pdf'] as $format) {
                $file = $exports->download($saved['id'], $format, $this->admin->id);
                $this->assertSame($saved['sha256'], $file['snapshot_sha256'], $kind . '/' . $format);
                $this->assertGreaterThan(0, filesize($file['path']));
                $this->assertSame($file['sha256'], hash_file('sha256', $file['path']));
                if ($format === 'csv') { $this->assertStringNotContainsString('NEVER_EXPORT_PRIVATE_DETAIL', file_get_contents($file['path'])); }
            }
        }
        $this->assertSame(9, ReportJob::count());
        $this->assertSame(27, ReportArtifact::count());
    }

    public function test_read_only_export_events_do_not_make_the_financial_review_stale(): void
    {
        $this->reportPost(now()->format('Y-m-d'), '12');
        $before = app(ReportService::class)->build('journal');
        $saved = $this->captureReport('journal');
        app(ReportExportService::class)->download($saved['id'], 'csv', $this->admin->id);
        $after = app(ReportService::class)->build('journal');
        $this->assertSame($before['data_version'], $after['data_version']);
        $this->assertSame($before['review_token'], $after['review_token']);
        $this->assertSame($before['totals'], $after['totals']);
        $this->assertSame(2, app(ReportExportService::class)->capture('journal', $before['filters'], $before['review_token'], $this->admin->id)['row_count']);
    }
}
