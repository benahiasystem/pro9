<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Models\{Account, JournalEntry, OpenItem, Incident, Period};
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Accounting\Support\TenantDB as DB;
final class ReportService
{
    public const ANNUAL_BALANCE_TRANSFERS = ['annual_balance', 'annual_opening', 'annual_reopen_balance', 'annual_reopen_opening'];
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
    public function build(string $kind, array $filters = []): array
    {
        return DB::transaction(function () use ($kind, $filters) {
            $c = app(CompanyContext::class);
            $from = $filters['from'] ?? now('America/Caracas')->startOfMonth()->format('Y-m-d');
            $to = $filters['to'] ?? now('America/Caracas')->endOfMonth()->format('Y-m-d');
            if ($from > $to) {
                throw new ApiProblem('INVALID_DATE_RANGE', 'La fecha inicial debe ser anterior a la final.');
            }
            $result = match ($kind) {
                'dashboard' => $this->dashboard($from, $to),
                'journal' => $this->journal($from, $to, $filters),
                'ledger' => $this->ledger($from, $to, $filters),
                'trial-balance' => $this->trial($from, $to),
                'balance-sheet' => $this->balance($to),
                'income-statement' => $this->income($from, $to),
                'open-items' => $this->openItems($filters),
                default => throw new ApiProblem('REPORT_NOT_FOUND', 'El informe no está disponible.', 404),
            };
            $versions = Period::where(fn($q) => $q->where('year', '<', (int) substr($to, 0, 4))->orWhere(fn($q) => $q->where('year', (int) substr($to, 0, 4))->where('month', '<=', (int) substr($to, 5, 2))))->orderBy('year')->orderBy('month')->get(['id', 'year', 'month', 'status', 'version', 'close_snapshot'])->toArray();
            return [...$result, 'kind' => $kind, 'from' => $from, 'to' => $to, 'company' => ['id' => $c->id(), 'name' => $c->company()->name, 'tax_id' => $c->company()->tax_id], 'data_version' => (int) (DB::table('accounting_audit_heads')->where('company_id', $c->id())->value('last_sequence') ?? 0), 'period_versions' => $versions, 'generated_at' => now()->toIso8601String(), 'format_version' => '1.0'];
        });
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
    private function page($q, array $filters): array
    {
        $per = max(1, min(500, (int) ($filters['per_page'] ?? 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = (clone $q)->count();
        $rows = $q->offset(($page - 1) * $per)->limit($per)->get()->map(fn($x) => (array) $x)->all();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per, 'last_page' => max(1, (int) ceil($total / $per))];
    }
    private function journal(string $from, string $to, array $f): array
    {
        $q = $this->lines()->whereBetween('e.accounting_date', [$from, $to]);
        $tot = (clone $q)->selectRaw('SUM(l.debit) as debit,SUM(l.credit) as credit')->first();
        $q->select('e.id as entry_id', 'e.reference', 'e.accounting_date', 'e.concept', 'e.entry_type', 'a.code as account_code', 'a.name as account_name', 'l.debit', 'l.credit', 'l.third_party_id', 'l.document_reference')->orderBy('e.accounting_date')->orderBy('e.reference')->orderBy('l.position');
        return [...$this->page($q, $f), 'totals' => ['debit' => $this->money($tot->debit), 'credit' => $this->money($tot->credit)]];
    }
    private function ledger(string $from, string $to, array $f): array
    {
        if (empty($f['account_id'])) {
            throw new ApiProblem('ACCOUNT_REQUIRED', 'Selecciona una cuenta para consultar el Mayor.');
        }
        $account = Account::findOrFail($f['account_id']);
        $q = $this->lines()->where('l.account_id', $account->id);
        if (!empty($f['third_party_id'])) {
            $q->where('l.third_party_id', $f['third_party_id']);
        }
        $opening = (clone $q)->where('e.accounting_date', '<', $from)->selectRaw('COALESCE(SUM(l.debit-l.credit),0) as balance')->value('balance');
        $q->whereBetween('e.accounting_date', [$from, $to]);
        $tot = (clone $q)->selectRaw('SUM(l.debit) as debit,SUM(l.credit) as credit')->first();
        $q->select('e.id as entry_id', 'e.reference', 'e.accounting_date', 'e.concept', 'l.debit', 'l.credit', 'l.document_reference')->orderBy('e.accounting_date')->orderBy('e.reference')->orderBy('l.position')->orderBy('l.id');
        $per = max(1, min(500, (int) ($f['per_page'] ?? 100)));
        $number = max(1, (int) ($f['page'] ?? 1));
        $offset = ($number - 1) * $per;
        // MySQL 5.7 has no window functions. Aggregate the ordered prefix in SQL,
        // then compute this page with exact decimal arithmetic.
        $running = BigDecimal::of((string) $opening);
        if ($offset > 0) {
            $prefix = (clone $q)->select('l.debit', 'l.credit')->limit($offset);
            $prior = DB::query()->fromSub($prefix, 'preceding_lines')->selectRaw('COALESCE(SUM(debit-credit),0) as amount')->value('amount');
            $running = $running->plus((string) $prior);
        }
        $page = $this->page($q, $f);
        foreach ($page['rows'] as &$row) {
            $running = $running->plus($row['debit'])->minus($row['credit']);
            $row['balance'] = $this->money($running);
        }
        unset($row);
        return [...$page, 'account' => $account, 'opening' => $this->money($opening), 'totals' => ['debit' => $this->money($tot->debit), 'credit' => $this->money($tot->credit), 'balance' => $this->money(BigDecimal::of((string) $opening)->plus((string) ($tot->debit ?? '0'))->minus((string) ($tot->credit ?? '0')))]];
    }
    private function trial(string $from, string $to): array
    {
        $q = $this->lines()->where('e.accounting_date', '<=', $to);
        $rows = $q->select('a.id', 'a.code', 'a.name', 'a.kind', 'a.nature')->selectRaw('SUM(CASE WHEN e.accounting_date < ? THEN l.debit-l.credit ELSE 0 END) as opening', [$from])->selectRaw('SUM(CASE WHEN e.accounting_date >= ? THEN l.debit ELSE 0 END) as debit', [$from])->selectRaw('SUM(CASE WHEN e.accounting_date >= ? THEN l.credit ELSE 0 END) as credit', [$from])->selectRaw('SUM(l.debit-l.credit) as balance')->groupBy('a.id', 'a.code', 'a.name', 'a.kind', 'a.nature')->orderBy('a.code')->get();
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
        })->whereIn('a.kind', ['income', 'cost', 'expense'])->select('a.code', 'a.name', 'a.kind')->selectRaw('SUM(l.credit-l.debit) as effect')->groupBy('a.code', 'a.name', 'a.kind')->orderBy('a.code')->get();
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
        $rows = $this->economicLines()->where('e.accounting_date', '<=', $to)->select('a.code', 'a.name', 'a.kind')->selectRaw('SUM(l.debit-l.credit) as balance')->groupBy('a.code', 'a.name', 'a.kind')->orderBy('a.code')->get();
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
    private function openItems(array $f): array
    {
        $q = OpenItem::query();
        if (!empty($f['side'])) {
            $q->where('side', $f['side']);
        }
        if (!empty($f['third_party_id'])) {
            $q->where('third_party_id', $f['third_party_id']);
        }
        return ['rows' => $q->orderBy('due_date')->limit(5000)->get(), 'totals' => ['book' => $this->money((clone $q)->sum('remaining_book'))]];
    }
}
