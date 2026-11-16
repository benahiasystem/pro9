<?php

namespace Modules\Accounting\Support;

final class ThirdPartyInput
{
    public static function rules(): array
    {
        return ['tax_id' => 'required|string|max:30', 'name' => 'required|string|max:255', 'kind' => 'required|in:customer,supplier,both,other', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:2000', 'active' => 'sometimes|boolean'];
    }
}
