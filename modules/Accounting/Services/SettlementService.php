<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Exact};
use Modules\Accounting\Models\{Account, ThirdParty, OpenItem, Settlement, SettlementAllocation, JournalEntry};
use Modules\Accounting\Support\TenantDB as DB;
final class SettlementService
{
    public function prepare(array $input, int $user, ?int $version = null): JournalEntry
    {
        return DB::transaction(function () use ($input, $user, $version) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            $cash = Account::findOrFail($input['cash_account_id']);
            if (!$cash->active || !$cash->posting || !in_array($cash->auxiliary, ['cash', 'bank'], true)) {
                throw new ApiProblem('CASH_ACCOUNT_REQUIRED', 'Selecciona una cuenta activa de caja o banco.');
            }
            $party = ThirdParty::whereKey($input['third_party_id'])->where('active', true)->firstOrFail();
            $rate = FinancialEffects::rate($input['exchange_rate']);
            if ($input['currency'] === 'VES' && !BigDecimal::of($rate)->isEqualTo(1)) {
                throw new ApiProblem('VES_RATE_REQUIRED', 'Una liquidación en VES utiliza tasa uno.');
            }
            $original = Exact::of($input['amount'], 12);
            if (!$original->isPositive()) {
                throw new ApiProblem('POSITIVE_SETTLEMENT_REQUIRED', 'El importe del cobro o pago debe ser positivo.');
            }
            $cashBook = Exact::convert((string) $original, $rate);
            if (!BigDecimal::of($cashBook)->isPositive()) {
                throw new ApiProblem('SETTLEMENT_PRECISION', 'El cobro o pago debe tener un importe representable en el libro VES.');
            }
            $reference = trim($input['reference']);
            $key = hash('sha256', $input['direction'] . '|' . $cash->id . '|' . $input['accounting_date'] . '|' . mb_strtoupper($reference));
            $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
            $record = !empty($input['entry_id']) ? Settlement::where('journal_entry_id', $input['entry_id'])->lockForUpdate()->firstOrFail() : Settlement::where('active_key', $key)->lockForUpdate()->first();
            if (Settlement::where('active_key', $key)->when($record, fn($q) => $q->where('id', '!=', $record->id))->exists()) {
                throw new ApiProblem('SETTLEMENT_REFERENCE_EXISTS', 'La referencia ya pertenece a otra liquidación.', 409);
            }
            if ($record) {
                $old = JournalEntry::whereKey($record->journal_entry_id)->lockForUpdate()->firstOrFail();
                if ($record->data_hash === $hash && ($version === null || $old->posted_at)) {
                    return $old;
                }
                if ($old->posted_at) {
                    throw new ApiProblem('SETTLEMENT_ALREADY_POSTED', 'La referencia ya corresponde a una liquidación contabilizada. Prepara un reverso enlazado para corregirla.', 409, ['entry_id' => $old->id]);
                }
                if ($version === null) {
                    throw new ApiProblem('SETTLEMENT_REFERENCE_EXISTS', 'Ya existe una propuesta para esa referencia. Revisa su versión antes de reemplazarla.', 409, ['entry_id' => $old->id, 'version' => $old->version]);
                }
                app(EntryService::class)->version($old, $version);
            }
            $receipt = $input['direction'] === 'receipt';
            $side = $receipt ? 'receivable' : 'payable';
            $allocations = [];
            $total = BigDecimal::zero();
            $lines = [];
            $seen = [];
            $applications = $input['applications'];
            usort($applications, fn($a, $b) => strcmp($a['open_item_id'], $b['open_item_id']));
            foreach ($applications as $application) {
                $id = $application['open_item_id'];
                if (isset($seen[$id])) {
                    throw new ApiProblem('DUPLICATE_APPLICATION', 'No repitas la misma partida en una liquidación.');
                }
                $seen[$id] = true;
                $item = OpenItem::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($item->status !== 'open' || $item->side !== $side || $item->third_party_id !== $party->id) {
                    throw new ApiProblem('OPEN_ITEM_NOT_AVAILABLE', 'La partida debe estar abierta y corresponder al tercero y al tipo de liquidación.');
                }
                $amount = Exact::of($application['original_amount'], 12);
                $remaining = Exact::of($item->remaining_original, 12);
                if (!$amount->isPositive() || $amount->isGreaterThan($remaining)) {
                    throw new ApiProblem('OVER_APPLICATION', 'El importe aplicado supera el saldo original de una partida.');
                }
                $book = $amount->isEqualTo($remaining) ? BigDecimal::of($item->remaining_book)->toScale(2, RoundingMode::UNNECESSARY) : BigDecimal::of($item->remaining_book)->multipliedBy($amount)->dividedBy($remaining, 2, RoundingMode::HALF_UP);
                if (!$book->isPositive()) {
                    throw new ApiProblem('APPLICATION_PRECISION', 'Agrupa un importe mayor o documenta un ajuste de redondeo del auxiliar.');
                }
                $measurement = $item->currency === $input['currency'] ? $rate : FinancialEffects::rate($application['measurement_rate'] ?? '0');
                if ($item->currency === 'VES' && !BigDecimal::of($measurement)->isEqualTo(1)) {
                    throw new ApiProblem('VES_RATE_REQUIRED', 'La medición de una partida VES utiliza tasa uno.');
                }
                $value = BigDecimal::of(Exact::convert((string) $amount, $measurement));
                $total = $total->plus($value);
                $difference = $value->minus($book);
                $account = Account::findOrFail($item->account_id);
                if (!$account->active || !$account->posting || $account->auxiliary !== $side) {
                    throw new ApiProblem('AUXILIARY_ACCOUNT_REQUIRED', 'Revisa la cuenta de movimiento de la partida.');
                }
                $lines[] = FinancialEffects::line($account->id, $party->id, (string) $book, !$receipt, 'Cancelación de la partida ' . $item->reference . ' por su valor contable.', $item->currency, (string) $amount, FinancialEffects::carryingRate((string) $book, (string) $amount, $item->currency), $item->reference);
                if (!$difference->isZero()) {
                    $gain = $receipt ? $difference->isPositive() : $difference->isNegative();
                    $fxId = $input[$gain ? 'gain_account_id' : 'loss_account_id'] ?? null;
                    $fx = $fxId ? Account::find($fxId) : null;
                    if (!$fx || !$fx->active || !$fx->posting || $fx->auxiliary !== ($gain ? 'exchange_gain' : 'exchange_loss')) {
                        throw new ApiProblem('FX_ACCOUNT_REQUIRED', 'Selecciona la cuenta de ganancia o pérdida por diferencia de cambio que corresponda.');
                    }
                    $lines[] = FinancialEffects::line($fx->id, $party->id, (string) $difference->abs(), !$gain, 'Diferencia entre la medición de liquidación y el saldo contable de ' . $item->reference . '.');
                }
                $allocations[] = ['open_item_id' => $id, 'version' => $item->version, 'remaining_original' => $item->remaining_original, 'remaining_book' => $item->remaining_book, 'original_amount' => (string) $amount, 'book_amount' => (string) $book, 'settlement_amount' => (string) $value, 'exchange_difference' => (string) $difference, 'measurement_rate' => $measurement, 'reference' => $item->reference, 'currency' => $item->currency];
            }
            if (!$total->isEqualTo($cashBook)) {
                throw new ApiProblem('SETTLEMENT_TOTAL_MISMATCH', 'La medición de las aplicaciones no coincide con el movimiento bancario. Revisa importes, tasas y las retenciones o tributos que requieran su flujo específico.');
            }
            array_unshift($lines, FinancialEffects::line($cash->id, $party->id, $cashBook, $receipt, $receipt ? 'Ingreso bancario por cobro; no reconoce otra venta.' : 'Salida bancaria por pago; no reconoce otra compra.', $input['currency'], (string) $original, $rate, $reference));
            $effect = ['kind' => 'settlement', 'accounting_date' => $input['accounting_date'], 'input' => $input, 'reference_hash' => $key, 'allocations' => $allocations, 'lines' => $lines, 'rate_evidence' => ['source' => $input['exchange_source'], 'date' => $input['exchange_date'], 'reference' => $input['evidence_reference'], 'reason' => $input['rate_reason']], 'tax_review_reference' => $input['tax_review_reference']];
            $data = ['accounting_date' => $input['accounting_date'], 'concept' => ($receipt ? 'Cobro ' : 'Pago ') . $reference . ' · ' . $party->name, 'lines' => $lines, 'metadata' => ['financial_effect' => $effect]];
            if (isset($old)) {
                app(AuditService::class)->record($user, 'settlement.proposal_replaced', 'journal_entry', $old->id, ['prior_entry' => app(EntryService::class)->resource($old)]);
                $entry = app(EntryService::class)->edit($old->id, $data, $old->version, $user);
                $entry->metadata = [...$data['metadata'], 'prepared_by' => $user];
                $entry->save();
                $record->data_hash = $hash;
                $record->reference_hash = $key;
                $record->active_key = $key;
                $record->version++;
                $record->save();
            } else {
                $entry = app(EntryService::class)->create($data, $user);
                $record = Settlement::create(['journal_entry_id' => $entry->id, 'reference_hash' => $key, 'active_key' => $key, 'data_hash' => $hash]);
            }
            app(AuditService::class)->record($user, 'settlement.proposed', 'settlement', $record->id, ['journal_entry_id' => $entry->id, 'reference' => $reference, 'applications' => $allocations, 'bank_book_amount' => $cashBook]);
            return $entry;
        }, 5);
    }
    public function validate(JournalEntry $entry, array $effect): void
    {
        if ($entry->accounting_date->format('Y-m-d') !== $effect['accounting_date']) {
            throw new ApiProblem('SETTLEMENT_DATE_CHANGED', 'Cambia la fecha y sus tasas desde la propuesta de liquidación.');
        }
        $record = Settlement::where('journal_entry_id', $entry->id)->firstOrFail();
        if ($record->status !== 'draft') {
            throw new ApiProblem('SETTLEMENT_STATE', 'La liquidación ya no está pendiente de contabilizar.');
        }
        foreach ($effect['allocations'] as $allocation) {
            $item = OpenItem::whereKey($allocation['open_item_id'])->lockForUpdate()->firstOrFail();
            if ($item->status !== 'open' || $item->version !== $allocation['version'] || $item->remaining_original !== $allocation['remaining_original'] || $item->remaining_book !== $allocation['remaining_book']) {
                throw new ApiProblem('OPEN_ITEM_CHANGED', 'El saldo de una partida cambió. Regenera la propuesta y revisa su nueva aplicación.', 409, ['open_item_id' => $item->id, 'version' => $item->version]);
            }
        }
    }
    public function apply(JournalEntry $entry, array $effect): void
    {
        $record = Settlement::where('journal_entry_id', $entry->id)->lockForUpdate()->firstOrFail();
        foreach ($effect['allocations'] as $allocation) {
            $item = OpenItem::whereKey($allocation['open_item_id'])->lockForUpdate()->firstOrFail();
            $item->remaining_original = (string) BigDecimal::of($item->remaining_original)->minus($allocation['original_amount']);
            $item->remaining_book = (string) BigDecimal::of($item->remaining_book)->minus($allocation['book_amount']);
            $item->status = BigDecimal::of($item->remaining_original)->isZero() ? 'settled' : 'open';
            $item->version++;
            $item->save();
            SettlementAllocation::create([...array_intersect_key($allocation, array_flip(['open_item_id', 'original_amount', 'book_amount', 'settlement_amount', 'exchange_difference'])), 'journal_entry_id' => $entry->id, 'settlement_date' => $entry->accounting_date->format('Y-m-d'), 'source_key' => $record->id . '|' . $item->id]);
        }
        $record->status = 'posted';
        $record->version++;
        $record->save();
    }
}
