<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, YearClosingPermit};
use Modules\Accounting\Models\{Period, PeriodReview, FiscalYear, JournalEntry, SourceDocument, FiscalRecord, Incident, YearClosing};
use Modules\Accounting\Support\TenantDB as DB;

final class ClosingService
{
    public const ATTESTATIONS = ['source_reconciliation', 'cash_bank_reconciliation', 'fiscal_review'];

    public function __construct(private AuditService $audit, private OperationService $operations, private ReportService $reports)
    {
    }

    private function lockCompany(): void
    {
        $c = app(CompanyContext::class);
        $row = DB::table('accounting_companies')->where('organization_id', $c->organizationId())->where('id', $c->id())->lockForUpdate()->first();
        if (!$row) {
            throw new ApiProblem('COMPANY_NOT_FOUND', 'La empresa no está disponible.', 404);
        }
        $c->company()->setRawAttributes((array) $row, true);
    }

    private function token(array $data): string
    {
        $canonical = function ($value) use (&$canonical) {
            if (!is_array($value)) {
                return $value;
            }
            if (!array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            return array_map($canonical, $value);
        };
        return hash('sha256', json_encode($canonical($data), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function periods(int $year): array
    {
        return ['year' => $year, 'periods' => Period::where('year', $year)->orderBy('month')->get()->toArray(), 'years' => FiscalYear::orderBy('year')->get()->toArray()];
    }

    public function preview(string $id): array
    {
        return DB::transaction(function () use ($id) {
            $this->lockCompany();
            return $this->review(Period::whereKey($id)->lockForUpdate()->firstOrFail());
        }, 5);
    }

    public function annualPreview(string $id, array $excludedEntries = []): array
    {
        return DB::transaction(function () use ($id, $excludedEntries) {
            $this->lockCompany();
            return $this->review(Period::whereKey($id)->lockForUpdate()->firstOrFail(), true, $excludedEntries);
        }, 5);
    }

    /** SQL sums read the immutable, finite DECIMAL journal amounts, never formatted report figures. */
    private function trial(string $from, string $to, bool $economic = false): array
    {
        $query = $economic ? $this->reports->economicLines() : $this->reports->lines();
        $rows = $query->where('e.accounting_date', '<=', $to)
            ->select('a.id', 'a.code', 'a.name', 'a.kind', 'a.auxiliary')
            ->selectRaw('SUM(CASE WHEN e.accounting_date < ? THEN l.debit-l.credit ELSE 0 END) AS opening', [$from])
            ->selectRaw('SUM(CASE WHEN e.accounting_date >= ? THEN l.debit ELSE 0 END) AS debit', [$from])
            ->selectRaw('SUM(CASE WHEN e.accounting_date >= ? THEN l.credit ELSE 0 END) AS credit', [$from])
            ->selectRaw('SUM(l.debit-l.credit) AS balance')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.kind', 'a.auxiliary')->orderBy('a.code')->limit(10001)->get();
        if ($rows->count() > 10000) {
            throw new ApiProblem('CLOSE_SCOPE_TOO_LARGE', 'El cierre supera 10.000 cuentas con movimientos; amplía el procesamiento del servidor antes de continuar.', 413);
        }
        $debit = BigDecimal::zero();
        $credit = BigDecimal::zero();
        $balance = BigDecimal::zero();
        foreach ($rows as $row) {
            $debit = $debit->plus($row->debit);
            $credit = $credit->plus($row->credit);
            $balance = $balance->plus($row->balance);
        }
        return ['rows' => $rows->map(fn($r) => (array) $r)->all(), 'totals' => ['debit' => (string) $debit, 'credit' => (string) $credit, 'balance' => (string) $balance]];
    }

    /** Reconstruct balances at the cutoff, including applications and reversals dated after it. */
    private function auxiliaries(string $to, array $trial): array
    {
        $c = app(CompanyContext::class);
        $items = DB::table('accounting_open_items as i')
            ->join('accounting_journal_entries as e', fn($j) => $j->on('e.id', '=', 'i.origin_entry_id')->on('e.company_id', '=', 'i.company_id')->on('e.organization_id', '=', 'i.organization_id'))
            ->leftJoin('accounting_journal_entries as r', fn($j) => $j->on('r.reversal_of', '=', 'e.id')->on('r.company_id', '=', 'e.company_id')->on('r.organization_id', '=', 'e.organization_id')->whereNotNull('r.posted_at')->where('r.accounting_date', '<=', $to))
            ->where('i.company_id', $c->id())->where('i.organization_id', $c->organizationId())->whereNotNull('e.posted_at')->where('e.accounting_date', '<=', $to)->whereNull('r.id')
            ->select('i.id', 'i.account_id', 'i.side', 'i.book_amount')->orderBy('i.id')->limit(50001)->get();
        $applications = DB::table('accounting_settlement_allocations as s')
            ->join('accounting_journal_entries as e', fn($j) => $j->on('e.id', '=', 's.journal_entry_id')->on('e.company_id', '=', 's.company_id')->on('e.organization_id', '=', 's.organization_id'))
            ->leftJoin('accounting_journal_entries as r', fn($j) => $j->on('r.id', '=', 's.reversal_entry_id')->on('r.company_id', '=', 's.company_id')->on('r.organization_id', '=', 's.organization_id')->whereNotNull('r.posted_at')->where('r.accounting_date', '<=', $to))
            ->where('s.company_id', $c->id())->where('s.organization_id', $c->organizationId())->whereNotNull('e.posted_at')->where('e.accounting_date', '<=', $to)->whereNull('r.id')
            ->select('s.open_item_id')->selectRaw('SUM(s.book_amount) AS amount')->groupBy('s.open_item_id')->limit(50001)->get()->keyBy('open_item_id');
        if ($items->count() > 50000 || $applications->count() > 50000) {
            throw new ApiProblem('CLOSE_SCOPE_TOO_LARGE', 'El cierre supera 50.000 partidas históricas; amplía el procesamiento del servidor antes de continuar.', 413);
        }
        $balances = [];
        foreach ($items as $item) {
            $value = BigDecimal::of($item->book_amount)->minus($applications->get($item->id)->amount ?? '0');
            if ($item->side === 'payable') {
                $value = $value->negated();
            }
            $balances[$item->account_id] = ($balances[$item->account_id] ?? BigDecimal::zero())->plus($value);
        }
        $rows = [];
        foreach ($trial['rows'] as $row) {
            if (!in_array($row['auxiliary'], ['receivable', 'payable'], true)) {
                continue;
            }
            $aux = $balances[$row['id']] ?? BigDecimal::zero();
            $rows[] = ['account_id' => $row['id'], 'code' => $row['code'], 'name' => $row['name'], 'side' => $row['auxiliary'], 'ledger' => $row['balance'], 'auxiliary' => (string) $aux, 'difference' => (string) BigDecimal::of($row['balance'])->minus($aux)];
            unset($balances[$row['id']]);
        }
        foreach ($balances as $id => $value) {
            if (!$value->isZero()) {
                $rows[] = ['account_id' => $id, 'code' => '', 'name' => 'Partidas sin saldo de control', 'ledger' => '0', 'auxiliary' => (string) $value, 'difference' => (string) $value->negated()];
            }
        }
        return $rows;
    }

    private function review(Period $period, bool $annual = false, array $excludedEntries = []): array
    {
        $periodStart = sprintf('%04d-%02d-01', $period->year, $period->month);
        $from = $annual ? $period->year . '-01-01' : $periodStart;
        $to = CarbonImmutable::parse($periodStart, 'America/Caracas')->endOfMonth()->format('Y-m-d');
        $blockers = [];
        $company = app(CompanyContext::class)->company();
        $start = $company->migration_date ? substr((string) $company->migration_date, 0, 7) : '0000-01';
        $prior = Period::where('status', '!=', 'closed')->whereRaw("CONCAT(year, '-', LPAD(CAST(month AS text), 2, '0')) >= ?", [$start])->where(fn($q) => $q->where('year', '<', $period->year)->orWhere(fn($q) => $q->where('year', $period->year)->where('month', '<', $period->month)))->orderBy('year')->orderBy('month')->get(['id', 'year', 'month', 'version'])->toArray();
        if ($prior) {
            $blockers[] = ['code' => 'PRIOR_PERIOD_OPEN', 'message' => 'Cierra primero los períodos anteriores desde el inicio del libro.', 'count' => count($prior), 'periods' => $prior];
        }
        if ($to > now('America/Caracas')->format('Y-m-d')) {
            $blockers[] = ['code' => 'PERIOD_NOT_ENDED', 'message' => 'El período todavía no ha terminado. El cierre se habilita desde su fecha final.'];
        }
        if (FiscalYear::findOrFail($period->fiscal_year_id)->status !== 'open') {
            $blockers[] = ['code' => 'YEAR_CLOSED', 'message' => 'El ejercicio requiere su control de cierre o reapertura anual.'];
        }
        if (!$annual && $period->month === 12 && YearClosing::where('active_key', $period->fiscal_year_id)->where('status', 'prepared')->exists()) {
            $blockers[] = ['code' => 'ANNUAL_CLOSING_ACTIVE', 'message' => 'Completa el conjunto anual pendiente o retira su propuesta antes de cerrar diciembre por separado.'];
        }
        $trial = $this->trial($from, $to);
        if (!BigDecimal::of($trial['totals']['balance'])->isZero() || !BigDecimal::of($trial['totals']['debit'])->isEqualTo($trial['totals']['credit'])) {
            $blockers[] = ['code' => 'UNBALANCED_LEDGER', 'message' => 'El libro presenta una diferencia exacta entre Debe y Haber.'];
        }
        $auxiliaries = $this->auxiliaries($to, $this->trial($from, $to, true));
        $different = array_filter($auxiliaries, fn($r) => !BigDecimal::of($r['difference'])->isZero());
        if ($different) {
            $blockers[] = ['code' => 'AUXILIARY_DIFFERENCE', 'message' => 'Los auxiliares no coinciden con sus cuentas de control a la fecha de corte.', 'count' => count($different)];
        }
        $incidents = Incident::where('status', 'open')->count();
        if ($incidents) {
            $blockers[] = ['code' => 'UNRESOLVED_INCIDENTS', 'message' => 'Resuelve las incidencias de origen antes de cerrar el libro.', 'count' => $incidents];
        }
        $entries = JournalEntry::whereBetween('accounting_date', [$from, $to])->where('status', '!=', 'withdrawn')->whereNotIn('id', $excludedEntries);
        $approved = (clone $entries)->whereNull('posted_at')->where('status', 'approved')->count();
        if ($approved) {
            $blockers[] = ['code' => 'APPROVED_ENTRIES_PENDING', 'message' => 'Contabiliza u observa los comprobantes aprobados pendientes.', 'count' => $approved];
        }
        $pending = [
            'pending_entries' => ['label' => 'Comprobantes sin contabilizar', 'count' => (clone $entries)->whereNull('posted_at')->count()],
            'pending_documents' => ['label' => 'Documentos fiscales sin contabilizar', 'count' => SourceDocument::whereBetween('fiscal_date', [$from, $to])->whereNotIn('status', ['posted', 'opening_antecedent', 'excluded', 'cancelled'])->count()],
            'fiscal_observations' => ['label' => 'Registros fiscales con observaciones', 'count' => FiscalRecord::whereBetween('fiscal_period', [substr($from, 0, 7), substr($to, 0, 7)])->whereNotIn('status', ['validated', 'superseded'])->count()],
            'manual_fiscal_links' => ['label' => 'Comprobantes manuales para revisar su soporte fiscal', 'count' => (clone $entries)->whereNotNull('posted_at')->whereNull('source_document_id')->whereIn('entry_type', ['regular', 'adjustment'])->count()],
        ];
        $reports = [];
        foreach (['trial-balance', 'balance-sheet', 'income-statement'] as $kind) {
            $reports[$kind] = $this->reports->build($kind, ['from' => $from, 'to' => $to]);
            unset($reports[$kind]['generated_at']);
        }
        $review = ['schema' => 1, 'period' => $period->toArray(), 'from' => $from, 'to' => $to, 'trial' => $trial, 'auxiliaries' => $auxiliaries, 'pending' => $pending, 'blockers' => $blockers, 'reports' => $reports, 'required_attestations' => self::ATTESTATIONS];
        return [...$review, 'review_token' => $this->token($review), 'generated_at' => now()->toIso8601String()];
    }

    private function version(Period $period, int $version): void
    {
        if ($period->version !== $version) {
            throw new ApiProblem('PERIOD_VERSION_CONFLICT', 'El período cambió. Actualiza la revisión antes de continuar.', 412, ['current' => $period->toArray()]);
        }
    }

    public function close(string $id, int $version, array $input, int $user, ?string $key): array
    {
        $this->reason($input['reason'] ?? '');
        return $this->operations->execute($user, 'period.close', $key, ['id' => $id, 'version' => $version, ...$input], function () use ($id, $version, $input, $user) {
            $this->lockCompany();
            $period = Period::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->version($period, $version);
            if ($period->status !== 'open') {
                throw new ApiProblem('PERIOD_ALREADY_CLOSED', 'El período ya está cerrado.', 409);
            }
            $review = $this->review($period);
            if (!hash_equals($review['review_token'], $input['review_token'])) {
                throw new ApiProblem('CLOSE_REVIEW_CHANGED', 'Los datos cambiaron desde tu revisión. Revisa de nuevo sus controles y pendientes.', 409);
            }
            if ($review['blockers']) {
                throw new ApiProblem('CLOSE_BLOCKED', 'Resuelve los bloqueos antes de cerrar.', 409, ['blockers' => $review['blockers']]);
            }
            foreach (self::ATTESTATIONS as $name) {
                if (mb_strlen(trim($input['attestations'][$name] ?? '')) < 10) {
                    throw new ApiProblem('CLOSE_ATTESTATION_REQUIRED', 'Documenta la conciliación de origen, cajas y bancos y la revisión fiscal.');
                }
            }
            foreach ($review['pending'] as $name => $pending) {
                if ($pending['count'] && mb_strlen(trim($input['exceptions'][$name] ?? '')) < 10) {
                    throw new ApiProblem('CLOSE_EXCEPTION_REQUIRED', 'Explica el tratamiento de cada grupo de pendientes antes de cerrar.', 422, ['exception' => $name]);
                }
            }
            $snapshot = [...$review, 'reason' => $input['reason'], 'attestations' => $input['attestations'], 'exceptions' => $input['exceptions'] ?? [], 'closed_period_version' => $period->version + 1];
            $sha = $this->token($snapshot);
            $record = PeriodReview::create(['period_id' => $id, 'user_id' => $user, 'action' => 'close', 'reason' => $input['reason'], 'snapshot' => $snapshot]);
            $period->status = 'closed';
            $period->version++;
            $period->close_snapshot = ['review_id' => $record->id, 'sha256' => $sha, 'closed_at' => $record->created_at->toIso8601String(), 'report_data_version' => $review['reports']['trial-balance']['data_version']];
            $period->save();
            $this->audit->record($user, 'period.closed', 'period', $id, ['version' => $period->version, 'review_id' => $record->id, 'snapshot_sha256' => $sha, 'reason' => $input['reason']]);
            return ['period' => $period->toArray(), 'review_id' => $record->id, 'snapshot_sha256' => $sha];
        });
    }

    private function reopenReview(Period $period): array
    {
        $affected = Period::where('status', 'closed')->where(fn($q) => $q->where('year', '>', $period->year)->orWhere(fn($q) => $q->where('year', $period->year)->where('month', '>=', $period->month)))->orderBy('year')->orderBy('month')->get()->toArray();
        $years = FiscalYear::whereIn('id', array_column($affected, 'fiscal_year_id'))->where('status', '!=', 'open')->count();
        $data = ['period' => $period->toArray(), 'affected_periods' => $affected, 'annual_reopening_required' => $years > 0];
        return [...$data, 'review_token' => $this->token($data)];
    }

    public function reopening(string $id): array
    {
        return DB::transaction(function () use ($id) {
            $this->lockCompany();
            return $this->reopenReview(Period::whereKey($id)->lockForUpdate()->firstOrFail());
        }, 5);
    }

    public function reopen(string $id, int $version, array $input, int $user, ?string $key): array
    {
        $this->reason($input['reason'] ?? '');
        return $this->operations->execute($user, 'period.reopen', $key, ['id' => $id, 'version' => $version, ...$input], function () use ($id, $version, $input, $user) {
            $this->lockCompany();
            $period = Period::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->version($period, $version);
            if ($period->status !== 'closed') {
                throw new ApiProblem('PERIOD_NOT_CLOSED', 'El período ya está abierto.', 409);
            }
            $review = $this->reopenReview($period);
            if (!hash_equals($review['review_token'], $input['review_token'])) {
                throw new ApiProblem('REOPEN_REVIEW_CHANGED', 'Cambió el alcance de la reapertura. Revisa otra vez los períodos afectados.', 409);
            }
            if ($review['annual_reopening_required']) {
                throw new ApiProblem('ANNUAL_REOPENING_REQUIRED', 'La reapertura debe corregir también los comprobantes controlados de cierre anual.', 409);
            }
            $result = [];
            foreach ($review['affected_periods'] as $old) {
                $p = Period::whereKey($old['id'])->lockForUpdate()->firstOrFail();
                $record = PeriodReview::create(['period_id' => $p->id, 'user_id' => $user, 'action' => 'reopen', 'reason' => $input['reason'], 'snapshot' => ['schema' => 1, 'requested_period_id' => $id, 'previous_period' => $old, 'affected_period_ids' => array_column($review['affected_periods'], 'id'), 'new_period_version' => $p->version + 1]]);
                $p->status = 'open';
                $p->version++;
                $p->save();
                $this->audit->record($user, 'period.reopened', 'period', $p->id, ['version' => $p->version, 'review_id' => $record->id, 'previous_close' => $old['close_snapshot'], 'requested_period_id' => $id, 'reason' => $input['reason']]);
                $result[] = $p->toArray();
            }
            return ['periods' => $result];
        });
    }

    public function history(string $id): array
    {
        Period::findOrFail($id);
        return PeriodReview::where('period_id', $id)->orderByDesc('created_at')->orderByDesc('id')->get(['id', 'period_id', 'user_id', 'action', 'reason', 'created_at'])->toArray();
    }

    private function reason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 2000) {
            throw new ApiProblem('CLOSE_REASON_REQUIRED', 'Documenta el motivo, entre 10 y 2000 caracteres.');
        }
    }

    /** Called only inside the atomic posting of an approved annual bundle. */
    public function completeAnnual(YearClosing $closing, array $review, int $user, YearClosingPermit $permit): array
    {
        if ($permit->closingId !== $closing->id || $permit->direction !== 'close' || !YearClosing::whereKey($closing->id)->where('status', 'finalizing')->exists()) {
            throw new ApiProblem('YEAR_CLOSING_BATCH_REQUIRED', 'El cierre de diciembre requiere el conjunto anual autorizado.', 409);
        }
        $ids = array_values($closing->entries);
        if (JournalEntry::whereIn('id', $ids)->whereNotNull('posted_at')->count() !== count($ids)) {
            throw new ApiProblem('YEAR_CLOSING_INCOMPLETE', 'No se confirmó íntegramente el conjunto anual.', 409);
        }
        $period = Period::whereKey($review['period']['id'])->lockForUpdate()->firstOrFail();
        $this->version($period, $review['period']['version']);
        if ($period->month !== 12 || $period->fiscal_year_id !== $closing->fiscal_year_id || $period->status !== 'open' || $review['blockers']) {
            throw new ApiProblem('YEAR_CLOSING_BLOCKED', 'Los controles del ejercicio no permiten cerrar diciembre.', 409);
        }
        $snapshot = [...$review, 'scope' => 'annual_before_transfer', 'annual_closing_id' => $closing->id, 'annual_entries' => $closing->entries,
            'reason' => $closing->reason, 'attestations' => $closing->attestations, 'exceptions' => $closing->exceptions, 'closed_period_version' => $period->version + 1];
        $sha = $this->token($snapshot);
        $record = PeriodReview::create(['period_id' => $period->id, 'user_id' => $user, 'action' => 'close', 'reason' => $closing->reason, 'snapshot' => $snapshot]);
        $period->status = 'closed';
        $period->version++;
        $period->close_snapshot = ['review_id' => $record->id, 'sha256' => $sha, 'closed_at' => $record->created_at->toIso8601String(), 'report_data_version' => $review['reports']['trial-balance']['data_version'], 'annual_closing_id' => $closing->id];
        $period->save();
        $this->audit->record($user, 'period.closed', 'period', $period->id, ['version' => $period->version, 'review_id' => $record->id, 'snapshot_sha256' => $sha, 'annual_closing_id' => $closing->id, 'reason' => $closing->reason]);
        return $period->toArray();
    }

    public function snapshot(string $id, string $review): array
    {
        $record = PeriodReview::where('period_id', $id)->whereKey($review)->firstOrFail();
        return [...$record->toArray(), 'snapshot_sha256' => $this->token($record->snapshot)];
    }
}
