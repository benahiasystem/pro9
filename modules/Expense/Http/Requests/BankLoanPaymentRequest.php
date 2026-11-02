<?php

    namespace Modules\Expense\Http\Requests;

    use Illuminate\Foundation\Http\FormRequest;
    use Illuminate\Validation\Rule;

    /**
     * Class BankLoanPaymentRequest
     *
     * @package Modules\Expense\Http\Requests
     */
    class BankLoanPaymentRequest extends FormRequest
    {
        /**
         * @return bool
         */
        public function authorize()
        {
            return true;
        }

        /**
         * @return \string[][]
         */
        public function rules()
        {
            return [
                'date_of_payment' => [
                    'date',
                    'required',
                ],
                'payment_method_type_id' => [
                    'required',
                Rule::exists('tenant.payment_method_types', 'id')->where('is_active', 1)->where('collection_kind', 'account'),
                ],
                'payment_destination_id' => [
                    'required',
                ],
                'payment' => [
                    'bail', 'required', new \App\Rules\ExactDecimal('0', true),
                    function ($attribute, $value, $fail) {
                        if (is_float($value)) $fail('Transmita el importe decimal como texto.');
                    },
                ],
            ];
        }
    }
