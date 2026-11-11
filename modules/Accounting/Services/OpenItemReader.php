<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Models\{OpenItem, ThirdParty, Account, SettlementAllocation, JournalEntry};
final class OpenItemReader
{
    public function many(Collection $items, bool $history = false, int $historyLimit = 10000): Collection
    {
        $parties = ThirdParty::whereIn('id', $items->pluck('third_party_id'))->get()->keyBy('id');
        $accounts = Account::whereIn('id', $items->pluck('account_id'))->get()->keyBy('id');
        $allocations = collect();
        $entries = collect();
        if ($history) {
            $allocations = SettlementAllocation::whereIn('open_item_id', $items->pluck('id'))->orderBy('settlement_date')->orderBy('id')->limit($historyLimit + 1)->get();
            if ($allocations->count() > $historyLimit) {
                throw new ApiProblem('AUXILIARY_HISTORY_TOO_LARGE', 'La historia de aplicaciones supera el alcance de esta consulta. Consulta su detalle con PRO9 antes de preparar otra copia.', 413);
            }
            $entries = JournalEntry::whereIn('id', $allocations->pluck('journal_entry_id'))->get(['id', 'reference', 'status', 'posted_at'])->keyBy('id');
            $allocations = $allocations->groupBy('open_item_id');
        }
        return $items->map(function ($item) use ($parties, $accounts, $history, $allocations, $entries) {
            $row = [...$item->toArray(), 'party' => $parties->get($item->third_party_id)?->only(['id', 'name', 'tax_id']), 'account' => $accounts->get($item->account_id)?->only(['id', 'code', 'name'])];
            if ($history) {
                $row['allocations'] = $allocations->get($item->id, collect())->map(fn($allocation) => [...$allocation->toArray(), 'entry' => $entries->get($allocation->journal_entry_id)?->toArray()])->values()->all();
            }
            return $row;
        });
    }
    public function one(OpenItem $item, bool $history = false): array
    {
        return $this->many(collect([$item]), $history)->first();
    }
}
