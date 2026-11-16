<?php

namespace Modules\Accounting\Tests;

use Modules\Accounting\Models\{ImportJob, ImportUnit, ImportReceipt, JournalEntry, ThirdParty, Account, SourceDocument, OpenItem};
use Modules\Accounting\Domain\{CompanyContext, ApiProblem};
use Modules\Accounting\Support\{ImportFileReader, ImportSchema, ImportWorkbook};
use Modules\Accounting\Services\{ImportService, OperationService};
use Modules\Accounting\Support\TenantDB as DB;

trait ImportIntegrationAssertions
{
    private function importFile(string $kind, array $data, ?string $filename = null): array
    {
        app('config')->set('accounting.private_storage', sys_get_temp_dir() . '/accounting-import-core');
        DB::table('accounting_memberships')->where('company_id', app(CompanyContext::class)->id())->where('user_id', $this->admin->id)->update(['roles' => json_encode(['admin', 'accountant'])]);
        $headers = array_keys(ImportSchema::columns($kind));
        $stream = tmpfile();
        fputcsv($stream, $headers, ';', '"', '');
        foreach ($data as $row) {
            fputcsv($stream, array_map(fn($key) => (string) ($row[$key] ?? ''), $headers), ';', '"', '');
        }
        $path = stream_get_meta_data($stream)['uri'];
        fflush($stream);
        try {
            $rows = (new ImportFileReader())->read($path, $kind, 'csv');
            return app(ImportService::class)->preview($kind, $rows, hash_file('sha256', $path), $filename ?? 'archivo-ficticio.csv', ['format' => 'csv', 'delimiter' => ';', 'decimal_mark' => '.'], $this->admin->id);
        } finally {
            fclose($stream);
        }
    }

    private function importLine(string $reference, string $account, string $debit, string $credit, array $extra = []): array
    {
        return array_replace(['empresa_rif' => $this->a->tax_id, 'referencia_importacion' => $reference, 'fecha_contable' => now()->format('Y-m-d'), 'concepto' => 'Comprobante ficticio importado', 'cuenta' => $account, 'debe_ves' => $debit, 'haber_ves' => $credit], $extra);
    }

    private function importApply(array $job, ?array $units = null): array
    {
        $units ??= array_column(array_values(array_filter($job['units'], fn($unit) => $unit['status'] === 'valid')), 'id');
        return app(ImportService::class)->apply($job['id'], $job['version'], $job['preview_hash'], $units, $this->admin->id);
    }

    public function test_import_preview_has_no_domain_side_effects_and_rejects_the_entire_unbalanced_entry(): void
    {
        $before = [JournalEntry::count(), \Modules\Accounting\Models\JournalLine::count(), DB::table('accounting_audit_events')->count()];
        $job = $this->importFile('entries', [$this->importLine('ok', '1.1.02', '0.30', '0'), $this->importLine('ok', '3.1', '0', '0.30'), $this->importLine('bad', '1.1.02', '10', '0'), $this->importLine('bad', '3.1', '0', '9')]);
        $this->assertSame([2, 1, 1, 0, 0], array_values($job['summary']));
        $this->assertSame($before[0], JournalEntry::count());
        $this->assertSame($before[1], \Modules\Accounting\Models\JournalLine::count());
        $this->assertSame($before[2] + 1, DB::table('accounting_audit_events')->count());
        $this->assertSame('UNBALANCED_ENTRY', $job['units'][1]['errors'][0]['code']);
        $this->assertSame([4, 5], $job['units'][1]['rows']);
        $applied = $this->importApply($job);
        $this->assertSame('partial', $applied['status']);
        $this->assertSame($before[0] + 1, JournalEntry::count());
        $this->assertSame($before[1] + 2, \Modules\Accounting\Models\JournalLine::count());
        $entry = JournalEntry::findOrFail($applied['units'][0]['resource']['id']);
        $this->assertNull($entry->posted_at);
        $this->assertSame('0.30000000', $entry->total_debit);
        $this->assertSame(1, ImportReceipt::count());
    }

    public function test_import_receipts_survive_new_files_and_preserve_edited_records(): void
    {
        $rows = [$this->importLine('stable', '1.1.02', '12', '0'), $this->importLine('stable', '3.1', '0', '12')];
        $first = $this->importFile('entries', $rows);
        $accepted = $this->importApply($first);
        $id = $accepted['units'][0]['resource']['id'];
        $entry = JournalEntry::findOrFail($id);
        $entry->concept = 'Edición posterior que la importación conserva';
        $entry->version++;
        $entry->save();
        $rows[] = $this->importLine('another', '1.1.02', '8', '0');
        $rows[] = $this->importLine('another', '3.1', '0', '8');
        $next = $this->importFile('entries', $rows);
        $this->assertSame('already_imported', $next['units'][0]['status']);
        $this->assertSame($id, $next['units'][0]['resource']['id']);
        $this->assertSame('Edición posterior que la importación conserva', JournalEntry::findOrFail($id)->concept);
        $this->assertSame('complete', $this->importApply($next)['status']);
        $this->assertSame(2, JournalEntry::count());
        $rows[0]['debe_ves'] = '13';
        $changed = $this->importFile('entries', $rows);
        $this->assertSame('IMPORT_REFERENCE_CONFLICT', $changed['units'][0]['errors'][0]['code']);
    }

    public function test_import_rechecks_catalogs_at_acceptance_and_keeps_other_valid_units(): void
    {
        $extra = Account::create(['code' => '1.1.99', 'name' => 'Cuenta de prueba', 'kind' => 'asset', 'nature' => 'debit', 'posting' => true, 'active' => true]);
        $job = $this->importFile('entries', [$this->importLine('A', '1.1.02', '10', '0'), $this->importLine('A', '3.1', '0', '10'), $this->importLine('B', '1.1.99', '20', '0'), $this->importLine('B', '3.1', '0', '20')]);
        $this->assertSame(2, $job['summary']['valid']);
        $extra->active = false;
        $extra->save();
        $applied = $this->importApply($job);
        $this->assertSame(1, $applied['summary']['imported']);
        $this->assertSame('NON_POSTING_ACCOUNT', $applied['units'][1]['errors'][0]['code']);
        $this->assertSame(4, $applied['units'][1]['errors'][0]['row']);
        $this->assertSame('cuenta', $applied['units'][1]['errors'][0]['field']);
        $extra->active = true;
        $extra->save();
        $again = app(ImportService::class)->revalidate($job['id'], $applied['version'], $applied['preview_hash'], $this->admin->id);
        $this->assertSame(1, $again['summary']['valid']);
        $this->assertSame(1, $again['summary']['imported']);
        $this->assertSame('complete', $this->importApply($again)['status']);
    }

    public function test_import_enforces_company_rif_and_scopes_receipts_and_rows(): void
    {
        $job = $this->importFile('entries', [$this->importLine('A', '1.1.02', '1', '0', ['empresa_rif' => $this->b->tax_id]), $this->importLine('A', '3.1', '0', '1')]);
        $this->assertSame('IMPORT_WRONG_COMPANY', $job['units'][0]['errors'][0]['code']);
        app(CompanyContext::class)->set($this->b);
        $this->assertNull(ImportJob::find($job['id']));
        $this->assertSame(0, ImportUnit::count());
        $this->assertSame(0, ImportReceipt::count());
    }

    public function test_import_error_workbook_roundtrips_complete_units_as_safe_text(): void
    {
        $rows = [$this->importLine('bad', '1.1.02', '3', '0', ['concepto' => '=HYPERLINK("https://example.invalid")']), $this->importLine('bad', '3.1', '0', '2', ['concepto' => '=HYPERLINK("https://example.invalid")'])];
        $job = $this->importFile('entries', $rows);
        $bytes = app(ImportWorkbook::class)->errors(ImportJob::findOrFail($job['id']));
        $file = tmpfile();
        fwrite($file, $bytes);
        fflush($file);
        try {
            $restored = (new ImportFileReader())->read(stream_get_meta_data($file)['uri'], 'entries', 'xlsx');
            $this->assertCount(2, $restored);
            $this->assertSame($rows[0]['concepto'], $restored[0]['data']['concepto']);
            $this->assertSame('bad', $restored[1]['data']['referencia_importacion']);
            $this->assertSame([], $restored[1]['errors']);
        } finally {
            fclose($file);
        }
    }

    public function test_import_all_four_xlsx_templates_are_readable_and_contain_no_sample_operations(): void
    {
        foreach (array_keys(ImportSchema::kinds()) as $kind) {
            $bytes = app(ImportWorkbook::class)->template($kind);
            $file = tmpfile();
            fwrite($file, $bytes);
            fflush($file);
            try {
                (new ImportFileReader())->read(stream_get_meta_data($file)['uri'], $kind, 'xlsx');
                $this->fail('La plantilla vacía no debe contener registros para importar.');
            } catch (ApiProblem $e) {
                $this->assertSame('IMPORT_EMPTY', $e->errorCode);
            } finally {
                fclose($file);
            }
        }
    }

    public function test_import_third_parties_are_new_only_and_normalized_rifs_detect_duplicates(): void
    {
        $row = ['empresa_rif' => $this->a->tax_id, 'referencia_importacion' => 'P01', 'rif' => 'J-00000001-0', 'nombre' => 'Cliente ficticio importado', 'tipo' => 'customer'];
        $job = $this->importFile('third-parties', [$row]);
        $this->assertSame(0, ThirdParty::count());
        $this->assertSame(1, $job['summary']['valid']);
        $accepted = $this->importApply($job);
        $this->assertSame(1, ThirdParty::count());
        $this->assertSame('third-party', $accepted['units'][0]['resource']['type']);
        $row['referencia_importacion'] = 'P02';
        $row['rif'] = 'J000000010';
        $duplicate = $this->importFile('third-parties', [$row]);
        $this->assertSame('DUPLICATE_PARTY', $duplicate['units'][0]['errors'][0]['code']);
        $this->assertSame(1, ThirdParty::count());
    }

    public function test_import_documents_require_exact_totals_and_remain_pending_fiscal_review(): void
    {
        $party = ThirdParty::create(['tax_id' => 'J-00000001-0', 'name' => 'Cliente ficticio', 'kind' => 'customer', 'active' => true]);
        $d = ['empresa_rif' => $this->a->tax_id, 'referencia_importacion' => 'F01', 'tipo_documento' => 'sale_invoice', 'numero' => '000001', 'fecha_emision' => now()->format('Y-m-d'), 'fecha_fiscal' => now()->format('Y-m-d'), 'rif_tercero' => $party->tax_id, 'moneda' => 'VES', 'tasa_cambio' => '1', 'fecha_tasa' => now()->format('Y-m-d'), 'fuente_tasa' => 'Moneda de libro', 'base' => '100.000000000001', 'iva' => '16', 'otros_impuestos' => '0', 'total' => '116.000000000001'];
        $preview = $this->importFile('documents', [$d]);
        $this->assertSame(1, $preview['summary']['valid']);
        $this->assertSame(0, SourceDocument::count());
        $accepted = $this->importApply($preview);
        $document = SourceDocument::findOrFail($accepted['units'][0]['resource']['id']);
        $this->assertSame('draft', $document->status);
        $this->assertSame('116.000000000001', $document->amounts['total']);
        $this->assertSame([], $document->fiscal_amounts);
        $this->assertSame(0, JournalEntry::count());
        $d['referencia_importacion'] = 'F02';
        $this->assertSame('POSSIBLE_DUPLICATE_DOCUMENT', $this->importFile('documents', [$d])['units'][0]['errors'][0]['code']);
        $d['numero'] = '000002';
        $d['total'] = '116';
        $this->assertSame('IMPORT_DOCUMENT_TOTAL', $this->importFile('documents', [$d])['units'][0]['errors'][0]['code']);
    }

    public function test_import_opening_is_a_single_atomic_balanced_draft(): void
    {
        $common = ['empresa_rif' => $this->a->tax_id, 'referencia_importacion' => 'APERTURA', 'tipo_fila' => 'saldo', 'fecha_corte' => now()->format('Y-m-d'), 'motivo' => 'Migración ficticia de saldos conciliados', 'soporte_general' => 'Balance ficticio firmado'];
        $rows = [array_replace($common, ['cuenta' => '1.1.02', 'debe_ves' => '100', 'haber_ves' => '0']), array_replace($common, ['cuenta' => '3.1', 'debe_ves' => '0', 'haber_ves' => '100'])];
        $job = $this->importFile('opening', $rows);
        $this->assertSame(1, $job['summary']['valid']);
        $this->assertSame(0, JournalEntry::count());
        $accepted = $this->importApply($job);
        $entry = JournalEntry::findOrFail($accepted['units'][0]['resource']['id']);
        $this->assertSame('opening', $entry->entry_type);
        $this->assertNull($entry->posted_at);
        $this->assertNull($this->a->fresh()->migration_date);
        $this->assertSame(0, OpenItem::count());
        $rows[0]['referencia_importacion'] = $rows[1]['referencia_importacion'] = 'OTRA-APERTURA';
        $rows[0]['debe_ves'] = $rows[1]['haber_ves'] = '101';
        $this->assertSame('OPENING_EXISTS', $this->importFile('opening', $rows)['units'][0]['errors'][0]['code']);
    }

    public function test_import_versions_and_operation_receipts_handle_uncertain_responses(): void
    {
        $job = $this->importFile('entries', [$this->importLine('A', '1.1.02', '1', '0'), $this->importLine('A', '3.1', '0', '1')]);
        $payload = ['id' => $job['id'], 'version' => $job['version'], 'preview_hash' => $job['preview_hash'], 'units' => [$job['units'][0]['id']]];
        $operations = app(OperationService::class);
        $first = $operations->execute($this->admin->id, 'import.apply', 'import-retry-key', $payload, fn() => $this->importApply($job));
        $replay = $operations->execute($this->admin->id, 'import.apply', 'import-retry-key', $payload, fn() => throw new \RuntimeException('No debe repetirse'));
        $this->assertEquals(json_decode(json_encode($first), true), $replay);
        $this->assertSame('confirmed', $operations->status($this->admin->id, 'import.apply', 'import-retry-key')['status']);
        $this->assertSame(1, JournalEntry::count());
        $this->expectException(ApiProblem::class);
        $this->expectExceptionMessage('validación cambió');
        $this->importApply($job);
    }

    public function test_import_infrastructure_failure_rolls_back_all_selected_units_and_receipts(): void
    {
        $job = $this->importFile('entries', [$this->importLine('A', '1.1.02', '1', '0'), $this->importLine('A', '3.1', '0', '1'), $this->importLine('B', '1.1.02', '2', '0', ['descripcion' => 'FALLO-IMPORTACION-FICTICIO']), $this->importLine('B', '3.1', '0', '2')]);
        $this->assertSame(2, $job['summary']['valid']);
        $enabled = true;
        DB::listen(function ($query) use (&$enabled) {
            if ($enabled && str_contains($query->sql, 'accounting_journal_lines') && in_array('FALLO-IMPORTACION-FICTICIO', $query->bindings, true)) {
                throw new \RuntimeException('Fallo de infraestructura ficticio');
            }
        });
        try {
            $this->importApply($job);
            $this->fail('El fallo SQL debe abortar el lote completo.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Fallo de infraestructura ficticio', $e->getMessage());
            $this->assertSame(0, JournalEntry::count());
            $this->assertSame(0, ImportReceipt::count());
            $this->assertSame(2, ImportUnit::where('status', 'valid')->count());
            $this->assertSame($job['version'], ImportJob::findOrFail($job['id'])->version);
        } finally {
            $enabled = false;
        }
    }

    public function test_import_opening_reconciles_customer_control_and_original_currency_before_accepting(): void
    {
        ThirdParty::create(['tax_id' => 'J-00000001-0', 'name' => 'Cliente inicial ficticio', 'kind' => 'customer', 'active' => true]);
        $control = Account::where('auxiliary', 'receivable')->where('posting', true)->firstOrFail();
        $common = ['empresa_rif' => $this->a->tax_id, 'referencia_importacion' => 'APERTURA-AUX', 'fecha_corte' => now()->format('Y-m-d'), 'motivo' => 'Migración ficticia con auxiliares conciliados', 'soporte_general' => 'Balance ficticio revisado'];
        $rows = [array_replace($common, ['tipo_fila' => 'saldo', 'cuenta' => $control->code, 'debe_ves' => '100', 'haber_ves' => '0']), array_replace($common, ['tipo_fila' => 'saldo', 'cuenta' => '3.1', 'debe_ves' => '0', 'haber_ves' => '100']), array_replace($common, ['tipo_fila' => 'partida', 'cuenta' => $control->code, 'rif_tercero' => 'J-00000001-0', 'referencia_partida' => 'FAC-HIST-01', 'fecha_emision' => now()->subDay()->format('Y-m-d'), 'moneda' => 'USD', 'importe_original' => '2', 'valor_libro_ves' => '100', 'tasa_cambio' => '50', 'fecha_tasa' => now()->subDay()->format('Y-m-d'), 'fuente_tasa' => 'Soporte histórico ficticio', 'soporte_partida' => 'Factura histórica ficticia'])];
        $job = $this->importFile('opening', $rows);
        $this->assertSame(1, $job['summary']['valid']);
        $rows[2]['valor_libro_ves'] = '99';
        $bad = $this->importFile('opening', $rows);
        $this->assertSame('OPENING_CONVERSION_MISMATCH', $bad['units'][0]['errors'][0]['code']);
        $this->assertSame(0, JournalEntry::count());
        $accepted = $this->importApply($job);
        $entry = JournalEntry::findOrFail($accepted['units'][0]['resource']['id']);
        $this->assertSame(2, $entry->lines()->count());
        $this->assertSame('USD', $entry->metadata['financial_effect']['open_items'][0]['currency']);
        $this->assertSame(0, OpenItem::count());
    }

    public function test_import_note_requires_existing_matching_invoice_and_cannot_invent_its_reference(): void
    {
        ThirdParty::create(['tax_id' => 'J-00000001-0', 'name' => 'Cliente ficticio', 'kind' => 'customer', 'active' => true]);
        $invoice = ['empresa_rif' => $this->a->tax_id, 'referencia_importacion' => 'FACTURA-BASE', 'tipo_documento' => 'sale_invoice', 'numero' => '000003', 'fecha_emision' => now()->format('Y-m-d'), 'fecha_fiscal' => now()->format('Y-m-d'), 'rif_tercero' => 'J-00000001-0', 'moneda' => 'VES', 'tasa_cambio' => '1', 'fecha_tasa' => now()->format('Y-m-d'), 'fuente_tasa' => 'Moneda de libro', 'base' => '100', 'iva' => '16', 'otros_impuestos' => '0', 'total' => '116'];
        $note = array_replace($invoice, ['referencia_importacion' => 'NOTA-01', 'tipo_documento' => 'sale_credit_note', 'numero' => 'NC-01', 'original_numero' => '000003', 'original_fecha' => $invoice['fecha_emision']]);
        $this->assertSame('IMPORT_ORIGINAL_DOCUMENT', $this->importFile('documents', [$note])['units'][0]['errors'][0]['code']);
        $acceptedInvoice = $this->importApply($this->importFile('documents', [$invoice]));
        // The same file is intentionally a persisted preview. Explicitly revalidate
        // after creating the antecedent, rather than silently changing its result.
        $job = $this->importFile('documents', [$note]);
        $job = app(ImportService::class)->revalidate($job['id'], $job['version'], $job['preview_hash'], $this->admin->id);
        $this->assertSame(1, $job['summary']['valid']);
        $acceptedNote = $this->importApply($job);
        $document = SourceDocument::findOrFail($acceptedNote['units'][0]['resource']['id']);
        $this->assertSame($acceptedInvoice['units'][0]['resource']['id'], $document->related_document_id);
        $this->assertSame('draft', $document->status);
    }

    public function test_import_rechecks_permissions_inside_the_locked_confirmation_transaction(): void
    {
        $job = $this->importFile('entries', [$this->importLine('A', '1.1.02', '1', '0'), $this->importLine('A', '3.1', '0', '1')]);
        DB::table('accounting_memberships')->where('company_id', $this->a->id)->where('user_id', $this->admin->id)->update(['roles' => json_encode(['viewer'])]);
        try {
            $this->importApply($job);
            $this->fail('No se acepta una selección con permisos retirados.');
        } catch (ApiProblem $error) {
            $this->assertSame('FORBIDDEN_ACTION', $error->errorCode);
            $this->assertSame(0, JournalEntry::count());
            $this->assertSame(0, ImportReceipt::count());
            $this->assertSame($job['version'], ImportJob::findOrFail($job['id'])->version);
        }
    }

    public function test_import_line_error_reports_the_actual_source_row_and_field_without_saving_other_lines(): void
    {
        $job = $this->importFile('entries', [$this->importLine('A', '1.1.02', '3', '0'), $this->importLine('A', '3.1', '0', '-3')]);
        $this->assertSame(1, $job['summary']['invalid']);
        $this->assertSame([2, 3], $job['units'][0]['rows']);
        $this->assertSame(3, $job['units'][0]['errors'][0]['row']);
        $this->assertSame('haber_ves', $job['units'][0]['errors'][0]['field']);
        $this->assertSame('INVALID_LINE', $job['units'][0]['errors'][0]['code']);
        $this->assertSame(0, JournalEntry::count());
    }
}
