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
                Rule::exists('tenant.payment_method_types', 'id')->where('is_active', 1),
                ],
                'payment_destination_id' => [
                    'required_unless:expense_method_type_id, "1"',
                    // 'required',
                ],
                'payment' => [
                    'required',
                ],
            ];
        }
    }
