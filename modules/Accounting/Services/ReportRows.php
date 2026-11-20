<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Models\Account;
use Modules\Accounting\Support\TenantDB as DB;

/** One query definition for paged views and complete, ordered export streams. */
final class ReportRows
{
    private function money(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::UNNECESSARY);
    }

    private function filter($query, array $f): mixed
    {
        foreach (['account_id', 'third_party_id', 'cost_center_id'] as $key) {
            if (isset($f[$key])) {
                $query->where('l.' . $key, $f[$key]);
            }
        }
        return $query;
    }

    private function journalQuery(array $f): mixed
    {
        return $this->filter(app(ReportService::class)->lines(), $f)
            ->leftJoin('accounting_third_parties as p', fn($j) => $j->on('p.id', '=', 'l.third_party_id')->on('p.company_id', '=', 'l.company_id')->on('p.organization_id', '=', 'l.organization_id'))
            ->leftJoin('accounting_source_documents as d', fn($j) => $j->on('d.id', '=', 'e.source_document_id')->on('d.company_id', '=', 'e.company_id')->on('d.organization_id', '=', 'e.organization_id'))
            ->whereBetween('e.accounting_date', [$f['from'], $f['to']])
            ->select('l.id as line_id', 'l.position', 'e.id as entry_id', 'e.version as entry_version', 'e.reference', 'e.accounting_date', 'e.concept', 'e.entry_type', 'e.reversal_of', 'e.source_document_id', 'd.number as source_number', 'a.id as account_id', 'a.code as account_code', 'a.name as account_name', 'a.version as account_version', 'a.kind as account_kind', 'a.report_group', 'p.id as third_party_id', 'p.name as party_name', 'p.tax_id as party_tax_id', 'l.document_reference', 'l.description', 'l.debit', 'l.credit', 'l.original_currency', 'l.original_amount', 'l.exchange_rate')
            ->orderBy('e.accounting_date')->orderBy('e.reference')->orderBy('l.position')->orderBy('l.id');
    }

    private function openItemsQuery(array $f): mixed
    {
        $c = app(CompanyContext::class);
        $to = $f['to'];
        $applications = DB::table('accounting_settlement_allocations as s')
            ->join('accounting_journal_entries as se', fn($j) => $j->on('se.id', '=', 's.journal_entry_id')->on('se.company_id', '=', 's.company_id')->on('se.organization_id', '=', 's.organization_id'))
            ->leftJoin('accounting_journal_entries as sr', fn($j) => $j->on('sr.id', '=', 's.reversal_entry_id')->on('sr.company_id', '=', 's.company_id')->on('sr.organization_id', '=', 's.organization_id')->whereNotNull('sr.posted_at')->where('sr.accounting_date', '<=', $to))
            ->where('s.company_id', $c->id())->where('s.organization_id', $c->organizationId())->whereNotNull('se.posted_at')->where('se.accounting_date', '<=', $to)->whereNull('sr.id')
            ->select('s.open_item_id')->selectRaw('SUM(s.original_amount) as original_applied, SUM(s.book_amount) as book_applied')->groupBy('s.open_item_id');
        $q = DB::table('accounting_open_items as i')
            ->join('accounting_journal_entries as e', fn($j) => $j->on('e.id', '=', 'i.origin_entry_id')->on('e.company_id', '=', 'i.company_id')->on('e.organization_id', '=', 'i.organization_id'))
            ->leftJoin('accounting_journal_entries as r', fn($j) => $j->on('r.reversal_of', '=', 'e.id')->on('r.company_id', '=', 'e.company_id')->on('r.organization_id', '=', 'e.organization_id')->whereNotNull('r.posted_at')->where('r.accounting_date', '<=', $to))
            ->join('accounting_accounts as a', fn($j) => $j->on('a.id', '=', 'i.account_id')->on('a.company_id', '=', 'i.company_id')->on('a.organization_id', '=', 'i.organization_id'))
            ->join('accounting_third_parties as p', fn($j) => $j->on('p.id', '=', 'i.third_party_id')->on('p.company_id', '=', 'i.company_id')->on('p.organization_id', '=', 'i.organization_id'))
            ->leftJoinSub($applications, 'applied', fn($j) => $j->on('applied.open_item_id', '=', 'i.id'))
            ->where('i.company_id', $c->id())->where('i.organization_id', $c->organizationId())->whereNotNull('e.posted_at')->where('e.accounting_date', '<=', $to)->whereNull('r.id');
        foreach (['account_id', 'third_party_id', 'side'] as $field) {
            if (isset($f[$field])) {
                $q->where('i.' . $field, $f[$field]);
            }
        }
        $remainingOriginal = '(i.original_amount - COALESCE(applied.original_applied,0))';
        $remainingBook = '(i.book_amount - COALESCE(applied.book_applied,0))';
        if (($f['status'] ?? 'open') !== 'all') {
            $q->whereRaw($remainingOriginal . (($f['status'] ?? 'open') === 'settled' ? '=0' : '>0'));
        }
        return $q->select('i.id', 'i.account_id', 'i.third_party_id', 'i.side', 'i.reference', 'i.issue_date', 'i.due_date', 'i.currency', 'i.original_amount', 'i.book_amount', 'i.is_opening', 'i.source_document_id', 'e.id as entry_id', 'e.reference as entry_reference', 'a.code as account_code', 'a.name as account_name', 'p.name as party_name', 'p.tax_id as party_tax_id')
            ->selectRaw($remainingOriginal . ' as remaining_original, ' . $remainingBook . ' as remaining_book')
            ->selectRaw("CASE WHEN " . $remainingOriginal . "=0 THEN 'settled' ELSE 'open' END as status_at_cutoff")
            ->orderBy('i.id');
    }

    private function documentsQuery(array $f): mixed
    {
        $c = app(CompanyContext::class);
        $entries = DB::table('accounting_journal_entries as de')->where('de.company_id', $c->id())->where('de.organization_id', $c->organizationId())->whereNotNull('de.posted_at')->where('de.accounting_date', '<=', $f['to'])->whereNotNull('de.source_document_id')->select('de.source_document_id')->selectRaw('MIN(de.id::text COLLATE "C") as entry_id, MAX(de.source_revision) as posted_source_revision, COUNT(DISTINCT de.source_revision) as posted_source_versions')->groupBy('de.source_document_id');
        $openingEntries = DB::table('accounting_open_items as oi')->join('accounting_journal_entries as oe', fn($j) => $j->on('oe.id', '=', 'oi.origin_entry_id')->on('oe.company_id', '=', 'oi.company_id')->on('oe.organization_id', '=', 'oi.organization_id'))
            ->where('oi.company_id', $c->id())->where('oi.organization_id', $c->organizationId())->where('oi.is_opening', true)->whereNotNull('oi.source_document_id')->whereNotNull('oe.posted_at')->where('oe.accounting_date', '<=', $f['to'])
            ->select('oi.source_document_id')->selectRaw('MIN(oe.id::text COLLATE "C") as entry_id')->groupBy('oi.source_document_id');
        $q = DB::table('accounting_source_documents as d')
            ->leftJoinSub($entries, 'posted', fn($j) => $j->on('posted.source_document_id', '=', 'd.id'))
            ->leftJoinSub($openingEntries, 'opening_posted', fn($j) => $j->on('opening_posted.source_document_id', '=', 'd.id'))
            ->leftJoin('accounting_third_parties as p', fn($j) => $j->on('p.id', '=', 'd.third_party_id')->on('p.company_id', '=', 'd.company_id')->on('p.organization_id', '=', 'd.organization_id'))
            ->where('d.company_id', $c->id())->where('d.organization_id', $c->organizationId())->whereBetween('d.fiscal_date', [$f['from'], $f['to']]);
        if (empty($f['include_pending'])) {
            $q->where(fn($q) => $q->whereNotNull('posted.entry_id')->orWhereNotNull('opening_posted.entry_id'));
        }
        if (isset($f['third_party_id'])) {
            $q->where('d.third_party_id', $f['third_party_id']);
        }
        return $q->select('d.id', 'd.kind', 'd.origin', 'd.number', 'd.fiscal_control', 'd.issue_date', 'd.fiscal_date', 'd.status', 'd.currency', 'd.version', 'd.source_revision', 'd.third_party_id', 'p.name as party_name', 'p.tax_id as party_tax_id', 'posted.posted_source_revision', 'posted.posted_source_versions')->selectRaw('COALESCE(posted.entry_id, opening_posted.entry_id) as entry_id')
            ->selectRaw("d.amounts ->> 'total' as total")
            ->orderBy('d.id');
    }

    private function incidentsQuery(array $f): mixed
    {
        $c = app(CompanyContext::class);
        $q = DB::table('accounting_incidents as i')->where('i.company_id', $c->id())->where('i.organization_id', $c->organizationId())->where('i.created_at', '>=', $f['from'] . ' 00:00:00')->where('i.created_at', '<=', $f['to'] . ' 23:59:59');
        if (($f['status'] ?? 'all') !== 'all') {
            $q->where('i.status', $f['status']);
        }
        return $q->select('i.id', 'i.kind', 'i.title', 'i.status', 'i.resolution', 'i.resource_type', 'i.resource_id')->selectRaw('DATE(i.created_at) as created_on')->orderBy('i.id');
    }

    private function query(string $kind, array $f): mixed
    {
        return match ($kind) {
            'journal', 'ledger', 'auxiliary' => $this->journalQuery($f),
            'open-items' => $this->openItemsQuery($f),
            'documents' => $this->documentsQuery($f),
            'incidents' => $this->incidentsQuery($f),
        };
    }

    private function groupKey(array $row): string
    {
        return $row['account_id'] . '|' . ($row['third_party_id'] ?? '');
    }

    private function opening(array $f, bool $grouped): array
    {
        $q = $this->filter(app(ReportService::class)->lines(), $f)->where('e.accounting_date', '<', $f['from']);
        if (!$grouped) {
            return ['all' => (string) ($q->selectRaw('COALESCE(SUM(l.debit-l.credit),0) as amount')->value('amount') ?? '0')];
        }
        $rows = $q->select('l.account_id', 'l.third_party_id')->selectRaw('SUM(l.debit-l.credit) as amount')->groupBy('l.account_id', 'l.third_party_id')->get();
        $map = [];
        foreach ($rows as $row) {
            $map[$this->groupKey((array) $row)] = (string) $row->amount;
        }
        return $map;
    }

    private function openingGroups(array $f): array
    {
        return $this->filter(app(ReportService::class)->lines(), $f)
            ->leftJoin('accounting_third_parties as op', fn($j) => $j->on('op.id', '=', 'l.third_party_id')->on('op.company_id', '=', 'l.company_id')->on('op.organization_id', '=', 'l.organization_id'))
            ->where('e.accounting_date', '<', $f['from'])
            ->select('l.account_id', 'l.third_party_id', 'a.code as account_code', 'a.name as account_name', 'op.name as party_name', 'op.tax_id as party_tax_id')
            ->selectRaw('SUM(l.debit-l.credit) as opening')->groupBy('l.account_id', 'l.third_party_id', 'a.code', 'a.name', 'op.name', 'op.tax_id')
            ->havingRaw('SUM(l.debit-l.credit)<>0')->orderBy('a.code')->orderBy('op.tax_id')->get()->map(fn($row) => [...(array) $row, 'opening' => $this->money($row->opening)])->all();
    }

    private function totals(string $kind, $query, array $f): array
    {
        $data = DB::query()->fromSub((clone $query)->reorder(), 'dataset');
        if (in_array($kind, ['journal', 'ledger', 'auxiliary'], true)) {
            $r = (clone $data)->selectRaw('SUM(debit) as debit,SUM(credit) as credit')->first();
            $out = ['debit' => $this->money($r->debit), 'credit' => $this->money($r->credit)];
            if ($kind !== 'journal') {
                $opening = $this->opening($f, false)['all'];
                $out['balance'] = $this->money(BigDecimal::of($opening)->plus($r->debit ?? '0')->minus($r->credit ?? '0'));
            }
            return $out;
        }
        if ($kind === 'open-items') {
            $r = (clone $data)->selectRaw("SUM(remaining_book) as book, SUM(CASE WHEN side='receivable' THEN remaining_book ELSE 0 END) as receivable, SUM(CASE WHEN side='payable' THEN remaining_book ELSE 0 END) as payable")->first();
            return ['book' => $this->money($r->book), 'receivable' => $this->money($r->receivable), 'payable' => $this->money($r->payable)];
        }
        if ($kind === 'incidents') {
            return ['incidents' => (string) (clone $data)->count(), 'open' => (string) (clone $data)->where('status', 'open')->count(), 'resolved' => (string) (clone $data)->where('status', 'resolved')->count()];
        }
        return ['documents' => (string) (clone $data)->count()];
    }

    public function build(string $kind, array $f, array $page = []): array
    {
        $query = $this->query($kind, $f);
        $total = (clone $query)->count();
        $per = max(1, min(500, (int) ($page['per_page'] ?? 100)));
        $number = max(1, (int) ($page['page'] ?? 1));
        $offset = ($number - 1) * $per;
        $rows = (clone $query)->offset($offset)->limit($per)->get()->map(fn($row) => (array) $row)->all();
        $out = ['rows' => $rows, 'total' => $total, 'page' => $number, 'per_page' => $per, 'last_page' => max(1, (int) ceil($total / $per)), 'totals' => $this->totals($kind, $query, $f)];
        if (in_array($kind, ['ledger', 'auxiliary'], true)) {
            $running = $this->opening($f, $kind === 'auxiliary');
            if ($offset) {
                $prefix = DB::query()->fromSub((clone $query)->limit($offset), 'prefix');
                $prior = $kind === 'ledger' ? [(object) ['amount' => $prefix->selectRaw('SUM(debit-credit) as amount')->value('amount') ?? '0']] : $prefix->select('account_id', 'third_party_id')->selectRaw('SUM(debit-credit) as amount')->groupBy('account_id', 'third_party_id')->get();
                foreach ($prior as $r) {
                    $key = $kind === 'ledger' ? 'all' : $this->groupKey((array) $r);
                    $running[$key] = (string) BigDecimal::of($running[$key] ?? '0')->plus($r->amount);
                }
            }
            foreach ($out['rows'] as &$row) {
                $key = $kind === 'ledger' ? 'all' : $this->groupKey($row);
                $running[$key] = (string) BigDecimal::of($running[$key] ?? '0')->plus($row['debit'])->minus($row['credit']);
                $row['balance'] = $this->money($running[$key]);
            }
            unset($row);
            $out['opening'] = $this->money($this->opening($f, false)['all']);
            if ($kind === 'ledger') {
                $out['account'] = Account::findOrFail($f['account_id']);
            } else {
                $out['opening_groups'] = $this->openingGroups($f);
                $out['opening_note'] = 'Los saldos iniciales por cuenta y tercero incluyen grupos sin movimientos en el intervalo. El saldo de cada fila acumula únicamente su propio grupo.';
            }
        }
        if (in_array($kind, ['documents', 'open-items'], true)) {
            $data = DB::query()->fromSub((clone $query)->reorder(), 'amounts');
            $out['currency_totals'] = $kind === 'documents'
                ? $data->select('currency', 'kind', 'status')->selectRaw('COUNT(*) as documents, SUM(CAST(total AS DECIMAL(32,12))) as amount')->groupBy('currency', 'kind', 'status')->get()->map(fn($row) => (array) $row)->all()
                : $data->select('currency', 'side')->selectRaw('SUM(remaining_original) as amount')->groupBy('currency', 'side')->get()->map(fn($row) => (array) $row)->all();
        }
        return $out;
    }

    /** Keyset pagination preserves complete ordering without OFFSET or a row cap. */
    public function iterate(string $kind, array $f): \Generator
    {
        $query = $this->query($kind, $f);
        $journal = in_array($kind, ['journal', 'ledger', 'auxiliary'], true);
        $running = in_array($kind, ['ledger', 'auxiliary'], true) ? $this->opening($f, $kind === 'auxiliary') : [];
        $last = null;
        do {
            $batch = clone $query;
            if ($last !== null) {
                if ($journal) {
                    $batch->whereRaw('(e.accounting_date,e.reference,l.position,l.id) > (?,?,?,?)', [$last['accounting_date'], $last['reference'], $last['position'], $last['line_id']]);
                } else {
                    $batch->where(($kind === 'documents' ? 'd' : 'i') . '.id', '>', $last['id']);
                }
            }
            $rows = $batch->limit(500)->get();
            foreach ($rows as $record) {
                $row = (array) $record;
                $last = $row;
                if ($running || in_array($kind, ['ledger', 'auxiliary'], true)) {
                    $key = $kind === 'ledger' ? 'all' : $this->groupKey($row);
                    $running[$key] = (string) BigDecimal::of($running[$key] ?? '0')->plus($row['debit'])->minus($row['credit']);
                    $row['balance'] = $this->money($running[$key]);
                }
                yield $row;
            }
        } while ($rows->count() === 500);
    }
}
