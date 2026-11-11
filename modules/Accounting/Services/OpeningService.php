<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Exact};
use Modules\Accounting\Models\{Account, ThirdParty, OpenItem, OpeningBatch, SourceDocument, FiscalRecord, JournalEntry};
use Modules\Accounting\Support\TenantDB as DB;
final class OpeningService
{
    public function prepare(array $input, int $user, ?int $version = null): JournalEntry
    {
        return DB::transaction(function () use ($input, $user, $version) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
            $batch = OpeningBatch::where('active_key', 'migration')->lockForUpdate()->first();
            if ($batch) {
                $old = JournalEntry::whereKey($batch->journal_entry_id)->lockForUpdate()->firstOrFail();
                if ($batch->data_hash === $hash && ($version === null || $old->posted_at)) {
                    return $old;
                }
                if ($old->posted_at) {
                    throw new ApiProblem('OPENING_ALREADY_POSTED', 'La apertura ya fue contabilizada. Su corrección requiere un reverso y una apertura de reemplazo.', 409, ['entry_id' => $old->id]);
                }
                if ($version === null) {
                    throw new ApiProblem('OPENING_EXISTS', 'Revisa la versión de la apertura antes de modificarla.', 409, ['entry_id' => $old->id, 'version' => $old->version]);
                }
                app(EntryService::class)->version($old, $version);
            }
            $cutoff = $input['cutoff_date'];
            $lines = [];
            $controls = [];
            $items = [];
            $seen = [];
            foreach ($input['balances'] as $balance) {
                $account = Account::findOrFail($balance['account_id']);
                if (!$account->posting || !$account->active) {
                    throw new ApiProblem('NON_POSTING_ACCOUNT', 'La apertura requiere cuentas activas de movimiento.');
                }
                if (isset($seen[$account->id])) {
                    throw new ApiProblem('DUPLICATE_OPENING_ACCOUNT', 'Registra un solo saldo de control por cuenta.');
                }
                $seen[$account->id] = true;
                $debit = Exact::of($balance['debit'], 2);
                $credit = Exact::of($balance['credit'], 2);
                if ($debit->isNegative() || $credit->isNegative() || $debit->isPositive() && $credit->isPositive() || $debit->isZero() && $credit->isZero()) {
                    throw new ApiProblem('INVALID_OPENING_BALANCE', 'Cada cuenta debe tener un saldo positivo en Debe o Haber.');
                }
                if (in_array($account->auxiliary, ['receivable', 'payable'], true)) {
                    $book = $account->auxiliary === 'receivable' ? $debit : $credit;
                    if (!$book->isPositive()) {
                        throw new ApiProblem('OPENING_AUXILIARY_DIRECTION', 'Revisa la clasificación de los saldos y anticipos de clientes y proveedores.');
                    }
                    $controls[$account->id] = (string) $book;
                } else {
                    $lines[] = ['account_id' => $account->id, 'third_party_id' => $balance['third_party_id'] ?? null, 'debit' => (string) $debit, 'credit' => (string) $credit, 'original_currency' => 'VES', 'description' => 'Saldo de apertura conciliado; el soporte se conserva en la propuesta.'];
                }
            }
            $totals = [];
            $identities = [];
            foreach ($input['open_items'] ?? [] as $item) {
                $account = Account::findOrFail($item['account_id']);
                $party = ThirdParty::whereKey($item['third_party_id'])->where('active', true)->firstOrFail();
                if (!array_key_exists($account->id, $controls)) {
                    throw new ApiProblem('OPENING_CONTROL_REQUIRED', 'Incluye el saldo de control de cada cuenta de clientes o proveedores.');
                }
                if ($item['issue_date'] >= $cutoff) {
                    throw new ApiProblem('OPENING_DOCUMENT_DATE', 'Las partidas iniciales deben proceder de documentos anteriores al inicio.');
                }
                $identity = $account->id . '|' . $party->id . '|' . $item['currency'] . '|' . mb_strtoupper(trim($item['reference']));
                if (isset($identities[$identity])) {
                    throw new ApiProblem('DUPLICATE_OPENING_ITEM', 'No repitas una partida inicial del mismo tercero, documento y moneda.');
                }
                $identities[$identity] = true;
                $original = Exact::of($item['original_amount'], 12);
                $book = Exact::of($item['book_amount'], 2);
                $rate = FinancialEffects::rate($item['exchange_rate']);
                if (!$original->isPositive() || !$book->isPositive() || Exact::convert((string) $original, $rate) !== (string) $book || $item['currency'] === 'VES' && !BigDecimal::of($rate)->isEqualTo(1)) {
                    throw new ApiProblem('OPENING_CONVERSION_MISMATCH', 'El saldo original, la tasa histórica y el valor contable de la partida deben conciliar.');
                }
                if ($item['exchange_date'] > $cutoff) {
                    throw new ApiProblem('FUTURE_OPENING_RATE', 'La tasa de apertura no puede ser posterior al inicio.');
                }
                if (!empty($item['source_document_id'])) {
                    $this->checkAntecedent(SourceDocument::findOrFail($item['source_document_id']), $party->id, $item['currency'], $account->auxiliary, (string) $original, $cutoff);
                }
                $partySnapshot = $party->only(['id', 'name', 'tax_id']);
                $documentSnapshot = !empty($item['source_document_id']) ? SourceDocument::findOrFail($item['source_document_id'])->toArray() : null;
                $totals[$account->id] = ($totals[$account->id] ?? BigDecimal::zero())->plus($book);
                $lines[] = FinancialEffects::line($account->id, $party->id, (string) $book, $account->auxiliary === 'receivable', 'Partida inicial ' . $item['reference'] . '; su liquidación no repetirá la operación histórica.', $item['currency'], (string) $original, $rate, $item['reference']);
                $items[] = [...$item, 'original_amount' => (string) $original, 'book_amount' => (string) $book, 'side' => $account->auxiliary, 'exchange_rate' => $rate, 'party_snapshot' => $partySnapshot, 'source_snapshot' => $documentSnapshot];
            }
            foreach ($controls as $account => $amount) {
                if (!($totals[$account] ?? BigDecimal::zero())->isEqualTo($amount)) {
                    throw new ApiProblem('OPENING_AUXILIARY_MISMATCH', 'El detalle de clientes y proveedores no concilia con el saldo de control.', 422, ['account_id' => $account, 'control' => $amount, 'detail' => (string) ($totals[$account] ?? BigDecimal::zero())]);
                }
            }
            $effect = ['kind' => 'opening', 'cutoff_date' => $cutoff, 'input' => $input, 'open_items' => $items, 'lines' => $lines, 'evidence_reference' => $input['evidence_reference']];
            $data = ['accounting_date' => $cutoff, 'concept' => 'Apertura al ' . $cutoff . ' · ' . $input['reason'], 'entry_type' => 'opening', 'lines' => $lines, 'metadata' => ['financial_effect' => $effect]];
            if (isset($old)) {
                app(AuditService::class)->record($user, 'opening.proposal_replaced', 'journal_entry', $old->id, ['prior_entry' => app(EntryService::class)->resource($old)]);
                $entry = app(EntryService::class)->edit($old->id, $data, $old->version, $user);
                $entry->metadata = [...$data['metadata'], 'prepared_by' => $user];
                $entry->save();
                $batch->cutoff_date = $cutoff;
                $batch->data_hash = $hash;
                $batch->version++;
                $batch->save();
            } else {
                $entry = app(EntryService::class)->create($data, $user);
                $batch = OpeningBatch::create(['journal_entry_id' => $entry->id, 'cutoff_date' => $cutoff, 'active_key' => 'migration', 'data_hash' => $hash]);
            }
            app(AuditService::class)->record($user, 'opening.proposed', 'opening_batch', $batch->id, ['journal_entry_id' => $entry->id, 'cutoff_date' => $cutoff, 'items' => count($items)]);
            return $entry;
        }, 5);
    }
    private function checkAntecedent(SourceDocument $document, string $party, string $currency, string $side, string $original, string $cutoff): void
    {
        if ($document->third_party_id !== $party || $document->currency !== $currency || $document->kind !== ($side === 'receivable' ? 'sale_invoice' : 'purchase_invoice') || $document->issue_date->format('Y-m-d') >= $cutoff || BigDecimal::of($original)->isGreaterThan($document->amounts['total'])) {
            throw new ApiProblem('INVALID_OPENING_ANTECEDENT', 'El antecedente debe ser una factura anterior al inicio, del mismo tercero y moneda, suficiente para el saldo inicial.');
        }
        if (JournalEntry::where('source_document_id', $document->id)->whereNotNull('posted_at')->exists()) {
            throw new ApiProblem('OPENING_HISTORY_ALREADY_POSTED', 'El documento histórico ya tiene efectos contables. Concílialos antes de cargar la apertura.');
        }
    }
    public function validate(JournalEntry $entry, array $effect): void
    {
        $batch = OpeningBatch::where('journal_entry_id', $entry->id)->firstOrFail();
        if ($batch->status !== 'draft' || $entry->accounting_date->format('Y-m-d') !== $effect['cutoff_date']) {
            throw new ApiProblem('OPENING_STATE', 'Revisa la fecha y el estado de la apertura.');
        }
        if (JournalEntry::whereNotNull('posted_at')->whereDate('accounting_date', '<', $effect['cutoff_date'])->exists()) {
            throw new ApiProblem('OPENING_HISTORY_ALREADY_POSTED', 'Existen movimientos contables anteriores al inicio. Concílialos antes de cargar la apertura.');
        }
        foreach ($effect['open_items'] as $item) {
            if (!empty($item['source_document_id'])) {
                $document = SourceDocument::whereKey($item['source_document_id'])->lockForUpdate()->firstOrFail();
                if ($document->version !== ($item['source_snapshot']['version'] ?? null)) {
                    throw new ApiProblem('OPENING_ANTECEDENT_CHANGED', 'El antecedente cambió. Regenera la propuesta de apertura y revisa su evidencia.');
                }
                $this->checkAntecedent($document, $item['third_party_id'], $item['currency'], $item['side'], $item['original_amount'], $effect['cutoff_date']);
            }
        }
    }
    public function apply(JournalEntry $entry, array $effect): void
    {
        $batch = OpeningBatch::where('journal_entry_id', $entry->id)->lockForUpdate()->firstOrFail();
        foreach ($effect['open_items'] as $item) {
            OpenItem::create([...array_intersect_key($item, array_flip(['third_party_id', 'account_id', 'source_document_id', 'side', 'reference', 'issue_date', 'due_date', 'currency', 'original_amount', 'book_amount'])), 'opening_entry_id' => $entry->id, 'origin_entry_id' => $entry->id, 'remaining_original' => $item['original_amount'], 'remaining_book' => $item['book_amount'], 'is_opening' => true]);
            if (!empty($item['source_document_id'])) {
                $this->markAntecedent(SourceDocument::findOrFail($item['source_document_id']), $entry->id, $effect['cutoff_date']);
            }
        }
        $company = app(CompanyContext::class)->company();
        $company->migration_date = $effect['cutoff_date'];
        $company->save();
        $batch->status = 'posted';
        $batch->version++;
        $batch->save();
    }
    private function markAntecedent(SourceDocument $document, string $entry, string $cutoff): void
    {
        $document->metadata = [...$document->metadata ?? [], 'opening_antecedent' => ['entry_id' => $entry, 'cutoff_date' => $cutoff]];
        $document->status = 'opening_antecedent';
        $document->version++;
        $document->save();
        FiscalRecord::where('source_document_id', $document->id)->whereDate('event_date', '<', $cutoff)->whereIn('status', ['validated', 'source_changed'])->update(['status' => 'opening_antecedent']);
    }
    public function linkAntecedent(string $itemId, string $documentId, int $version, int $user, string $reason): OpenItem
    {
        return DB::transaction(function () use ($itemId, $documentId, $version, $user, $reason) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            $item = OpenItem::whereKey($itemId)->lockForUpdate()->firstOrFail();
            if ($item->version !== $version) {
                throw new ApiProblem('VERSION_CONFLICT', 'La partida cambió. Revisa su versión actual.', 412);
            }
            if (!$item->is_opening || $item->status === 'reversed') {
                throw new ApiProblem('NOT_AN_OPENING_ITEM', 'Selecciona una partida de apertura vigente.');
            }
            if ($item->source_document_id && $item->source_document_id !== $documentId) {
                throw new ApiProblem('ANTECEDENT_ALREADY_LINKED', 'Conserva el antecedente enlazado y revisa su corrección.');
            }
            $document = SourceDocument::whereKey($documentId)->lockForUpdate()->firstOrFail();
            $cutoff = JournalEntry::findOrFail($item->opening_entry_id)->accounting_date->format('Y-m-d');
            $this->checkAntecedent($document, $item->third_party_id, $item->currency, $item->side, $item->original_amount, $cutoff);
            $item->source_document_id = $document->id;
            $item->version++;
            $item->save();
            $this->markAntecedent($document, $item->opening_entry_id, $cutoff);
            app(AuditService::class)->record($user, 'opening.antecedent_linked', 'open_item', $item->id, ['document_id' => $documentId, 'reason' => $reason, 'version' => $item->version]);
            return $item;
        }, 5);
    }
}
