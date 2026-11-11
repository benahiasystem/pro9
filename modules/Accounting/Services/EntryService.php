<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Exact};
use Modules\Accounting\Models\{Account, ThirdParty, CostCenter, Period, JournalEntry, JournalLine, EntryReview, SourceDocument, OpenItem};
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Accounting\Support\TenantDB as DB;
use Illuminate\Support\Str;
final class EntryService
{
    public function __construct(private AuditService $audit, private OperationService $operations)
    {
    }
    public function period(string $date): Period
    {
        $d = \Carbon\CarbonImmutable::parse($date, 'America/Caracas');
        $p = Period::where('year', $d->year)->where('month', $d->month)->first();
        if (!$p) {
            throw new ApiProblem('PERIOD_NOT_CONFIGURED', 'Configura el ejercicio y el período antes de guardar.');
        }
        return $p;
    }
    public function create(array $data, int $user): JournalEntry
    {
        return DB::transaction(function () use ($data, $user) {
            $period = $this->period($data['accounting_date']);
            $lines = $this->normalize($data['lines']);
            $entry = JournalEntry::create(['period_id' => $period->id, 'accounting_date' => $data['accounting_date'], 'concept' => $data['concept'], 'entry_type' => $data['entry_type'] ?? 'regular', 'created_by' => $user, 'source_document_id' => $data['source_document_id'] ?? null, 'source_revision' => $data['source_revision'] ?? null, 'effect_type' => $data['effect_type'] ?? 'document', 'local_id' => $data['local_id'] ?? null, 'metadata' => [...$data['metadata'] ?? [], 'prepared_by' => $user], 'rule_snapshot' => $data['rule_snapshot'] ?? null, 'reason' => $data['reason'] ?? null, 'reversal_of' => $data['reversal_of'] ?? null, 'total_debit' => Exact::total(array_column($lines, 'debit'), 8), 'total_credit' => Exact::total(array_column($lines, 'credit'), 8), 'status' => $period->status === 'closed' ? 'observed' : 'draft']);
            foreach ($lines as $i => $line) {
                JournalLine::create([...$line, 'journal_entry_id' => $entry->id, 'position' => $i + 1]);
            }
            $this->audit->record($user, 'entry.created', 'journal_entry', $entry->id, ['version' => 1, 'period_status' => $period->status]);
            return $entry;
        }, 5);
    }
    public function edit(string $id, array $data, int $version, int $user): JournalEntry
    {
        return DB::transaction(function () use ($id, $data, $version, $user) {
            $entry = JournalEntry::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->editable($entry);
            $this->version($entry, $version);
            $lines = $this->normalize($data['lines']);
            $period = $this->period($data['accounting_date']);
            $before = $entry->version;
            $entry->fill(['period_id' => $period->id, 'accounting_date' => $data['accounting_date'], 'concept' => $data['concept'], 'version' => $before + 1, 'status' => $period->status === 'closed' ? 'observed' : 'draft', 'approved_by' => null, 'approved_at' => null, 'approved_version' => null, 'approval_reason' => null, 'metadata' => [...$entry->metadata ?? [], 'prepared_by' => $user], 'total_debit' => Exact::total(array_column($lines, 'debit'), 8), 'total_credit' => Exact::total(array_column($lines, 'credit'), 8)]);
            $entry->save();
            JournalLine::where('journal_entry_id', $id)->delete();
            foreach ($lines as $i => $line) {
                JournalLine::create([...$line, 'journal_entry_id' => $entry->id, 'position' => $i + 1]);
            }
            $this->audit->record($user, 'entry.updated', 'journal_entry', $id, ['before_version' => $before, 'version' => $entry->version, 'approval_invalidated' => true]);
            return $entry;
        }, 5);
    }
    public function transition(string $id, int $version, int $user, string $action, string $comment = ''): JournalEntry
    {
        return DB::transaction(function () use ($id, $version, $user, $action, $comment) {
            $sourceId = JournalEntry::whereKey($id)->value('source_document_id');
            if ($sourceId) {
                SourceDocument::whereKey($sourceId)->lockForUpdate()->firstOrFail();
            }
            $e = JournalEntry::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->editable($e);
            $this->version($e, $version);
            if ($action === 'submit') {
                if (!in_array($e->status, ['draft', 'observed'], true)) {
                    throw new ApiProblem('INVALID_ENTRY_STATE', 'Solo un borrador o comprobante observado se envía a revisión.');
                }
                $this->validatePosting($e, false);
                $e->status = 'review';
            } elseif ($action === 'observe') {
                if (!in_array($e->status, ['review', 'approved'], true)) {
                    throw new ApiProblem('INVALID_ENTRY_STATE', 'El comprobante no está en revisión.');
                }
                if (trim($comment) === '') {
                    throw new ApiProblem('COMMENT_REQUIRED', 'Indica la observación.');
                }
                $e->status = 'observed';
                $e->approved_by = null;
                $e->approved_at = null;
                $e->approved_version = null;
            } elseif ($action === 'approve') {
                if ($e->status !== 'review') {
                    throw new ApiProblem('REVIEW_REQUIRED', 'Envía el comprobante a revisión antes de aprobarlo.');
                }
                $this->validatePosting($e, true);
                if ((int) ($e->metadata['prepared_by'] ?? $e->created_by) === $user) {
                    if (app(CompanyContext::class)->company()->approval_mode !== 'self_control') {
                        throw new ApiProblem('SEPARATE_APPROVAL_REQUIRED', 'Otra persona autorizada debe aprobar este comprobante.');
                    }
                    if (trim($comment) === '') {
                        throw new ApiProblem('SELF_CONTROL_REASON_REQUIRED', 'Documenta el motivo del autocontrol para aprobar tu propio comprobante.');
                    }
                }
                $e->status = 'approved';
                $e->approved_by = $user;
                $e->approved_at = now();
                $e->approved_version = $e->version + 1;
                $e->approval_reason = $comment;
            } else {
                throw new ApiProblem('UNKNOWN_TRANSITION', 'La acción no está disponible.');
            }
            $e->version++;
            $e->save();
            EntryReview::create(['journal_entry_id' => $e->id, 'user_id' => $user, 'entry_version' => $e->version, 'action' => $action, 'comment' => $comment]);
            $this->audit->record($user, 'entry.' . $action, 'journal_entry', $id, ['version' => $e->version, 'comment' => $comment]);
            return $e;
        }, 5);
    }
    public function post(string $id, int $version, int $user, ?string $key): array
    {
        return $this->operations->execute($user, 'entry.post', $key, ['id' => $id, 'version' => $version], function () use ($id, $version, $user) {
            $context = app(CompanyContext::class);
            $company = DB::table('accounting_companies')->where('id', $context->id())->lockForUpdate()->first();
            $context->company()->setRawAttributes((array) $company, true);
            $hint = JournalEntry::findOrFail($id);
            if ($hint->source_document_id) {
                SourceDocument::whereKey($hint->source_document_id)->lockForUpdate()->firstOrFail();
            }
            $period = Period::whereKey($hint->period_id)->lockForUpdate()->firstOrFail();
            $e = JournalEntry::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($e->period_id !== $period->id) {
                throw new ApiProblem('PERIOD_CHANGED', 'La fecha del comprobante cambió. Revisa la versión actual.', 409);
            }
            $this->version($e, $version);
            if ($e->posted_at) {
                throw new ApiProblem('ALREADY_POSTED', 'El comprobante ya fue contabilizado.', 409);
            }
            if ($period->status === 'closed') {
                throw new ApiProblem('PERIOD_CLOSED', 'El período está cerrado. Revisa la fecha o solicita una reapertura autorizada.', 409);
            }
            if ($e->status !== 'approved' || $e->approved_version !== $e->version) {
                throw new ApiProblem('APPROVAL_REQUIRED', 'Se requiere la aprobación de la versión actual.');
            }
            $this->validatePosting($e, true);
            $c = app(CompanyContext::class);
            $now = now()->toDateTimeString();
            DB::table('accounting_journal_sequences')->insertOrIgnore(['id' => (string) Str::uuid(), 'organization_id' => $c->organizationId(), 'company_id' => $c->id(), 'year' => $period->year, 'series' => 'CG', 'last_number' => 0, 'created_at' => $now, 'updated_at' => $now]);
            $seq = DB::table('accounting_journal_sequences')->where('company_id', $c->id())->where('year', $period->year)->where('series', 'CG')->lockForUpdate()->first();
            $n = $seq->last_number + 1;
            DB::table('accounting_journal_sequences')->where('id', $seq->id)->update(['last_number' => $n, 'updated_at' => $now]);
            $e->reference = 'CG-' . $period->year . '-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
            $e->status = 'posted';
            $e->posted_at = now();
            $e->posted_by = $user;
            $e->version++;
            $e->save();
            if ($e->reversal_of) {
                $this->reverseEffects($e);
            } else {
                $this->createOpenItem($e);
                app(FinancialEffects::class)->apply($e);
            }
            if ($e->source_document_id) {
                $source = SourceDocument::findOrFail($e->source_document_id);
                $source->status = 'posted';
                $source->version++;
                $source->save();
            }
            $this->audit->record($user, 'entry.posted', 'journal_entry', $id, ['reference' => $e->reference, 'version' => $e->version, 'approved_version' => $e->approved_version, 'debit' => $e->total_debit, 'credit' => $e->total_credit, 'period_version' => $period->version]);
            return $this->resource($e);
        });
    }
    public function prepareReversal(string $id, string $date, string $reason, int $user): JournalEntry
    {
        return DB::transaction(function () use ($id, $date, $reason, $user) {
            $e = JournalEntry::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($e->reversal_of) {
                throw new ApiProblem('REVERSAL_OF_REVERSAL', 'Conserva el reverso. La corrección requiere un reemplazo vinculado al comprobante original.');
            }
            if (!$e->posted_at) {
                throw new ApiProblem('NOT_POSTED', 'Solo se puede reversar un comprobante contabilizado.');
            }
            if ($old = JournalEntry::where('reversal_of', $id)->first()) {
                throw new ApiProblem('REVERSAL_EXISTS', 'Este comprobante ya tiene un reverso preparado o contabilizado.', 409, ['reversal_id' => $old->id]);
            }
            if (trim($reason) === '') {
                throw new ApiProblem('REASON_REQUIRED', 'Indica el motivo del reverso.');
            }
            $period = $this->period($date);
            if ($period->status === 'closed') {
                throw new ApiProblem('PERIOD_CLOSED', 'Elige una fecha de un período abierto.');
            }
            $lines = $e->lines()->get()->map(fn($l) => ['account_id' => $l->account_id, 'third_party_id' => $l->third_party_id, 'cost_center_id' => $l->cost_center_id, 'debit' => $l->credit, 'credit' => $l->debit, 'description' => $l->description, 'document_reference' => $l->document_reference, 'original_currency' => $l->original_currency, 'original_amount' => $l->original_amount, 'exchange_rate' => $l->exchange_rate])->all();
            $reverse = $this->create(['accounting_date' => $date, 'concept' => 'Reverso de ' . $e->reference . ' · ' . $reason, 'entry_type' => 'reversal', 'reason' => $reason, 'reversal_of' => $id, 'lines' => $lines], $user);
            $this->audit->record($user, 'entry.reversal_prepared', 'journal_entry', $reverse->id, ['original' => $id, 'reason' => $reason]);
            return $reverse;
        }, 5);
    }
    public function version(JournalEntry $e, int $version): void
    {
        if ($e->version !== $version) {
            throw new ApiProblem('VERSION_CONFLICT', 'Otra persona modificó este comprobante. Conserva tu trabajo y compara las versiones.', 412, ['current' => $this->resource($e)]);
        }
    }
    private function editable(JournalEntry $e): void
    {
        if ($e->posted_at) {
            throw new ApiProblem('IMMUTABLE_ENTRY', 'El comprobante contabilizado se conserva íntegro. Prepara un reverso para corregirlo.', 409);
        }
    }
    private function normalize(array $lines): array
    {
        $out = [];
        foreach ($lines as $i => $l) {
            $account = Account::find($l['account_id']);
            if (!$account) {
                throw new ApiProblem('INVALID_ACCOUNT', 'La cuenta de la línea ' . ($i + 1) . ' no pertenece a esta empresa.');
            }
            if (!empty($l['third_party_id']) && !ThirdParty::whereKey($l['third_party_id'])->exists()) {
                throw new ApiProblem('INVALID_PARTY', 'El tercero no pertenece a esta empresa.');
            }
            if (!empty($l['cost_center_id']) && !CostCenter::whereKey($l['cost_center_id'])->exists()) {
                throw new ApiProblem('INVALID_COST_CENTER', 'El centro de costo no pertenece a esta empresa.');
            }
            $debit = Exact::of($l['debit']);
            $credit = Exact::of($l['credit']);
            if ($debit->isNegative() || $credit->isNegative() || $debit->isPositive() && $credit->isPositive()) {
                throw new ApiProblem('INVALID_LINE', 'Cada línea debe tener un importe positivo en Debe o Haber.');
            }
            $out[] = ['account_id' => $account->id, 'third_party_id' => $l['third_party_id'] ?? null, 'cost_center_id' => $l['cost_center_id'] ?? null, 'description' => $l['description'] ?? null, 'document_reference' => $l['document_reference'] ?? null, 'debit' => (string) $debit, 'credit' => (string) $credit, 'original_currency' => $l['original_currency'] ?? 'VES', 'original_amount' => isset($l['original_amount']) ? (string) Exact::of($l['original_amount'], 12) : null, 'exchange_rate' => isset($l['exchange_rate']) ? (string) Exact::of($l['exchange_rate'], 12) : null];
        }
        return $out;
    }
    public function validatePosting(JournalEntry $e, bool $requireOpenPeriod): void
    {
        if ($e->source_document_id) {
            $source = SourceDocument::whereKey($e->source_document_id)->lockForUpdate()->firstOrFail();
            app(Pro9SourceReader::class)->assertCurrent($source);
            if (!in_array($source->status, ['validated', 'proposed'], true) || $source->source_revision !== $e->source_revision) {
                throw new ApiProblem('SOURCE_REVALIDATION_REQUIRED', 'El documento de origen requiere validar su versión actual antes de contabilizar.');
            }
            $validation = $source->metadata['validation'] ?? null;
            if (!$validation || !$e->rule_snapshot || $validation != $e->rule_snapshot) {
                throw new ApiProblem('SOURCE_SNAPSHOT_MISMATCH', 'El asiento debe conservar la validación fiscal de esta versión del documento. Prepara nuevamente su propuesta.');
            }
        }
        $cutoff = app(CompanyContext::class)->company()->migration_date;
        if ($cutoff && $e->entry_type !== 'opening' && $e->accounting_date->format('Y-m-d') < substr((string) $cutoff, 0, 10)) {
            throw new ApiProblem('BEFORE_MIGRATION_DATE', 'La contabilización requiere una fecha desde el inicio del libro. Conserva los documentos anteriores como antecedentes de apertura.');
        }
        if ($cutoff && isset($source) && $source->issue_date->format('Y-m-d') < substr((string) $cutoff, 0, 10)) {
            throw new ApiProblem('OPENING_ANTECEDENT', 'El documento anterior al inicio se enlaza con la partida inicial y no genera otra venta o compra.');
        }
        $lines = $e->lines()->get();
        if (isset($source) && in_array($source->kind, ['sale_invoice', 'purchase_invoice'], true)) {
            $this->validateInvoiceAuxiliary($source, $lines);
        }
        if ($lines->count() < 2) {
            throw new ApiProblem('LINES_REQUIRED', 'El comprobante necesita al menos dos líneas.');
        }
        if ($requireOpenPeriod && Period::findOrFail($e->period_id)->status === 'closed') {
            throw new ApiProblem('PERIOD_CLOSED', 'El período está cerrado.');
        }
        foreach ($lines as $l) {
            $a = Account::find($l->account_id);
            if (!$a || !$a->active || !$a->posting) {
                throw new ApiProblem('NON_POSTING_ACCOUNT', 'Usa cuentas activas de movimiento en todas las líneas.');
            }
            if ($a->requires_party && !$l->third_party_id) {
                throw new ApiProblem('PARTY_REQUIRED', 'La cuenta ' . $a->code . ' requiere tercero.');
            }
            if ($l->third_party_id && !ThirdParty::whereKey($l->third_party_id)->where('active', true)->exists()) {
                throw new ApiProblem('INACTIVE_PARTY', 'El tercero no está activo.');
            }
            if (Exact::of($l->debit)->isZero() && Exact::of($l->credit)->isZero()) {
                throw new ApiProblem('ZERO_LINE', 'Elimina las líneas sin importe antes de enviar a revisión.');
            }
            try {
                Exact::of($l->debit)->toScale(2, RoundingMode::UNNECESSARY);
                Exact::of($l->credit)->toScale(2, RoundingMode::UNNECESSARY);
            } catch (\Throwable) {
                throw new ApiProblem('BOOK_SCALE_REQUIRED', 'Los importes del libro VES deben expresarse a dos decimales; documenta cualquier diferencia de redondeo.');
            }
            if ($l->original_currency !== 'VES') {
                if ($l->original_amount === null || $l->exchange_rate === null || !Exact::of($l->original_amount, 12)->isPositive() || !Exact::of($l->exchange_rate, 12)->isPositive()) {
                    throw new ApiProblem('EXCHANGE_RATE_REQUIRED', 'Conserva el importe original y una tasa positiva en cada línea en divisas.');
                }
                $converted = Exact::convert($l->original_amount, $l->exchange_rate);
                if (Exact::of($converted)->compareTo(Exact::of($l->debit)->plus($l->credit)) !== 0) {
                    throw new ApiProblem('CONVERSION_MISMATCH', 'La conversión de la línea no coincide con el importe registrado en VES.');
                }
            }
        }
        if (Exact::of($e->total_debit)->compareTo(Exact::of($e->total_credit)) !== 0) {
            throw new ApiProblem('UNBALANCED_ENTRY', 'El comprobante no está balanceado. Revisa la diferencia entre Debe y Haber.');
        }
        if (Exact::of($e->total_debit)->isZero()) {
            throw new ApiProblem('ZERO_ENTRY', 'El comprobante debe contener un importe mayor que cero.');
        }
        app(FinancialEffects::class)->validate($e, $lines);
    }
    private function validateInvoiceAuxiliary(SourceDocument $document, mixed $lines): void
    {
        $side = $document->kind === 'sale_invoice' ? 'receivable' : 'payable';
        $auxiliary = $lines->filter(fn($line) => in_array(Account::findOrFail($line->account_id)->auxiliary, ['receivable', 'payable'], true));
        $line = $auxiliary->first();
        if ($auxiliary->count() !== 1 || Account::findOrFail($line->account_id)->auxiliary !== $side || $line->third_party_id !== $document->third_party_id || !Exact::of($side === 'receivable' ? $line->debit : $line->credit)->isEqualTo($document->fiscal_amounts['total']) || !Exact::of($side === 'receivable' ? $line->credit : $line->debit)->isZero() || $line->original_currency !== $document->currency || $line->original_amount === null || $line->exchange_rate === null || !Exact::of($line->original_amount, 12)->isEqualTo($document->amounts['total']) || !Exact::of($line->exchange_rate, 12)->isEqualTo($document->exchange_rate)) {
            throw new ApiProblem('INVOICE_AUXILIARY_MISMATCH', 'La factura requiere una sola partida de cliente o proveedor por su total, tercero, moneda y tasa validados. Revisa la propuesta antes de aprobarla.');
        }
    }
    private function createOpenItem(JournalEntry $e): void
    {
        if (!$e->source_document_id) {
            return;
        }
        $doc = SourceDocument::findOrFail($e->source_document_id);
        if (!in_array($doc->kind, ['sale_invoice', 'purchase_invoice'], true)) {
            return;
        }
        $side = $doc->kind === 'sale_invoice' ? 'receivable' : 'payable';
        foreach ($e->lines()->get() as $l) {
            $a = Account::findOrFail($l->account_id);
            if ($a->auxiliary !== $side) {
                continue;
            }
            $book = $side === 'receivable' ? $l->debit : $l->credit;
            if (Exact::of($book)->isZero()) {
                continue;
            }
            if (OpenItem::where('source_document_id', $doc->id)->where('account_id', $a->id)->where('status', '!=', 'reversed')->exists()) {
                return;
            }
            OpenItem::create(['third_party_id' => $l->third_party_id, 'account_id' => $a->id, 'source_document_id' => $doc->id, 'origin_entry_id' => $e->id, 'side' => $side, 'reference' => $doc->number, 'issue_date' => $doc->issue_date->format('Y-m-d'), 'due_date' => $doc->due_date, 'currency' => $doc->currency, 'original_amount' => $doc->amounts['total'], 'book_amount' => $book, 'remaining_original' => $doc->amounts['total'], 'remaining_book' => $book]);
        }
    }
    private function reverseEffects(JournalEntry $reverse): void
    {
        $allocations = \Modules\Accounting\Models\SettlementAllocation::where('journal_entry_id', $reverse->reversal_of)->whereNull('reversal_entry_id')->lockForUpdate()->get();
        foreach ($allocations as $a) {
            $item = OpenItem::whereKey($a->open_item_id)->lockForUpdate()->firstOrFail();
            $item->remaining_original = (string) Exact::of($item->remaining_original, 12)->plus($a->original_amount);
            $item->remaining_book = (string) Exact::of($item->remaining_book)->plus($a->book_amount);
            $item->status = 'open';
            $item->version++;
            $item->save();
            $a->reversal_entry_id = $reverse->id;
            $a->save();
        }
        $source = JournalEntry::findOrFail($reverse->reversal_of);
        $items = OpenItem::where('origin_entry_id', $source->id)->lockForUpdate()->get();
        foreach ($items as $item) {
            if (!Exact::of($item->remaining_original, 12)->isEqualTo($item->original_amount) || !Exact::of($item->remaining_book)->isEqualTo($item->book_amount)) {
                throw new ApiProblem('SETTLED_SOURCE', 'Resuelve primero las aplicaciones de cobro o pago de la partida.');
            }
            $item->remaining_original = '0';
            $item->remaining_book = '0';
            $item->status = 'reversed';
            $item->version++;
            $item->save();
        }
        foreach ([\Modules\Accounting\Models\OpeningBatch::class, \Modules\Accounting\Models\Settlement::class] as $model) {
            $record = $model::where('journal_entry_id', $source->id)->lockForUpdate()->first();
            if ($record) {
                $record->status = 'reversed';
                $record->active_key = null;
                $record->version++;
                $record->save();
            }
        }
    }
    public function resource(JournalEntry $e): array
    {
        $lines = $e->lines()->get();
        $accounting_accounts = Account::whereIn('id', $lines->pluck('account_id'))->get()->keyBy('id');
        $parties = ThirdParty::whereIn('id', $lines->pluck('third_party_id')->filter())->get()->keyBy('id');
        $reverse = JournalEntry::where('reversal_of', $e->id)->first();
        return [...$e->toArray(), 'display_status' => $reverse?->posted_at ? 'reversed' : $e->status, 'reversal' => $reverse?->only(['id', 'reference', 'status', 'posted_at']), 'lines' => $lines->map(fn($l) => [...$l->toArray(), 'account_code' => $accounting_accounts[$l->account_id]->code ?? '', 'account_name' => $accounting_accounts[$l->account_id]->name ?? '', 'party_name' => $parties[$l->third_party_id]->name ?? null])->all()];
    }
}
