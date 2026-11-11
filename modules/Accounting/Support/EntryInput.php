<?php

namespace Modules\Accounting\Support;

use Illuminate\Http\Request;
final class EntryInput
{
    public static function validate(Request $r): array
    {
        return $r->validate(['accounting_date' => 'required|date_format:Y-m-d', 'concept' => 'required|string|max:500', 'entry_type' => 'sometimes|in:regular,adjustment', 'local_id' => 'nullable|uuid', 'lines' => 'required|array|min:1|max:500', 'lines.*.account_id' => 'required|uuid', 'lines.*.third_party_id' => 'nullable|uuid', 'lines.*.cost_center_id' => 'nullable|uuid', 'lines.*.debit' => 'required|string|max:40', 'lines.*.credit' => 'required|string|max:40', 'lines.*.description' => 'nullable|string|max:500', 'lines.*.document_reference' => 'nullable|string|max:100', 'lines.*.original_currency' => 'sometimes|in:VES,USD,EUR', 'lines.*.original_amount' => 'nullable|string|max:40', 'lines.*.exchange_rate' => 'nullable|string|max:40']);
    }
}
