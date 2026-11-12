<?php

namespace Modules\Accounting\Support;

use Illuminate\Support\Facades\Validator;

final class DocumentInput
{
    public static function fromArray(array $input): array
    {
        return Validator::make($input, self::rules())->validate();
    }

    public static function rules(): array
    {
        return ['kind' => 'required|in:sale_invoice,purchase_invoice,sale_credit_note,sale_debit_note,purchase_credit_note,purchase_debit_note', 'number' => 'nullable|string|max:100', 'fiscal_control' => 'nullable|string|max:100', 'issue_date' => 'required|date_format:Y-m-d', 'fiscal_date' => 'required|date_format:Y-m-d', 'received_on' => 'nullable|date_format:Y-m-d', 'due_date' => 'nullable|date_format:Y-m-d', 'third_party_id' => 'nullable|uuid', 'related_document_id' => 'nullable|uuid', 'currency' => 'required|in:VES,USD,EUR', 'exchange_rate' => 'required|string|max:40', 'exchange_source' => 'nullable|string|max:255', 'exchange_date' => 'required|date_format:Y-m-d', 'rate_evidence_reference' => 'nullable|string|max:1000', 'rate_reason' => 'nullable|string|max:2000', 'tax_explanation' => 'nullable|string|max:2000', 'amounts' => 'required|array', 'amounts.base' => 'required|string|max:40', 'amounts.vat' => 'required|string|max:40', 'amounts.other_taxes' => 'sometimes|required|string|max:40', 'amounts.total' => 'required|string|max:40', ...self::taxFields()];
    }

    public static function taxFields(): array
    {
        return ['tax_context' => 'sometimes|array:currency,payment_currency,scope', 'tax_context.currency' => 'sometimes|in:VES,USD,EUR', 'tax_context.payment_currency' => 'sometimes|in:VES,USD,EUR', 'tax_context.scope' => 'sometimes|string|max:100', 'tax_components' => 'sometimes|array|max:25', 'tax_components.*.tax' => 'required|in:IVA,IGTF,OTHER', 'tax_components.*.rule_id' => 'nullable|uuid', 'tax_components.*.base' => 'required|string|max:40', 'tax_components.*.amount' => 'required|string|max:40', 'tax_components.*.manual_reason' => 'nullable|string|max:2000'];
    }
}
