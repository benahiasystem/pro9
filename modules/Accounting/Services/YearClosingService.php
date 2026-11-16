<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Exact, YearClosingPermit};
use Modules\Accounting\Models\{Account, CostCenter, FiscalYear, JournalEntry, Period, ThirdParty, YearClosing};
use Modules\Accounting\Support\TenantDB as DB;

final class YearClosingService
{
    private const PHASES = ['result', 'balance', 'opening'];
    public function __construct(private AuditService $audit, private OperationService $operations, private ClosingService $monthly, private ReportService $reports)
    {
    }

    private function lockBook(): void
    {
        $c = app(CompanyContext::class);
        $row = DB::table('accounting_companies')->where('id', $c->id())->where('organization_id', $c->organizationId())->lockForUpdate()->first();
        if (!$row) {
            throw new ApiProblem('COMPANY_NOT_FOUND', 'La empresa no está disponible.', 404);
        }
        $c->company()->setRawAttributes((array) $row, true);
    }

    private function hash(array $value): string
    {
        $ordered = function ($v) use (&$ordered) {
            if (!is_array($v)) {
                return $v;
            }
            if (!array_is_list($v)) {
                ksort($v, SORT_STRING);
            }
            return array_map($ordered, $v);
        };
        return hash('sha256', json_encode($ordered($value), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new ApiProblem('YEAR_CLOSING_VERSION_CONFLICT', 'El ejercicio o su cierre cambiaron. Actualiza la revisión antes de continuar.', 412);
        }
    }

    private function reason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 2000) {
            throw new ApiProblem('YEAR_CLOSING_REASON_REQUIRED', 'Explica el motivo, entre 10 y 2000 caracteres.');
        }
    }

    private function resultAccount(?string $id): ?Account
    {
        return $id ? Account::findOrFail($id) : Account::where('auxiliary', 'current_result')->where('active', true)->where('posting', true)->orderBy('code')->first();
    }

    private function groups(string $to): array
    {
        $rows = $this->reports->lines()->where('e.accounting_date', '<=', $to)
            ->select('a.id as account_id', 'a.code', 'a.name', 'a.kind', 'a.active', 'a.posting', 'a.requires_party', 'a.version', 'l.third_party_id', 'l.cost_center_id')
            ->selectRaw('SUM(l.debit-l.credit) AS balance')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.kind', 'a.active', 'a.posting', 'a.requires_party', 'a.version', 'l.third_party_id', 'l.cost_center_id')
            ->havingRaw('SUM(l.debit-l.credit) <> 0')->orderBy('a.code')->orderBy('l.third_party_id')->orderBy('l.cost_center_id')->limit(10001)->get();
        if ($rows->count() > 10000) {
            throw new ApiProblem('YEAR_CLOSING_TOO_LARGE', 'El traslado supera 10.000 saldos por cuenta, tercero y centro de costo. Amplía el procesamiento antes de continuar.', 413);
        }
        return $rows->map(fn($row) => (array) $row)->all();
    }

    private function line(array $row, BigDecimal $signed, string $description): array
    {
        return ['account_id' => $row['account_id'], 'third_party_id' => $row['third_party_id'] ?? null, 'cost_center_id' => $row['cost_center_id'] ?? null,
            'debit' => (string) ($signed->isPositive() ? $signed : BigDecimal::zero())->toScale(8),
            'credit' => (string) ($signed->isNegative() ? $signed->negated() : BigDecimal::zero())->toScale(8),
            'description' => $description, 'document_reference' => null, 'original_currency' => 'VES', 'original_amount' => null, 'exchange_rate' => null];
    }

    private function proposal(array $rows, Account $account, int $year): array
    {
        $result = [];
        $balances = [];
        $pl = BigDecimal::zero();
        foreach ($rows as $row) {
            $amount = BigDecimal::of($row['balance']);
            if (in_array($row['kind'], ['income', 'cost', 'expense'], true)) {
                $result[] = $this->line($row, $amount->negated(), 'Determinación del resultado de ' . $year);
                $pl = $pl->plus($amount);
            } else {
                $balances[$row['account_id'] . '|' . ($row['third_party_id'] ?? '') . '|' . ($row['cost_center_id'] ?? '')] = $row;
            }
        }
        if (!$pl->isZero()) {
            $target = ['account_id' => $account->id, 'third_party_id' => null, 'cost_center_id' => null];
            $result[] = $this->line($target, $pl, 'Incorporación del resultado de ' . $year);
            $key = $account->id . '||';
            $balances[$key] = [...$target, 'balance' => (string) $pl->plus($balances[$key]['balance'] ?? '0')];
        }
        $close = [];
        $opening = [];
        foreach ($balances as $row) {
            $amount = BigDecimal::of($row['balance']);
            if ($amount->isZero()) {
                continue;
            }
            $close[] = $this->line($row, $amount->negated(), 'Cierre de saldos de ' . $year);
            $opening[] = $this->line($row, $amount, 'Apertura del ejercicio ' . ($year + 1));
        }
        $phases = [];
        foreach (['result' => $result, 'balance' => $close, 'opening' => $opening] as $phase => $lines) {
            if (!$lines) {
                continue;
            }
            $debit = Exact::total(array_column($lines, 'debit'), 8);
            $credit = Exact::total(array_column($lines, 'credit'), 8);
            if (!BigDecimal::of($debit)->isEqualTo($credit) || BigDecimal::of($debit)->isZero()) {
                throw new ApiProblem('YEAR_CLOSING_UNBALANCED', 'El comprobante controlado no cuadra. Revisa las cuentas y los saldos antes de continuar.');
            }
            Exact::of($debit, 8);
            $phases[$phase] = ['accounting_date' => $phase === 'opening' ? ($year + 1) . '-01-01' : $year . '-12-31', 'entry_type' => $phase === 'opening' ? 'opening' : 'closing',
                'lines' => $lines, 'total_debit' => $debit, 'total_credit' => $credit];
        }
        return ['result' => (string) $pl->negated(), 'result_account' => $account->only(['id', 'code', 'name', 'kind', 'active', 'posting', 'requires_party', 'version']), 'phases' => $phases,
            'auxiliary_note' => 'El traslado resume el libro en VES. Las partidas originales conservan moneda, tasas, aplicaciones e historia; la apertura anual no crea nuevas deudas.'];
    }

    private function basis(FiscalYear $year, ?Account $account, array $excluded = []): array
    {
        $period = Period::where('fiscal_year_id', $year->id)->where('month', 12)->firstOrFail();
        $review = $this->monthly->annualPreview($period->id, $excluded);
        $opening = Period::where('year', $year->year + 1)->where('month', 1)->first();
        $blockers = $review['blockers'];
        if ($period->status !== 'open') {
            $blockers[] = ['code' => 'DECEMBER_NOT_OPEN', 'message' => 'Diciembre debe permanecer abierto para los comprobantes de cierre anual.'];
        }
        if ($opening && $opening->status !== 'open') {
            $blockers[] = ['code' => 'OPENING_PERIOD_CLOSED', 'message' => 'Enero del siguiente ejercicio debe estar abierto para recibir su apertura controlada.'];
        }
        if ($year->year >= 2100) {
            $blockers[] = ['code' => 'NEXT_YEAR_OUT_OF_RANGE', 'message' => 'El siguiente ejercicio supera el intervalo de configuración disponible.'];
        }
        $priorYears = FiscalYear::where('year', '<', $year->year)->where('status', '!=', 'closed')->get();
        foreach ($priorYears as $prior) {
            if (JournalEntry::whereBetween('accounting_date', [$prior->year . '-01-01', $prior->year . '-12-31'])->whereNotNull('posted_at')->exists()) {
                $blockers[] = ['code' => 'PRIOR_YEAR_NOT_CLOSED', 'message' => 'Completa primero el cierre anual del ejercicio ' . $prior->year . '.'];
            }
        }
        if (!$account || $account->kind !== 'equity' || !$account->active || !$account->posting || $account->requires_party) {
            $blockers[] = ['code' => 'RESULT_ACCOUNT_REQUIRED', 'message' => 'Elige una cuenta activa de patrimonio y movimiento que no requiera tercero para incorporar el resultado.'];
        }
        $rows = $this->groups($year->year . '-12-31');
        $parties = ThirdParty::whereIn('id', array_values(array_filter(array_column($rows, 'third_party_id'))))->orderBy('id')->get(['id', 'name', 'active', 'version'])->keyBy('id');
        $centers = CostCenter::whereIn('id', array_values(array_filter(array_column($rows, 'cost_center_id'))))->orderBy('id')->get(['id', 'code', 'name', 'active'])->keyBy('id');
        foreach ($rows as $row) {
            if (!$row['active'] || !$row['posting'] || !in_array($row['kind'], ['asset', 'liability', 'equity', 'income', 'cost', 'expense'], true) || $row['requires_party'] && !$row['third_party_id']) {
                $blockers[] = ['code' => 'INVALID_CLOSING_ACCOUNT', 'message' => 'Revisa la cuenta ' . $row['code'] . ': debe admitir movimientos y conservar sus datos obligatorios.'];
            }
            if ($row['third_party_id'] && !$parties->get($row['third_party_id'])?->active || $row['cost_center_id'] && !$centers->get($row['cost_center_id'])?->active) {
                $blockers[] = ['code' => 'INACTIVE_CLOSING_DIMENSION', 'message' => 'Una partida requiere revisar su tercero o centro de costo inactivo.'];
            }
        }
        $review['blockers'] = $blockers;
        $proposal = $account && $account->kind === 'equity' && $account->active && $account->posting && !$account->requires_party ? $this->proposal($rows, $account, $year->year) : null;
        $data = ['fiscal_year' => $year->toArray(), 'opening_period' => $opening?->toArray(), 'review' => $review, 'groups' => $rows, 'parties' => $parties->values()->toArray(), 'cost_centers' => $centers->values()->toArray(), 'proposal' => $proposal,
            'approval_mode' => app(CompanyContext::class)->company()->approval_mode];
        $data['basis_hash'] = $this->basisHash($data);
        return $data;
    }

    private function basisHash(array $basis): string
    {
        unset($basis['basis_hash'], $basis['review']['review_token'], $basis['review']['generated_at']);
        foreach ($basis['review']['reports'] as &$report) {
            // The report's download token follows the audit cursor, including
            // approvals and exports. It is not an economic change to this basis.
            unset($report['data_version'], $report['review_token']);
        }
        unset($report);
        return $this->hash($basis);
    }

    public function screen(int $number, ?string $accountId = null): array
    {
        return DB::transaction(function () use ($number, $accountId) {
            $this->lockBook();
            $year = FiscalYear::where('year', $number)->firstOrFail();
            $active = YearClosing::where('active_key', $year->id)->first();
            return ['fiscal_year' => $year->toArray(), 'preview' => $year->status === 'open' ? $this->basis($year, $this->resultAccount($accountId ?? $active?->result_account_id), array_values($active?->entries ?? [])) : null,
                'active' => $active ? $this->resource($active) : null, 'history' => YearClosing::where('fiscal_year_id', $year->id)->orderByDesc('created_at')->get(['id', 'status', 'version', 'reason', 'created_at', 'posted_at', 'reopened_at'])->toArray()];
        }, 5);
    }

    private function checkNotes(array $review, array $input): void
    {
        $this->reason($input['reason'] ?? '');
        foreach (ClosingService::ATTESTATIONS as $key) {
            if (mb_strlen(trim($input['attestations'][$key] ?? '')) < 10 || mb_strlen($input['attestations'][$key]) > 2000) {
                throw new ApiProblem('CLOSE_ATTESTATION_REQUIRED', 'Documenta las conciliaciones y la revisión fiscal del ejercicio.');
            }
        }
        foreach ($review['pending'] as $key => $pending) {
            if ($pending['count'] && mb_strlen(trim($input['exceptions'][$key] ?? '')) < 10) {
                throw new ApiProblem('CLOSE_EXCEPTION_REQUIRED', 'Explica el tratamiento de cada grupo de pendientes del ejercicio.', 422, ['exception' => $key]);
            }
        }
    }

    public function prepare(int $number, int $version, array $input, int $user, ?string $key): array
    {
        return $this->operations->execute($user, 'year.prepare', $key, ['year' => $number, 'version' => $version, ...$input], function () use ($number, $version, $input, $user) {
            $this->lockBook();
            $year = FiscalYear::where('year', $number)->lockForUpdate()->firstOrFail();
            $this->version($year->version, $version);
            if (YearClosing::where('active_key', $year->id)->exists()) {
                throw new ApiProblem('YEAR_CLOSING_EXISTS', 'El ejercicio ya tiene un conjunto de cierre. Consúltalo o retira su propuesta antes de preparar otra.', 409);
            }
            $basis = $this->basis($year, $this->resultAccount($input['result_account_id']));
            if (!hash_equals($basis['basis_hash'], $input['basis_hash'])) {
                throw new ApiProblem('YEAR_CLOSING_BASIS_CHANGED', 'Los saldos o controles cambiaron. Actualiza la revisión del ejercicio.', 409);
            }
            if ($basis['review']['blockers']) {
                throw new ApiProblem('YEAR_CLOSING_BLOCKED', 'Resuelve los bloqueos antes de preparar el cierre anual.', 409, ['blockers' => $basis['review']['blockers']]);
            }
            $this->checkNotes($basis['review'], $input);
            if (!$basis['opening_period']) {
                app(CompanyService::class)->createYear($number + 1);
                $basis = $this->basis($year, $this->resultAccount($input['result_account_id']));
            }
            $record = YearClosing::create(['fiscal_year_id' => $year->id, 'active_key' => $year->id, 'result_account_id' => $input['result_account_id'], 'prepared_by' => $user, 'reason' => $input['reason'], 'attestations' => $input['attestations'], 'exceptions' => $input['exceptions'] ?? [], 'basis' => $basis, 'basis_hash' => $basis['basis_hash'], 'proposal' => $basis['proposal'], 'entries' => [], 'status' => 'building']);
            $ids = [];
            foreach (self::PHASES as $phase) {
                $data = $record->proposal['phases'][$phase] ?? null;
                if (!$data) {
                    continue;
                }
                $entry = app(EntryService::class)->create([...$data, 'concept' => $this->phaseName($phase) . ' · Ejercicio ' . $number, 'reason' => $input['reason'], 'effect_type' => 'annual_' . $phase,
                    'metadata' => ['financial_effect' => ['kind' => 'year_close', 'closing_id' => $record->id, 'phase' => $phase, 'lines' => $data['lines']]]], $user);
                $ids[$phase] = $entry->id;
            }
            $record->entries = $ids;
            $record->status = 'prepared';
            $record->save();
            $this->audit->record($user, 'year.closing_prepared', 'year_closing', $record->id, ['year' => $number, 'basis_hash' => $record->basis_hash, 'entries' => $ids, 'reason' => $input['reason']]);
            return $this->resource($record);
        });
    }

    public function phaseName(string $phase): string
    {
        return ['result' => 'Resultado del ejercicio', 'balance' => 'Cierre de saldos', 'opening' => 'Apertura del siguiente ejercicio'][$phase] ?? $phase;
    }

    private function entrySummaries(array $ids): array
    {
        $rows = JournalEntry::whereIn('id', array_values($ids))->get()->keyBy('id');
        $result = [];
        foreach (self::PHASES as $phase) {
            $id = $ids[$phase] ?? null;
            if (!$id) {
                continue;
            }
            $entry = $rows->get($id);
            if (!$entry) {
                throw new ApiProblem('YEAR_CLOSING_ENTRY_MISSING', 'Falta un comprobante del conjunto anual. No se puede continuar.', 409);
            }
            $summary = array_intersect_key($entry->toArray(), array_flip(['id', 'reference', 'accounting_date', 'concept', 'status', 'version', 'created_by', 'approved_by', 'approved_version', 'approval_reason', 'posted_at', 'total_debit', 'total_credit']));
            $result[$phase] = [...$summary, 'phase' => $phase, 'title' => $this->phaseName($phase)];
        }
        return $result;
    }

    private function approved(array $entries): bool
    {
        foreach ($entries as $entry) {
            if ($entry['status'] !== 'approved' || $entry['approved_version'] !== $entry['version'] || $entry['posted_at']) {
                return false;
            }
        }
        return true;
    }

    private function reopeningReview(YearClosing $record): array
    {
        $year = FiscalYear::findOrFail($record->fiscal_year_id);
        $later = FiscalYear::where('year', '>', $year->year)->where('status', 'closed')->orderByDesc('year')->get(['id', 'year', 'version'])->toArray();
        $periods = Period::where('status', 'closed')->where('year', '>=', $year->year)->orderBy('year')->orderBy('month')->get()->toArray();
        return ['fiscal_year' => $year->toArray(), 'affected_periods' => $periods, 'later_closed_years' => $later, 'entries' => $this->entrySummaries($record->reopening_entries ?? [])];
    }

    public function resource(YearClosing $record): array
    {
        $entries = $this->entrySummaries($record->entries);
        $basis = $record->status === 'prepared' ? $this->basis(FiscalYear::findOrFail($record->fiscal_year_id), Account::findOrFail($record->result_account_id), array_values($record->entries)) : null;
        $same = $basis ? hash_equals($record->basis_hash, $basis['basis_hash']) : null;
        $reopening = in_array($record->status, ['posted', 'reopening'], true) ? $this->reopeningReview($record) : null;
        $reviewToken = $this->hash(['id' => $record->id, 'version' => $record->version, 'status' => $record->status, 'basis_hash' => $basis['basis_hash'] ?? null, 'entries' => $entries, 'reopening' => $reopening]);
        return [...$record->toArray(), 'entries' => $entries, 'basis_matches' => $same, 'current_review' => $basis['review'] ?? null, 'review_token' => $reviewToken,
            'posted_snapshot_sha256' => $record->posted_snapshot ? $this->hash($record->posted_snapshot) : null,
            'reopened_snapshot_sha256' => $record->reopened_snapshot ? $this->hash($record->reopened_snapshot) : null,
            'can_post' => $record->status === 'prepared' && $same && !$basis['review']['blockers'] && $this->approved($entries),
            'reopening_review' => $reopening, 'can_reopen' => $record->status === 'reopening' && !$reopening['later_closed_years'] && $this->approved($reopening['entries'])];
    }

    public function show(string $id): array
    {
        return DB::transaction(function () use ($id) {
            $this->lockBook();
            return $this->resource(YearClosing::findOrFail($id));
        }, 5);
    }

    private function reviewToken(YearClosing $record, string $token): array
    {
        $resource = $this->resource($record);
        if (!hash_equals($resource['review_token'], $token)) {
            throw new ApiProblem('YEAR_CLOSING_REVIEW_CHANGED', 'El conjunto o su alcance cambiaron. Revisa otra vez antes de confirmar.', 409);
        }
        return $resource;
    }

    public function post(string $id, int $version, string $token, int $user, ?string $key): array
    {
        return $this->operations->execute($user, 'year.close', $key, ['id' => $id, 'version' => $version, 'review_token' => $token], function () use ($id, $version, $token, $user) {
            $this->lockBook();
            $record = YearClosing::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->version($record->version, $version);
            if ($record->status !== 'prepared') {
                throw new ApiProblem('YEAR_CLOSING_STATE', 'Este conjunto ya no está pendiente de contabilizar.', 409);
            }
            $resource = $this->reviewToken($record, $token);
            if (!$resource['can_post']) {
                throw new ApiProblem('YEAR_CLOSING_NOT_READY', 'Revisa los saldos y aprueba la versión vigente de todos los comprobantes del conjunto.', 409);
            }
            $year = FiscalYear::whereKey($record->fiscal_year_id)->lockForUpdate()->firstOrFail();
            $permit = new YearClosingPermit($id, 'close');
            $record->status = 'finalizing';
            $record->save();
            foreach (self::PHASES as $phase) {
                $entry = $resource['entries'][$phase] ?? null;
                if (!$entry) {
                    continue;
                }
                app(EntryService::class)->post($entry['id'], $entry['version'], $user, 'annual:' . $id . ':' . $phase, $permit);
            }
            if ($this->groups($year->year . '-12-31')) {
                throw new ApiProblem('YEAR_CLOSING_NONZERO', 'El cierre no dejó saldos contables nulos en el ejercicio. No se guardó ninguna contabilización.', 409);
            }
            $closedPeriod = $this->monthly->completeAnnual($record, $resource['current_review'], $user, $permit);
            $year->status = 'closed';
            $year->version++;
            $year->result_entry_id = $record->entries['result'] ?? null;
            $year->closing_entry_id = $record->entries['balance'] ?? null;
            $year->opening_entry_id = $record->entries['opening'] ?? null;
            $year->save();
            $record->status = 'posted';
            $record->version++;
            $record->posted_by = $user;
            $record->posted_at = now();
            $record->posted_snapshot = ['schema' => 1, 'fiscal_year' => $year->toArray(), 'period' => $closedPeriod, 'entries' => $this->entrySummaries($record->entries), 'closing_balances_zero' => true, 'auxiliary_effect' => 'preserved_without_new_open_items'];
            $record->save();
            $this->audit->record($user, 'year.closed', 'year_closing', $id, ['year' => $year->year, 'version' => $record->version, 'entries' => $record->entries, 'period_review_id' => $closedPeriod['close_snapshot']['review_id'], 'snapshot_sha256' => $this->hash($record->posted_snapshot)]);
            return $this->resource($record);
        });
    }

    public function cancel(string $id, int $version, string $reason, int $user, ?string $key): array
    {
        $this->reason($reason);
        return $this->operations->execute($user, 'year.cancel', $key, ['id' => $id, 'version' => $version, 'reason' => $reason], function () use ($id, $version, $reason, $user) {
            $this->lockBook();
            $record = YearClosing::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->version($record->version, $version);
            if ($record->status !== 'prepared') {
                throw new ApiProblem('YEAR_CLOSING_STATE', 'Solo puede retirarse un conjunto que todavía no se ha contabilizado.', 409);
            }
            foreach (JournalEntry::whereIn('id', array_values($record->entries))->lockForUpdate()->get() as $entry) {
                if ($entry->posted_at) {
                    throw new ApiProblem('YEAR_CLOSING_ALREADY_POSTED', 'Un comprobante del conjunto ya tiene efecto contable.', 409);
                }
                $entry->fill(['status' => 'withdrawn', 'version' => $entry->version + 1, 'approved_by' => null, 'approved_at' => null, 'approved_version' => null, 'approval_reason' => null, 'metadata' => [...$entry->metadata, 'withdrawn_year_closing_reason' => $reason]]);
                $entry->save();
            }
            $record->status = 'cancelled';
            $record->version++;
            $record->active_key = null;
            $record->withdrawal_reason = $reason;
            $record->withdrawn_by = $user;
            $record->save();
            $this->audit->record($user, 'year.closing_withdrawn', 'year_closing', $id, ['reason' => $reason, 'entries' => $record->entries]);
            return $this->resource($record);
        });
    }

    public function prepareReopening(string $id, int $version, string $reason, int $user, ?string $key): array
    {
        $this->reason($reason);
        return $this->operations->execute($user, 'year.reopening.prepare', $key, ['id' => $id, 'version' => $version, 'reason' => $reason], function () use ($id, $version, $reason, $user) {
            $this->lockBook();
            $record = YearClosing::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->version($record->version, $version);
            if ($record->status !== 'posted') {
                throw new ApiProblem('YEAR_CLOSING_STATE', 'Consulta el cierre contabilizado para preparar su reapertura.', 409);
            }
            $review = $this->reopeningReview($record);
            if ($review['later_closed_years']) {
                throw new ApiProblem('LATER_YEAR_CLOSED', 'Reabre primero los ejercicios posteriores, desde el más reciente.', 409, ['years' => $review['later_closed_years']]);
            }
            $ids = [];
            foreach ($record->entries as $phase => $originalId) {
                $original = JournalEntry::findOrFail($originalId);
                $lines = $original->lines()->orderBy('position')->get()->map(fn($line) => [...$line->only(['account_id', 'third_party_id', 'cost_center_id', 'description', 'document_reference', 'original_currency', 'original_amount', 'exchange_rate']), 'debit' => $line->credit, 'credit' => $line->debit])->all();
                $entry = app(EntryService::class)->create(['accounting_date' => $original->accounting_date->format('Y-m-d'), 'concept' => 'Reapertura anual · Reverso de ' . $original->reference, 'entry_type' => 'reversal', 'effect_type' => 'annual_reopen_' . $phase, 'reason' => $reason, 'reversal_of' => $originalId, 'lines' => $lines,
                    'metadata' => ['financial_effect' => ['kind' => 'year_reopen', 'closing_id' => $id, 'phase' => $phase, 'lines' => $lines]]], $user);
                $ids[$phase] = $entry->id;
            }
            $record->status = 'reopening';
            $record->version++;
            $record->reopening_prepared_by = $user;
            $record->reopening_reason = $reason;
            $record->reopening_entries = $ids;
            $record->save();
            $this->audit->record($user, 'year.reopening_prepared', 'year_closing', $id, ['reason' => $reason, 'entries' => $ids, 'affected_periods' => array_column($review['affected_periods'], 'id')]);
            return $this->resource($record);
        });
    }

    public function reopen(string $id, int $version, string $token, int $user, ?string $key): array
    {
        return $this->operations->execute($user, 'year.reopen', $key, ['id' => $id, 'version' => $version, 'review_token' => $token], function () use ($id, $version, $token, $user) {
            $this->lockBook();
            $record = YearClosing::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->version($record->version, $version);
            $resource = $this->reviewToken($record, $token);
            if (!$resource['can_reopen']) {
                throw new ApiProblem('YEAR_REOPENING_NOT_READY', 'Aprueba los reversos vigentes y revisa los ejercicios posteriores antes de reabrir.', 409);
            }
            $record->status = 'reopen_finalizing';
            $record->save();
            $year = FiscalYear::whereKey($record->fiscal_year_id)->lockForUpdate()->firstOrFail();
            $year->status = 'open';
            $year->version++;
            $year->result_entry_id = $year->closing_entry_id = $year->opening_entry_id = null;
            $year->save();
            $affected = $resource['reopening_review']['affected_periods'];
            $periodResult = [];
            if ($affected) {
                $first = $affected[0];
                $review = $this->monthly->reopening($first['id']);
                $periodResult = $this->monthly->reopen($first['id'], $first['version'], ['review_token' => $review['review_token'], 'reason' => $record->reopening_reason], $user, 'annual-reopen-periods:' . $id)['periods'];
            }
            $permit = new YearClosingPermit($id, 'reopen');
            foreach (array_reverse($resource['reopening_review']['entries'], true) as $phase => $entry) {
                app(EntryService::class)->post($entry['id'], $entry['version'], $user, 'annual-reopen:' . $id . ':' . $phase, $permit);
            }
            $record->status = 'reopened';
            $record->version++;
            $record->active_key = null;
            $record->reopened_by = $user;
            $record->reopened_at = now();
            $record->reopened_snapshot = ['schema' => 1, 'fiscal_year' => $year->toArray(), 'periods' => $periodResult, 'entries' => $this->entrySummaries($record->reopening_entries), 'previous_close_sha256' => $this->hash($record->posted_snapshot)];
            $record->save();
            $this->audit->record($user, 'year.reopened', 'year_closing', $id, ['version' => $record->version, 'entries' => $record->reopening_entries, 'reason' => $record->reopening_reason, 'snapshot_sha256' => $this->hash($record->reopened_snapshot)]);
            return $this->resource($record);
        });
    }

    public function canReviewClosedReopening(JournalEntry $entry): bool
    {
        $effect = $entry->metadata['financial_effect'] ?? [];
        $record = ($effect['kind'] ?? null) === 'year_reopen' ? YearClosing::find($effect['closing_id'] ?? '') : null;
        return $record && $record->status === 'reopening' && ($record->reopening_entries[$effect['phase'] ?? ''] ?? null) === $entry->id && ($record->entries[$effect['phase'] ?? ''] ?? null) === $entry->reversal_of;
    }

    public function validate(JournalEntry $entry, array $effect, mixed $lines, ?YearClosingPermit $permit): void
    {
        $record = YearClosing::findOrFail($effect['closing_id']);
        $phase = $effect['phase'];
        if (!in_array($phase, self::PHASES, true)) {
            throw new ApiProblem('YEAR_CLOSING_CONTROLLED', 'La fase no pertenece al conjunto anual.', 409);
        }
        $reopening = $effect['kind'] === 'year_reopen';
        $ids = $reopening ? $record->reopening_entries : $record->entries;
        $allowed = $reopening ? ['reopening', 'reopen_finalizing'] : ['prepared', 'finalizing'];
        if (!in_array($record->status, $allowed, true) || ($ids[$phase] ?? null) !== $entry->id) {
            throw new ApiProblem('YEAR_CLOSING_CONTROLLED', 'El comprobante pertenece a un conjunto anual retirado o fuera de su estado de revisión.', 409);
        }
        $expected = $record->proposal['phases'][$phase];
        if ($entry->accounting_date->format('Y-m-d') !== $expected['accounting_date'] || $entry->entry_type !== ($reopening ? 'reversal' : $expected['entry_type'])) {
            throw new ApiProblem('YEAR_CLOSING_LINES_CHANGED', 'La fecha y el tipo deben coincidir con el conjunto anual revisado.');
        }
        $wanted = $expected['lines'];
        if ($reopening) {
            if ($entry->reversal_of !== $record->entries[$phase]) {
                throw new ApiProblem('YEAR_CLOSING_LINES_CHANGED', 'El reverso no corresponde al comprobante controlado original.');
            }
            $wanted = array_map(fn($line) => [...$line, 'debit' => $line['credit'], 'credit' => $line['debit']], $wanted);
        }
        FinancialEffects::sameLines($lines, $wanted, 'YEAR_CLOSING_LINES_CHANGED');
        $centers = fn($rows) => collect($rows)->map(fn($line) => json_encode([is_array($line) ? $line['account_id'] : $line->account_id, is_array($line) ? ($line['third_party_id'] ?? null) : $line->third_party_id, is_array($line) ? ($line['cost_center_id'] ?? null) : $line->cost_center_id, (string) Exact::of(is_array($line) ? $line['debit'] : $line->debit), (string) Exact::of(is_array($line) ? $line['credit'] : $line->credit)]))->sort()->values()->all();
        if ($centers($lines) !== $centers($wanted)) {
            throw new ApiProblem('YEAR_CLOSING_LINES_CHANGED', 'Los terceros y centros de costo deben conservarse en el traslado.');
        }
        if ($entry->status === 'approved' && (!$permit || $permit->closingId !== $record->id || $permit->direction !== ($reopening ? 'reopen' : 'close') || $record->status !== ($reopening ? 'reopen_finalizing' : 'finalizing'))) {
            throw new ApiProblem('YEAR_CLOSING_BATCH_REQUIRED', 'Confirma el conjunto completo desde Cierre anual. Sus comprobantes no se contabilizan por separado.', 409);
        }
    }
}
