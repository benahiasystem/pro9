<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Models\{Account, JournalEntry, OpenItem, Incident, Period, Company};
use Modules\Accounting\Support\{ReportInput, ReportSchema, TenantDB as DB};
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class ReportService
{
    public const ANNUAL_BALANCE_TRANSFERS = ['annual_balance', 'annual_opening', 'annual_reopen_balance', 'annual_reopen_opening'];
    private const DETAIL_KINDS = ['journal', 'ledger', 'auxiliary', 'open-items', 'documents', 'incidents'];

    private function money(mixed $v): string
    {
        return (string) BigDecimal::of((string) ($v ?? '0'))->toScale(2, RoundingMode::HALF_UP);
    }

    public function lines(): \Illuminate\Database\Query\Builder
    {
        $c = app(CompanyContext::class);
        return DB::table('accounting_journal_lines as l')->join('accounting_journal_entries as e', function ($j) {
            $j->on('l.journal_entry_id', '=', 'e.id')->on('l.organization_id', '=', 'e.organization_id')->on('l.company_id', '=', 'e.company_id');
        })->join('accounting_accounts as a', function ($j) {
            $j->on('l.account_id', '=', 'a.id')->on('l.company_id', '=', 'a.company_id')->on('l.organization_id', '=', 'a.organization_id');
        })->where('l.organization_id', $c->organizationId())->where('l.company_id', $c->id())->whereNotNull('e.posted_at');
    }

    public function economicLines(): \Illuminate\Database\Query\Builder
    {
        return $this->lines()->whereNotIn('e.effect_type', self::ANNUAL_BALANCE_TRANSFERS);
    }

    public function presentation(): array
    {
        return Account::orderBy('code')->get(['id', 'code', 'name', 'kind', 'nature', 'report_group', 'version'])->toArray();
    }

    public function build(string $kind, array $filters = []): array
    {
        return DB::readSnapshot(function () use ($kind, $filters) {
            if ($kind !== 'dashboard') {
                ReportSchema::title($kind);
            }
            $f = ReportInput::normalize($kind, $filters);
            $from = $f['from'];
            $to = $f['to'];
            $c = app(CompanyContext::class);
            $company = Company::whereKey($c->id())->where('organization_id', $c->organizationId())->firstOrFail();
            $result = match ($kind) {
                'dashboard' => $this->dashboard($from, $to),
                'trial-balance' => $this->trial($from, $to),
                'balance-sheet' => $this->balance($to),
                'income-statement' => $this->income($from, $to),
                default => app(ReportRows::class)->build($kind, $f, $filters),
            };
            if (isset($f['compare_from'])) {
                $prior = $kind === 'balance-sheet' ? $this->balance($f['compare_to']) : $this->income($f['compare_from'], $f['compare_to']);
                $result = $this->comparison($result, $prior, $f);
            }
            $maxTo = max($to, $f['compare_to'] ?? $to);
            $versions = Period::where(fn($q) => $q->where('year', '<', (int) substr($maxTo, 0, 4))->orWhere(fn($q) => $q->where('year', (int) substr($maxTo, 0, 4))->where('month', '<=', (int) substr($maxTo, 5, 2))))->orderBy('year')->orderBy('month')->get(['id', 'year', 'month', 'status', 'version', 'close_snapshot'])->toArray();
            $presentationHash = hash('sha256', json_encode($this->presentation(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $dataVersion = (int) (DB::table('accounting_audit_events')->where('organization_id', $c->organizationId())->where('company_id', $c->id())->where('action', 'not like', 'report.%')->whereNotIn('action', ['offline.prepared', 'import.previewed', 'import.revalidated', 'attachment.downloaded'])->max('sequence') ?? 0);
            $scope = $kind === 'documents' ? (!empty($f['include_pending']) ? 'control_with_pending' : 'source_control') : ($kind === 'incidents' || $kind === 'dashboard' ? 'operational_control' : 'posted_only');
            $out = [...$result, 'kind' => $kind, 'title' => $kind === 'dashboard' ? 'Inicio' : ReportSchema::title($kind), 'from' => $from, 'to' => $to,
                'filters' => $f, 'data_scope' => $scope, 'date_basis' => $kind === 'open-items' ? 'cutoff' : 'interval',
                'scope_note' => match ($scope) {
                    'control_with_pending' => 'Control de origen con documentos pendientes, identificados por estado. No incorpora borradores a los libros ni sustituye el registro fiscal.',
                    'source_control' => 'Control de documentos de origen vinculados con comprobantes contabilizados. El estado y los importes de origen no sustituyen la versión contable ni el libro fiscal.',
                    'operational_control' => 'Control operativo; sus conteos no son saldos contables.',
                    default => $kind === 'open-items' ? 'Saldos reconstruidos al corte, incluidas las deudas anteriores al intervalo. La fecha desde no limita la antigüedad de las partidas. Las aplicaciones y sus reversos se consideran según su fecha contable.' : 'Solo movimientos contabilizados. Los reversos conservan el original y su contrapartida; no se descuentan dos veces.',
                },
                'company' => $company->only(['id', 'name', 'tax_id', 'address']),
                'columns' => ReportSchema::columns($kind, isset($f['compare_from'])), 'data_version' => $dataVersion,
                'period_versions' => $versions, 'generated_at' => now()->toIso8601String(), 'format_version' => ReportSchema::VERSION,
                'presentation_version' => ReportSchema::VERSION . ':' . substr($presentationHash, 0, 12), 'presentation_hash' => $presentationHash];
            $out['review_token'] = hash('sha256', json_encode(['company' => $out['company'], 'kind' => $kind, 'filters' => $f, 'data_version' => $dataVersion, 'period_versions' => $versions, 'presentation_hash' => $presentationHash, 'totals' => $result['totals'] ?? [], 'total' => $result['total'] ?? count($result['rows'] ?? []), 'comparison_totals' => $result['comparison']['totals'] ?? null], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            return $out;
        });
    }

    public function iterate(string $kind, array $filters, ?array $built = null): \Generator
    {
        DB::assertReadSnapshot();
        $f = ReportInput::normalize($kind, $filters);
        if (in_array($kind, self::DETAIL_KINDS, true)) {
            yield from app(ReportRows::class)->iterate($kind, $f);
            return;
        }
        $report = $built ?? $this->build($kind, $f);
        foreach ($report['rows'] as $row) {
            yield $row;
        }
    }

    public function assertBookPrecision(string $kind, array $f): void
    {
        if (!in_array($kind, ['journal', 'ledger', 'auxiliary', 'trial-balance', 'balance-sheet', 'income-statement'], true)) {
            return;
        }
        $q = $this->lines()->where('e.accounting_date', '<=', max($f['to'], $f['compare_to'] ?? $f['to']));
        foreach (['account_id', 'third_party_id', 'cost_center_id'] as $field) {
            if (isset($f[$field])) {
                $q->where('l.' . $field, $f[$field]);
            }
        }
        if ($q->where(fn($q) => $q->whereRaw('l.debit <> ROUND(l.debit,2)')->orWhereRaw('l.credit <> ROUND(l.credit,2)'))->exists()) {
            throw new ApiProblem('REPORT_BOOK_PRECISION', 'Existen movimientos contabilizados fuera de la precisión del libro VES. Revisa su integridad antes de exportar; no se redondean para ocultarlos.', 409);
        }
    }

    private function comparison(array $current, array $prior, array $f): array
    {
        $rows = [];
        foreach ($current['rows'] as $row) {
            $rows[$row['code']] = $row;
        }
        foreach ($prior['rows'] as $row) {
            $rows[$row['code']] ??= [...$row, 'amount' => '0.00'];
        }
        $previous = array_column($prior['rows'], 'amount', 'code');
        ksort($rows);
        foreach ($rows as $code => &$row) {
            $row['previous_amount'] = $previous[$code] ?? '0.00';
            $row['difference'] = $this->money(BigDecimal::of($row['amount'])->minus($row['previous_amount']));
        }
        unset($row);
        $changes = [];
        foreach ($current['totals'] as $key => $value) {
            $changes[$key] = $this->money(BigDecimal::of($value)->minus($prior['totals'][$key] ?? '0'));
        }
        return [...$current, 'rows' => array_values($rows), 'comparison' => ['from' => $f['compare_from'], 'to' => $f['compare_to'], 'totals' => $prior['totals'], 'difference_totals' => $changes, 'basis_note' => 'Ambos intervalos usan la clasificación y versión de presentación identificadas en este informe. Los totales proceden de movimientos contabilizados.']];
    }

    private function dashboard(string $from, string $to): array
    {
        $counts = JournalEntry::select('status')->selectRaw('COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $ar = OpenItem::where('side', 'receivable')->where('status', 'open')->sum('remaining_book');
        $ap = OpenItem::where('side', 'payable')->where('status', 'open')->sum('remaining_book');
        $income = $this->income($from, $to);
        $latest = JournalEntry::orderByDesc('created_at')->limit(5)->get(['id', 'concept', 'reference', 'status', 'accounting_date', 'total_debit', 'version']);
        return ['counts' => $counts, 'receivable' => $this->money($ar), 'payable' => $this->money($ap), 'result' => $income['totals']['result'], 'incidents' => Incident::where('status', 'open')->count(), 'recent' => $latest, 'periods' => Period::whereBetween('year', [(int) substr($from, 0, 4), (int) substr($to, 0, 4)])->get()];
    }
    private function trial(string $from, string $to): array
    {
        $q = $this->lines()->where('e.accounting_date', '<=', $to);
        $rows = $q->select('a.id', 'a.code', 'a.name', 'a.kind', 'a.nature', 'a.report_group', 'a.version as account_version')->selectRaw('SUM(CASE WHEN e.accounting_date < ? THEN l.debit-l.credit ELSE 0 END) as opening', [$from])->selectRaw('SUM(CASE WHEN e.accounting_date >= ? THEN l.debit ELSE 0 END) as debit', [$from])->selectRaw('SUM(CASE WHEN e.accounting_date >= ? THEN l.credit ELSE 0 END) as credit', [$from])->selectRaw('SUM(l.debit-l.credit) as balance')->groupBy('a.id', 'a.code', 'a.name', 'a.kind', 'a.nature', 'a.report_group', 'a.version')->orderBy('a.code')->get();
        $debit = BigDecimal::zero();
        $credit = BigDecimal::zero();
        $balance = BigDecimal::zero();
        $out = [];
        foreach ($rows as $r) {
            $debit = $debit->plus($r->debit);
            $credit = $credit->plus($r->credit);
            $balance = $balance->plus($r->balance);
            $out[] = [...(array) $r, 'opening' => $this->money($r->opening), 'debit' => $this->money($r->debit), 'credit' => $this->money($r->credit), 'balance' => $this->money($r->balance)];
        }
        return ['rows' => $out, 'totals' => ['debit' => $this->money($debit), 'credit' => $this->money($credit), 'balance' => $this->money($balance)]];
    }
    private function income(string $from, string $to): array
    {
        $rows = $this->lines()->whereBetween('e.accounting_date', [$from, $to])->whereNotIn('e.entry_type', ['closing', 'opening'])->whereNotExists(function ($q) {
            $q->selectRaw('1')->from('accounting_journal_entries as original')->whereColumn('original.id', 'e.reversal_of')->whereColumn('original.company_id', 'e.company_id')->whereIn('original.entry_type', ['opening', 'closing']);
        })->whereIn('a.kind', ['income', 'cost', 'expense'])->select('a.code', 'a.name', 'a.kind', 'a.report_group', 'a.version as account_version')->selectRaw('SUM(l.credit-l.debit) as effect')->groupBy('a.code', 'a.name', 'a.kind', 'a.report_group', 'a.version')->orderBy('a.code')->get();
        $result = BigDecimal::zero();
        $out = [];
        foreach ($rows as $r) {
            $result = $result->plus($r->effect);
            $out[] = [...(array) $r, 'amount' => $this->money($r->kind === 'income' ? $r->effect : BigDecimal::of($r->effect)->negated())];
        }
        $carry = $this->lines()->where('e.accounting_date', '<=', $to)->where(function ($q) {
            $q->where('e.entry_type', 'opening')->orWhereExists(function ($sub) {
                $sub->selectRaw('1')->from('accounting_journal_entries as original')->whereColumn('original.id', 'e.reversal_of')->whereColumn('original.company_id', 'e.company_id')->where('original.entry_type', 'opening');
            });
        })->whereIn('a.kind', ['income', 'cost', 'expense'])->selectRaw('COALESCE(SUM(l.credit-l.debit),0) AS amount')->value('amount');
        return ['rows' => $out, 'totals' => ['result' => $this->money($result), 'opening_carry_forward' => $this->money($carry)], 'preliminary' => true, 'opening_note' => 'Los saldos iniciales de resultados se conservan por separado; no son ingresos o gastos nuevos del intervalo.'];
    }
    private function balance(string $to): array
    {
        $rows = $this->economicLines()->where('e.accounting_date', '<=', $to)->select('a.code', 'a.name', 'a.kind', 'a.report_group', 'a.version as account_version')->selectRaw('SUM(l.debit-l.credit) as balance')->groupBy('a.code', 'a.name', 'a.kind', 'a.report_group', 'a.version')->orderBy('a.code')->get();
        $assets = BigDecimal::zero();
        $liabilities = BigDecimal::zero();
        $equity = BigDecimal::zero();
        $unclosed = BigDecimal::zero();
        $out = [];
        foreach ($rows as $r) {
            $v = BigDecimal::of($r->balance);
            switch ($r->kind) {
                case 'asset':
                    $assets = $assets->plus($v);
                    break;
                case 'liability':
                    $liabilities = $liabilities->minus($v);
                    break;
                case 'equity':
                    $equity = $equity->minus($v);
                    break;
                default:
                    $unclosed = $unclosed->minus($v);
                    continue 2;
            }
            $out[] = [...(array) $r, 'amount' => $this->money($r->kind === 'asset' ? $v : $v->negated())];
        }
        $transfers = JournalEntry::whereNotNull('posted_at')->where('accounting_date', '<=', $to)->whereIn('effect_type', self::ANNUAL_BALANCE_TRANSFERS)->exists();
        return ['rows' => $out, 'totals' => ['assets' => $this->money($assets), 'liabilities' => $this->money($liabilities), 'equity' => $this->money($equity), 'unclosed_result' => $this->money($unclosed), 'difference' => $this->money($assets->minus($liabilities)->minus($equity)->minus($unclosed))], 'preliminary' => true,
            'basis_note' => $transfers ? 'La situación financiera conserva los saldos anteriores al traslado anual. El Diario y el Mayor incluyen los comprobantes de cierre y apertura; esos traslados no duplican activos, pasivos ni patrimonio en este informe.' : null];
    }
}
