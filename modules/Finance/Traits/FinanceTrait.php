<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

    namespace Modules\Finance\Traits;

    use App\Models\Tenant\{Document, DocumentPayment, PurchasePayment, SaleNotePayment, PurchaseSettlementPayment, SaleNote};
    use App\Models\Tenant\BankAccount;
    use App\Models\Tenant\Cash;
    use App\Models\Tenant\Company;
    use App\Models\Tenant\TransferAccountPayment;
    use Carbon\Carbon;
    use App\Support\Exact\Decimal as ExactDecimal;
    use ErrorException;
    use Illuminate\Database\Eloquent\Collection;
    use Modules\Expense\Models\BankLoanPayment;
    use Modules\Expense\Models\ExpensePayment;
    use Modules\Finance\Models\IncomePayment;
    use Modules\Pos\Models\CashTransaction;
    use Modules\Sale\Models\ContractPayment;
    use Modules\Sale\Models\QuotationPayment;
    use Modules\Sale\Models\TechnicalServicePayment;
    use App\Models\Tenant\ExchangeRate;
    use App\Models\Tenant\CashDocumentPayment;
    use App\Models\Tenant\CashDocumentCredit;
    use App\Models\Tenant\CashDocument;
    use Illuminate\Http\Exceptions\HttpResponseException;

    trait FinanceTrait
    {

        /**
         * @return \Illuminate\Support\Collection
         */
        public function getPaymentDestinations()
        {

            $bank_accounts = self::getBankAccounts();
            $cash = $this->getCash();

            // dd($cash);
            if ($cash) {
                return $bank_accounts->push($cash);
            }

            return $bank_accounts;

        }


        private static function getBankAccounts()
        {

            return BankAccount::get()->transform(function ($row) {
                return [
                    'id' => $row->id,
                    'cash_id' => null,
                    'description' => "{$row->bank->description} - {$row->currency_type_id} - {$row->description}",
                ];
            });

        }


        /**
         * @return array|null
         */
        public function getCash()
        {
            $cash = Cash::query()->where([['user_id', auth()->id()], ['state', true]])->first();
            if ($cash) {
                return [
                    'id' => 'cash',
                    'cash_id' => $cash->id,
                    'description' => ($cash->reference_number) ? "CAJA GENERAL - {$cash->reference_number}" : "CAJA GENERAL",
                ];
            }
            // else{

            //     $cash_create = Cash::create([
            //                             'user_id' => auth()->user()->id,
            //                             'date_opening' => date('Y-m-d'),
            //                             'time_opening' => date('H:i:s'),
            //                             'date_closed' => null,
            //                             'time_closed' => null,
            //                             'beginning_balance' => 0,
            //                             'final_balance' => 0,
            //                             'income' => 0,
            //                             'state' => true,
            //                             'reference_number' => null
            //                         ]);

            //     return [
            //         'id' => 'cash',
            //         'cash_id' => $cash_create->id,
            //         'description' => "CAJA GENERAL"
            //     ];

            // }

            return null;

        }

        public function finance_cash_document($model, $id)
        {
            $isDocument = $model instanceof Document;
            $documentModel = $isDocument ? Document::class : SaleNote::class;
            $documentField = $isDocument ? 'document_id' : 'sale_note_id';
            $paymentConditionField = $isDocument ? 'payment_condition_id' : 'payment_method_type_id';
            $creditCondition = $isDocument ? '02' : '09';

            $document = $documentModel::findOrFail((int) $id);

            // Tienda / API sin sesión de admin: usar el emisor del comprobante.
            // Si no hay caja abierta, no bloquear la emisión del documento.
            $userId = auth()->id() ?: ($document->user_id ?? null);
            $cash = $userId
                ? Cash::where([['user_id', $userId], ['state', true]])->first()
                : null;

            if (! $cash) {
                return;
            }

            $isCredit = $document->$paymentConditionField === $creditCondition;
            $cashDocumentCredit = $isCredit ? CashDocumentCredit::updateOrCreate([
                'cash_id' => $cash->id,
                $documentField => $document->id,
            ]) : null;

            $cashDocument = $cash->cash_documents()->updateOrCreate([
                $documentField => $id
            ]);


            $document->payments->each(function($payment) use($cash,$isDocument,$cashDocument){
                CashDocumentPayment::updateOrCreate([
                    'cash_id' => $cash->id,
                    $isDocument ? 'document_payment_id' : 'sale_note_payment_id' => $payment->id,
                    'cash_document_id' => optional($cashDocument)->id,
                ]);
            });

        }

        public function createGlobalPayment($model, $row)
        {
            $destination = $this->getDestinationRecord($row);
            $company = Company::active();
            // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
            if ($model instanceof DocumentPayment && $destination['destination_type'] === BankAccount::class) {
                $account = BankAccount::findOrFail($destination['destination_id']);
                if ($account->currency_type_id !== $model->currency_type_id) {
                    \App\Services\Fiscal\FiscalAmounts::error('payment_destination_id','La moneda del pago debe coincidir con la cuenta bancaria.');
                }
            }
            // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
            $model->global_payment()->create([
                'user_id' => auth()->id(),
                'fiscal_environment' => $company->fiscal_environment,
                'destination_id' => $destination['destination_id'],
                'destination_type' => $destination['destination_type'],
            ]);
        }

        public function getDestinationRecord($row)
        {
            if ($row['payment_destination_id'] === 'cash') {
                $cash = $this->getCash();

                if (!$cash || !isset($cash['cash_id'])) {
                    // Lanza una excepción con el mensaje y código de error
                    throw new HttpResponseException(response()->json([
                        'message' => 'La caja se encuentra cerrada. Debes abrirla antes de registrar un ingreso.'
                    ], 400));
                }

                $destination_id = $cash['cash_id']; // Usa la variable $cash ya validada
                $destination_type = Cash::class;

            } else {
                $destination_id = $row['payment_destination_id'];
                $destination_type = BankAccount::class;
            }

            return [
                'destination_id' => $destination_id,
                'destination_type' => $destination_type,
            ];
        }


        public function createCashDocumentPayment($payment, $isDocument = true)
        {

            $cash = Cash::where([
                ['user_id', auth()->user()->id],
                ['state', true],
            ])->first();

            if (!$cash) return;
            $cashDocument = ($isDocument)
                ?CashDocument::where('document_id',$payment->document_id)->first()
                :CashDocument::where('sale_note_id',$payment->sale_note_id)->first();
            $cashDocumentCredit = ($isDocument)
                ?CashDocumentCredit::where('document_id',$payment->document_id)->first()
                :CashDocumentCredit::where('sale_note_id',$payment->sale_note_id)->first();

            CashDocumentPayment::create([
                'cash_id' => $cash->id,
                $isDocument ? 'document_payment_id' : 'sale_note_payment_id' => $payment->id,
                'cash_document_id' => optional($cashDocument)->id,
                'cash_document_credit_id' => optional($cashDocumentCredit)->id,
            ]);

        }

        public function deleteAllPayments($payments)
        {

            foreach ($payments as $payment) {
                if (method_exists($payment, 'cashDocumentPayments')) {
                    $payment->cashDocumentPayments()->delete();
                }
                $payment->delete();
            }

        }

        public function getCollectionPaymentTypes()
        {

            return [
                ['id' => DocumentPayment::class, 'description' => 'COMPROBANTES (CPE)'],
                ['id' => SaleNotePayment::class, 'description' => 'NOTAS DE VENTA'],
                ['id' => PurchasePayment::class, 'description' => 'COMPRAS'],
                ['id' => ExpensePayment::class, 'description' => 'GASTOS'],
                ['id' => QuotationPayment::class, 'description' => 'COTIZACIÓN'],
                ['id' => ContractPayment::class, 'description' => 'CONTRATO'],
                ['id' => IncomePayment::class, 'description' => 'INGRESO'],
                // ['id'=> CashTransaction::class, 'description' => 'CAJA CHICA POS'],
                ['id' => TechnicalServicePayment::class, 'description' => 'SERVICIO TÉCNICO'],
                ['id' => PurchaseSettlementPayment::class, 'description' => 'LIQUIDACION COMPRA'],
            ];
        }

        /**
         * @return string[][]
         */
        public function getCollectionDestinationTypes()
        {

            $return = [
                ['id' => Cash::class, 'description' => 'CAJA GENERAL'],
                ['id' => BankAccount::class, 'description' => 'CUENTA BANCARIA'],
            ];
            /** @var Collection $banks */
            $banks = BankAccount::SelectIdDescription()->get()->transform(function ($v) {
                // Añade el banco con el standar App\Models\Tenant\BankAccount, separado por :: el id del banco
                $v = $v->toArray();
                $v['id'] = BankAccount::class . "::" . $v['id'];
                return $v;
            })->toArray();
            $return = array_merge($return, $banks);
            return $return;
        }

        /**
         * @param array $request
         *
         * @return array
         */
        public function getDatesOfPeriod($request)
        {

            $period = $request['period'];
            $date_start = $request['date_start'];
            $date_end = $request['date_end'];
            $month_start = $request['month_start'];
            $month_end = $request['month_end'];

            $d_start = null;
            $d_end = null;
            /** @todo: Eliminar periodo, fechas y cambiar por

            $date_start = $request['date_start'];
            $date_end = $request['date_end'];
            \App\CoreFacturalo\Helpers\Functions\FunctionsHelper\FunctionsHelper::setDateInPeriod($request, $date_start, $date_end);
             */
            switch ($period) {
                case 'month':
                    $d_start = Carbon::parse($month_start . '-01')->format('Y-m-d');
                    $d_end = Carbon::parse($month_start . '-01')->endOfMonth()->format('Y-m-d');
                    break;
                case 'between_months':
                    $d_start = Carbon::parse($month_start . '-01')->format('Y-m-d');
                    $d_end = Carbon::parse($month_end . '-01')->endOfMonth()->format('Y-m-d');
                    break;
                case 'date':
                    $d_start = $date_start;
                    $d_end = $date_start;
                    break;
                case 'between_dates':
                    $d_start = $date_start;
                    $d_end = $date_end;
                    break;
            }

            return [
                'd_start' => $d_start,
                'd_end' => $d_end
            ];
        }


        /**
         * @param Collection $cash
         *
         * @return array
         */
        public function getBalanceByCash($cash, $requestCurrencyTipeId = 'VES')
        {

            $document_payment = $this->getSumPayment($cash, DocumentPayment::class, $requestCurrencyTipeId);
            $expense_payment = $this->getSumPayment($cash, ExpensePayment::class, $requestCurrencyTipeId);
            $sale_note_payment = $this->getSumPayment($cash, SaleNotePayment::class, $requestCurrencyTipeId);
            $purchase_payment = $this->getSumPayment($cash, PurchasePayment::class, $requestCurrencyTipeId);
            $quotation_payment = $this->getSumPayment($cash, QuotationPayment::class, $requestCurrencyTipeId);
            // $contract_payment = 0; //$this->getSumPayment($cash, ContractPayment::class);
            $contract_payment = $this->getSumPayment($cash, ContractPayment::class, $requestCurrencyTipeId);
            $income_payment = $this->getSumPayment($cash, IncomePayment::class, $requestCurrencyTipeId);
            $cash_pos = $this->getSumPaymentCashPos($cash, CashTransaction::class);
            $technical_service_payment = $this->getSumPayment($cash, TechnicalServicePayment::class, $requestCurrencyTipeId);
            $purchase__settlement_payment = $this->getSumPayment($cash, PurchaseSettlementPayment::class, $requestCurrencyTipeId);
            $cash_ids = $cash->pluck('destination_id')->unique();

            $transfer_beween_account = ExactDecimal::of(0);
            foreach ($cash_ids as $c) {
                $transfer_beween_account = $transfer_beween_account->plus($this->getTransferAccountPayment(Cash::find($c)));
            }
            $entry = ExactDecimal::sum([$document_payment, $sale_note_payment, $quotation_payment, $contract_payment,
                $income_payment, $cash_pos, $technical_service_payment]);
            $refund_payment = $this->getSumPayment($cash, \App\Models\Tenant\DocumentNoteRefund::class, $requestCurrencyTipeId);
            $egress = ExactDecimal::sum([$refund_payment, $expense_payment, $purchase_payment]);
            $balance = $entry->minus($egress)->plus($transfer_beween_account);
            $income_payment_return = ExactDecimal::of($income_payment)->plus($cash_pos);
            return [
                'calculation_snapshot'=>ExactDecimal::snapshot(['document_payment'=>$document_payment, 'sale_note_payment'=>$sale_note_payment, 'quotation_payment'=>$quotation_payment, 'contract_payment'=>$contract_payment, 'purchase_payment'=>$purchase_payment, 'income_payment'=>$income_payment, 'technical_service_payment'=>$technical_service_payment, 'expense_payment'=>$expense_payment, 'balance'=>$balance]),

                'id' => 'cash',
                'description' => "CAJA GENERAL",
                'initial_balance' => self::FormatNumber($cash_pos),
                'expense_payment' => self::FormatNumber($expense_payment),
                'purchase_payment' => self::FormatNumber($purchase_payment),
                'document_payment' => self::FormatNumber($document_payment),
                'sale_note_payment' => self::FormatNumber($sale_note_payment),
                'quotation_payment' => self::FormatNumber($quotation_payment),
                'contract_payment' => self::FormatNumber($contract_payment),
                'income_payment' => self::FormatNumber($income_payment_return),
               'debs' => self::FormatNumber($egress->plus($transfer_beween_account->isNegative() ? $transfer_beween_account->abs() : 0)),
                'credits' => self::FormatNumber($entry->plus($transfer_beween_account->isGreaterThan(0) ? $transfer_beween_account : 0)),
                'technical_service_payment' => self::FormatNumber($technical_service_payment),
                'balance' => self::FormatNumber($balance),
                'transfer_beween_account' => self::FormatNumber($transfer_beween_account),
            ];
        }

        /**
         * Ajusta el resultado de la coleccion de items para prestamos bancarios
         *
         * @param $bankLoanItems
         * @param $loans
         */
        public static function getBankLoan($bankLoanItems, &$loans)
        {
            if (!empty($bankLoanItems)) {
                $loans = ExactDecimal::sum($bankLoanItems->map(fn ($row) => ExactDecimal::read($row, 'total_ingress')));
            }

        }

        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        public function getSumPayment($record, $model, $requestCurrencyTipeId = 'VES')
        {
            return ExactDecimal::sum($record->where('payment_type', $model)->map(function ($row) use ($requestCurrencyTipeId) {
                $receipt = $row->payment;
                if (!$receipt || $receipt->reversed_at) return ExactDecimal::of(0);
                if ($receipt instanceof \App\Models\Tenant\DocumentPayment || $receipt instanceof \App\Models\Tenant\DocumentNoteRefund) {
                    return ExactDecimal::convert($receipt->cash_received_amount, $receipt->currency_type_id, $requestCurrencyTipeId, $receipt->exchange_rate);
                }
                $parent = $receipt->associated_record_payment;
                if (!$parent) return ExactDecimal::read($receipt, 'payment');
                $credit = $row->instance_type === 'document' ? $this->getTotalCreditNotes($parent, $requestCurrencyTipeId) : ExactDecimal::of(0);
                return $this->calculateTotalCurrencyType($parent, ExactDecimal::read($receipt, 'payment'), $requestCurrencyTipeId)->minus($credit);
            }));
        }

        public function getTotalCreditNotes($record, $requestCurrencyTipeId = 'VES')
        {
            return ExactDecimal::sum($record->affected_documents->where('note_type', 'credit')->map(function ($note) use ($requestCurrencyTipeId) {
                return in_array($note->document->state_type_id, ['01','03','05','07','13'])
                    ? $this->calculateTotalCurrencyType($note->document, $note->document->exactValue('total'), $requestCurrencyTipeId) : ExactDecimal::of(0);
            }));
        }

        public function calculateTotalCurrencyType($record, $payment, $requestCurrencyTipeId = 'VES')
        {
            return ExactDecimal::convert($payment, $record->currency_type_id, $requestCurrencyTipeId, $record->exchange_rate_sale);
        }

        public function getSumPaymentCashPos($record, $model)
        {
            return ExactDecimal::sum($record->where('payment_type', $model)->map(fn ($row) => ExactDecimal::read($row->payment, 'payment')));
        }
        // ######## FIN CALCULOS EXACTOS PRO9 ########

        /**
         * Devuelve los movimientos de efectivo entre cuentas.
         *
         * @param BankAccount|Cash $model
         *
         * @return float
         */
        public function getTransferAccountPayment($model)
        {
            if(empty($model) ) return 0;
            $filter = [
                'destiny_id' => $model->id,
                'destiny_type' => get_class($model),
            ];
            return ExactDecimal::sum(TransferAccountPayment::where($filter)->get()->map(fn ($row) => $row->exactValue('amount')));
        }

        /**
         * @param        $number
         * @param int    $decimal
         * @param string $decimal_separator
         * @param string $thousands_separator
         *
         * @return string
         */
        public static function FormatNumber($number)
        {
            if (is_string($number) && $number === '-') return $number;

            return ExactDecimal::represent($number);
        }

        /**
         * @param BankAccount| Cash $bank_account
         *
         * @return array
         */
        public function getBalanceBySingleBankAcount($bank_account)
        {

            $cash_pos = 0;

            if (Cash::class == get_class($bank_account)) {
                $data = [];
                $data['id'] = "C:" . $bank_account->id;
                $data['user_id'] = $bank_account->user_id;
                $data['date_opening'] = $bank_account->date_opening;
                $data['time_opening'] = $bank_account->time_opening;
                $data['date_closed'] = $bank_account->date_closed;
                $data['time_closed'] = $bank_account->time_closed;
                $data['beginning_balance'] = $bank_account->beginning_balance;
                $data['final_balance'] = $bank_account->final_balance;
                $data['income'] = $bank_account->income;
                $data['state'] = $bank_account->state;
                $data['reference_number'] = $bank_account->reference_number;

                $data['description'] = $data['reference_number'];
                return $data;

            }

            $document_payment = $this->getSumPayment($bank_account->global_destination, DocumentPayment::class);
            $expense_payment = $this->getSumPayment($bank_account->global_destination, ExpensePayment::class);
            $sale_note_payment = $this->getSumPayment($bank_account->global_destination, SaleNotePayment::class);
            $purchase_payment = $this->getSumPayment($bank_account->global_destination, PurchasePayment::class);
            $quotation_payment = $this->getSumPayment($bank_account->global_destination, QuotationPayment::class);
            // $contract_payment = 0; //$this->getSumPayment($bank_account->global_destination, ContractPayment::class);
            $contract_payment = $this->getSumPayment($bank_account->global_destination, ContractPayment::class);
            $income_payment = $this->getSumPayment($bank_account->global_destination, IncomePayment::class);
            $technical_service_payment = $this->getSumPayment($bank_account->global_destination, TechnicalServicePayment::class);
            $purchase_settlement_payment = $this->getSumPayment($bank_account->global_destination, PurchaseSettlementPayment::class);
            $transfer_beween_account = $this->getTransferAccountPayment($bank_account);
            $initial_balance = $bank_account->initial_balance;

            $entry = ExactDecimal::sum([$document_payment, $sale_note_payment, $quotation_payment, $contract_payment,
                $income_payment, $cash_pos, $technical_service_payment, $initial_balance, $transfer_beween_account]);
            $refund_payment = $this->getSumPayment($bank_account->global_destination, \App\Models\Tenant\DocumentNoteRefund::class);
            $egress = ExactDecimal::sum([$refund_payment, $expense_payment, $purchase_payment]);
            $balance = $entry->minus($egress);
            $description = "{$bank_account->bank->description} - {$bank_account->currency_type_id} - {$bank_account->description}";


            return [
                'calculation_snapshot'=>ExactDecimal::snapshot(['document_payment'=>$document_payment, 'sale_note_payment'=>$sale_note_payment, 'quotation_payment'=>$quotation_payment, 'contract_payment'=>$contract_payment, 'purchase_payment'=>$purchase_payment, 'income_payment'=>$income_payment, 'technical_service_payment'=>$technical_service_payment, 'expense_payment'=>$expense_payment, 'balance'=>$balance, 'initial_balance'=>$initial_balance, 'purchase_settlement_payment'=>$purchase_settlement_payment]),

                'id' => $bank_account->id,
                'description' => $description,
                'expense_payment' => self::FormatNumber($expense_payment),
                'sale_note_payment' => self::FormatNumber($sale_note_payment),
                'quotation_payment' => self::FormatNumber($quotation_payment),
                'contract_payment' => self::FormatNumber($contract_payment),
                'document_payment' => self::FormatNumber($document_payment),
                'purchase_payment' => self::FormatNumber($purchase_payment),
                'income_payment' => self::FormatNumber($income_payment),
                'initial_balance' => self::FormatNumber($initial_balance),
                'debs' => self::FormatNumber($egress),
                'credits' => self::FormatNumber($entry),
                'technical_service_payment' => self::FormatNumber($technical_service_payment),
                'balance' => self::FormatNumber($balance),
                'transfer_beween_account' => self::FormatNumber($transfer_beween_account),


            ];
        }

        /**
         * @param Collection $bank_accounts
         *
         * @return Collection|\Illuminate\Support\Collection
         */
        public function getBalanceByBankAcounts($bank_accounts,  $requestCurrencyTipeId = 'VES')
        {

            $exchangeRate = ExchangeRate::where('date', date('Y-m-d'))->first();

            $records = $bank_accounts->map(function (\App\Models\Tenant\BankAccount  $row) use ($exchangeRate, $requestCurrencyTipeId) {
                $document_payment = $this->getSumPayment($row->global_destination, DocumentPayment::class, $requestCurrencyTipeId);
                $expense_payment = $this->getSumPayment($row->global_destination, ExpensePayment::class, $requestCurrencyTipeId);
                $sale_note_payment = $this->getSumPayment($row->global_destination, SaleNotePayment::class, $requestCurrencyTipeId);
                $purchase_payment = $this->getSumPayment($row->global_destination, PurchasePayment::class, $requestCurrencyTipeId);
                $quotation_payment = $this->getSumPayment($row->global_destination, QuotationPayment::class, $requestCurrencyTipeId);
                // $contract_payment = 0; //$this->getSumPayment($row->global_destination, ContractPayment::class);
                $contract_payment = $this->getSumPayment($row->global_destination, ContractPayment::class, $requestCurrencyTipeId);
                $income_payment = $this->getSumPayment($row->global_destination, IncomePayment::class, $requestCurrencyTipeId);
                $technical_service_payment = $this->getSumPayment($row->global_destination, TechnicalServicePayment::class, $requestCurrencyTipeId);
                $purchase_settlement_payment = $this->getSumPayment($row->global_destination, PurchaseSettlementPayment::class, $requestCurrencyTipeId);
                $transfer_beween_account = $this->getTransferAccountPayment($row);

                // BankLoan es el total otorgado, se suma
                $bankLoan = 0;
                // los pagos a credito bancario, restan banco
                $bankLoanItems = $row->bank_loan_items ??null;
                if($row->bank_loan_items) {
                     self::getBankLoan($bankLoanItems, $bankLoan);
                }
                $bankLoanPayment = $this->getSumPayment($row->global_destination, BankLoanPayment::class, $requestCurrencyTipeId);


                $entry = ExactDecimal::sum([$document_payment, $sale_note_payment, $quotation_payment, $contract_payment, $bankLoan, $income_payment, $technical_service_payment]);
                $refund_payment = $this->getSumPayment($row->global_destination, \App\Models\Tenant\DocumentNoteRefund::class, $requestCurrencyTipeId);
                $egress = ExactDecimal::sum([$refund_payment, $expense_payment, $purchase_payment, $bankLoanPayment]);
                $initial_balance = $row->exactValue('initial_balance');
                $transfer_beween_account = ExactDecimal::of($transfer_beween_account);
                $balance = $initial_balance->plus($entry)->minus($egress)->plus($transfer_beween_account);

                return [

                    'id' => $row->id,
                    'description' => "{$row->bank->description} - {$row->currency_type_id} - {$row->description}",
                    'expense_payment' => self::FormatNumber($expense_payment),
                    'sale_note_payment' => self::FormatNumber($sale_note_payment),
                    'quotation_payment' => self::FormatNumber($quotation_payment),
                    'contract_payment' => self::FormatNumber($contract_payment),
                    'document_payment' => self::FormatNumber($document_payment),
                    'purchase_payment' => self::FormatNumber($purchase_payment),
                    'income_payment' => self::FormatNumber($income_payment),
                    'bank_loan' => self::FormatNumber($bankLoan),
                    'bank_loan_payment' => self::FormatNumber($bankLoanPayment),
                    'initial_balance' => self::FormatNumber($row->initial_balance),
                    'technical_service_payment' => self::FormatNumber($technical_service_payment),
                    'debs' => self::FormatNumber($egress->plus($transfer_beween_account->isNegative() ? $transfer_beween_account->abs() : 0)),
                    'credits' => self::FormatNumber($entry->plus($transfer_beween_account->isGreaterThan(0) ? $transfer_beween_account : 0)),
                    'balance' => self::FormatNumber($balance),
                    'transfer_beween_account' => self::FormatNumber($transfer_beween_account),

                ];

            });

            return $records;

        }

        /**
         * @param Collection $payment_method_types
         *
         * @return Collection|\Illuminate\Support\Collection
         */
        public function getRecordsByPaymentMethodTypes($payment_method_types)
        {

            $records = $payment_method_types->map(function (\App\Models\Tenant\PaymentMethodType $row) {
                $document_payment = $this->getSumByPMT($row->document_payments, true);
                $sale_note_payment = $this->getSumByPMT($row->sale_note_payments);
                $purchase_payment = $this->getSumByPMT($row->purchase_payments) ;
                $purchase_settlement_payment = $this->getSumByPMT($row->purchase_settlement_payments) ;
                $quotation_payment = $this->getSumByPMT($row->quotation_payments);
                $contract_payment = $this->getSumByPMT($row->contract_payments);
                // $contract_payment = 0; //$this->getSumByPMT($row->contract_payments);
                $cash_transaction = ExactDecimal::sum($row->cash_transactions->map(fn ($receipt) => ExactDecimal::read($receipt, 'payment')));
                $income_payment = $this->getSumByPMT($row->income_payments)->plus($cash_transaction);
                $technical_service_payment = $this->getSumByPMT($row->technical_service_payments);

                $egress = $purchase_payment;
                $entry = ExactDecimal::sum([$document_payment,$sale_note_payment,$quotation_payment,$contract_payment,$income_payment,$technical_service_payment]);
                $balance = $entry->minus(ExactDecimal::of($egress));

                return [
                'calculation_snapshot'=>ExactDecimal::snapshot(['document_payment'=>$document_payment, 'sale_note_payment'=>$sale_note_payment, 'quotation_payment'=>$quotation_payment, 'contract_payment'=>$contract_payment, 'purchase_payment'=>$purchase_payment, 'income_payment'=>$income_payment, 'technical_service_payment'=>$technical_service_payment, 'balance'=>$balance, 'purchase_settlement_payment'=>$purchase_settlement_payment]),

                    'id' => $row->id,
                    'description' => $row->description,
                    'expense_payment' => '-',
                    'sale_note_payment' => self::FormatNumber($sale_note_payment),

                    'document_payment' => self::FormatNumber($document_payment),
                    'purchase_payment' => self::FormatNumber($purchase_payment),
                    'purchase_settlement_payment' => self::FormatNumber($purchase_settlement_payment),
                    'quotation_payment' => self::FormatNumber($quotation_payment),
                    'contract_payment' => self::FormatNumber($contract_payment),
                    'income_payment' => self::FormatNumber($income_payment),
                    'technical_service_payment' => self::FormatNumber($technical_service_payment),

                    'debs' => self::FormatNumber($egress),
                    'credits' => self::FormatNumber($entry),

                    'balance' => self::FormatNumber($balance),
                ];

            });

            return $records;
        }

        /**
         * @param Collection $records
         * @param false      $include_credit_notes
         *
         * @return mixed
         */
        public function getSumByPMT($records, $include_credit_notes = false)
        {
            return ExactDecimal::sum($records->map(function ($row) use ($include_credit_notes) {
                if ($row instanceof DocumentPayment) return $row->reversed_at ? ExactDecimal::of(0) : ExactDecimal::convert($row->cash_received_amount, $row->currency_type_id, 'VES', $row->exchange_rate);
                $credit = $include_credit_notes ? $this->getTotalCreditNotes($row->associated_record_payment) : ExactDecimal::of(0);
                return $this->calculateTotalCurrencyType($row->associated_record_payment, $row->exactValue('payment'))->minus($credit);
            }));
        }

        /**
         * @param Collection $expense_method_types
         *
         * @return Collection|\Illuminate\Support\Collection
         */
        public function getRecordsByExpenseMethodTypes($expense_method_types)
        {

            $records = $expense_method_types->map(function ($row) {

                // dd($row->expense_payments);
                $expense_payment = $this->getSumByPMT($row->expense_payments);

                return [
                'calculation_snapshot'=>ExactDecimal::snapshot(['expense_payment'=>$expense_payment]),

                    'id' => $row->id,
                    'description' => $row->description,
                    'expense_payment' => self::FormatNumber($expense_payment),
                    'sale_note_payment' => '-',
                    'document_payment' => '-',
                    'quotation_payment' => '-',
                    'contract_payment' => '-',
                    'income_payment' => '-',
                    'purchase_payment' => '-',
                    'technical_service_payment' => '-',
                    'purchase_settlement_payment' => '-',
                    'balance' => self::FormatNumber($expense_payment),

                ];

            });

            return $records;
        }

        /**
         * @param Collection|\Illuminate\Support\Collection $records_by_pmt
         * @param Collection|\Illuminate\Support\Collection $records_by_emt
         *
         * @return array
         */
        public function getTotalsPaymentMethodType($records_by_pmt, $records_by_emt)
        {

            $t_documents = 0;
            $t_sale_notes = 0;
            $t_quotations = 0;
            $t_contracts = 0;
            $t_purchases = 0;
            $t_purchase_settlement = 0;
            $t_expenses = 0;
            $t_income = 0;
            $t_technical_services = 0;

            $t_documents = ExactDecimal::sum($records_by_pmt->map(fn ($row) => ExactDecimal::read($row, 'document_payment')));
            $t_sale_notes = ExactDecimal::sum($records_by_pmt->map(fn ($row) => ExactDecimal::read($row, 'sale_note_payment')));
            $t_quotations = ExactDecimal::sum($records_by_pmt->map(fn ($row) => ExactDecimal::read($row, 'quotation_payment')));
            $t_contracts = ExactDecimal::sum($records_by_pmt->map(fn ($row) => ExactDecimal::read($row, 'contract_payment')));
            $t_purchases = ExactDecimal::sum($records_by_pmt->map(fn ($row) => ExactDecimal::read($row, 'purchase_payment')));
            $t_purchase_settlement = ExactDecimal::sum($records_by_pmt->map(fn ($row) => ExactDecimal::read($row, 'purchase_settlement_payment')));
            $t_income = ExactDecimal::sum($records_by_pmt->map(fn ($row) => ExactDecimal::read($row, 'income_payment')));
            $t_technical_services = ExactDecimal::sum($records_by_pmt->map(fn ($row) => ExactDecimal::read($row, 'technical_service_payment')));
            $t_expenses = ExactDecimal::sum($records_by_emt->map(fn ($row) => ExactDecimal::read($row, 'expense_payment')));
            $t_bank_loan_payment = ExactDecimal::sum($records_by_pmt->map(fn ($row) => ExactDecimal::read($row, 'bank_loan_payment')));
            $t_bank_loan = ExactDecimal::sum($records_by_pmt->map(fn ($row) => ExactDecimal::read($row, 'bank_loan')));
            $t_balance = ExactDecimal::sum($records_by_pmt->map(fn ($row) => ExactDecimal::read($row,'balance')))->minus(ExactDecimal::sum($records_by_emt->map(fn ($row) => ExactDecimal::read($row,'balance'))));

            return [
                't_documents' => self::FormatNumber($t_documents),
                't_sale_notes' => self::FormatNumber($t_sale_notes),
                't_quotations' => self::FormatNumber($t_quotations),
                't_contracts' => self::FormatNumber($t_contracts),
                't_purchases' => self::FormatNumber($t_purchases),
                't_purchase_settlement' => self::FormatNumber($t_purchase_settlement),
                't_expenses' => self::FormatNumber($t_expenses),
                't_income' => self::FormatNumber($t_income),
                't_technical_services' => self::FormatNumber($t_technical_services),
                't_balance' => self::FormatNumber($t_balance),
                't_bank_loan' => self::FormatNumber($t_bank_loan),
                't_bank_loan_payment' => self::FormatNumber($t_bank_loan_payment),
            ];

        }


        //cash transaction

        public function createGlobalPaymentTransaction($model, $row)
        {

            $destination = $this->getDestinationRecordTransaction($row);
            $company = Company::active();

            $model->global_payment()->create([
                'user_id' => auth()->id(),
                'fiscal_environment' => $company->fiscal_environment,
                'destination_id' => $destination['destination_id'],
                'destination_type' => $destination['destination_type'],
            ]);

        }

        public function getDestinationRecordTransaction($row)
        {

            if ($row['payment_destination_id'] === 'cash') {

                $destination_id = $this->getCashTransaction($row['user_id'])['cash_id'];
                $destination_type = Cash::class;

            } else {

                $destination_id = $row['payment_destination_id'];
                $destination_type = BankAccount::class;

            }

            return [
                'destination_id' => $destination_id,
                'destination_type' => $destination_type,
            ];
        }

        public function getCashTransaction($user_id)
        {

            $cash = Cash::where([['user_id', $user_id], ['state', true]])->first();

            if ($cash) {

                return [
                    'id' => 'cash',
                    'cash_id' => $cash->id,
                    'description' => ($cash->reference_number) ? "CAJA GENERAL - {$cash->reference_number}" : "CAJA GENERAL",
                ];

            }

            return null;

        }


        /**
         *
         * Obtener fiscal_environment para registro de entorno
         *
         * @return string
         */
        public function getCompanyFiscalEnvironment()
        {
            return Company::getCompanyFiscalEnvironment();
        }

    }

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
