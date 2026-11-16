<?php

namespace Modules\Accounting\Support;

final class OpeningInput
{
    public static function rules(): array
    {
        return ['cutoff_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:10|max:300', 'evidence_reference' => 'required|string|min:5|max:1000', 'balances' => 'required|array|min:2|max:500', 'balances.*' => 'array:account_id,third_party_id,debit,credit', 'balances.*.account_id' => 'required|uuid', 'balances.*.third_party_id' => 'nullable|uuid', 'balances.*.debit' => 'required|string|max:40', 'balances.*.credit' => 'required|string|max:40', 'open_items' => 'present|array|max:2000', 'open_items.*' => 'array:account_id,third_party_id,source_document_id,reference,issue_date,due_date,currency,original_amount,book_amount,exchange_rate,exchange_date,exchange_source,evidence_reference', 'open_items.*.account_id' => 'required|uuid', 'open_items.*.third_party_id' => 'required|uuid', 'open_items.*.source_document_id' => 'nullable|uuid', 'open_items.*.reference' => 'required|string|max:100', 'open_items.*.issue_date' => 'required|date_format:Y-m-d', 'open_items.*.due_date' => 'nullable|date_format:Y-m-d', 'open_items.*.currency' => 'required|in:VES,USD,EUR', 'open_items.*.original_amount' => 'required|string|max:40', 'open_items.*.book_amount' => 'required|string|max:40', 'open_items.*.exchange_rate' => 'required|string|max:40', 'open_items.*.exchange_date' => 'required|date_format:Y-m-d', 'open_items.*.exchange_source' => 'required|string|min:3|max:255', 'open_items.*.evidence_reference' => 'required|string|min:5|max:1000'];
    }
    public static function fromArray(array $input): array
    {
        return \Illuminate\Support\Facades\Validator::make($input, self::rules())->validate();
    }
}
