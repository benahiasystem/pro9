<?php

namespace Modules\MobileApp\Http\Controllers\Api;

use App\Models\Tenant\BankAccount;
use App\Models\Tenant\Cash;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\DocumentPayment;
use App\Models\Tenant\PaymentMethodType;
use App\Models\Tenant\PurchasePayment;
use App\Models\Tenant\PurchaseSettlementPayment;
use App\Models\Tenant\SaleNotePayment;
use App\Models\Tenant\TransferAccountPayment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Expense\Http\Controllers\ExpenseController;
use Modules\Expense\Models\BankLoanPayment;
use Modules\Expense\Models\ExpenseMethodType;
use Modules\Expense\Models\ExpensePayment;
use Modules\Expense\Models\ExpenseReason;
use Modules\Expense\Models\ExpenseType;
use Modules\Expense\Http\Requests\ExpenseRequest;
use Modules\Finance\Http\Controllers\IncomeController;
use Modules\Finance\Http\Requests\IncomeRequest;
use Modules\Finance\Models\GlobalPayment;
use Modules\Finance\Models\IncomePayment;
use Modules\Finance\Models\IncomeReason;
use Modules\Finance\Models\IncomeType;
use Modules\Finance\Traits\FinanceTrait;
use Modules\MobileApp\Http\Requests\Api\FinanceExpenseRequest;
use Modules\MobileApp\Http\Requests\Api\FinanceIncomeRequest;
use Modules\MobileApp\Http\Resources\Api\FinanceMovementCollection;
use Modules\Pos\Models\CashTransaction;
use Modules\Sale\Models\ContractPayment;
use Modules\Sale\Models\QuotationPayment;
use Modules\Sale\Models\TechnicalServicePayment;

/**
 * Finanzas para la app movil: movimientos unificados de ingresos/egresos
 * (global_payments) con filtros, registro de ingresos y de gastos diversos.
 * La emision delega en los controladores web (IncomeController / ExpenseController),
 * mismo patron que el resto del modulo MobileApp.
 */
class FinanceController extends Controller
{
    use FinanceTrait;

    // Tipo de gasto cuyo numero de comprobante es opcional ("Otros" en la web)
    const EXPENSE_NUMBER_OPTIONAL_TYPE_ID = 4;

    // instance_type => [clase del pago, descripcion, sentido]
    // Mismo mapa que GlobalPayment::getInstanceTypeAttribute / getTypeMovementAttribute.
    const INSTANCE_TYPES = [
        'document' => [DocumentPayment::class, 'CPE', 'input'],
        'sale_note' => [SaleNotePayment::class, 'Nota de venta', 'input'],
        'quotation' => [QuotationPayment::class, 'Cotización', 'input'],
        'contract' => [ContractPayment::class, 'Contrato', 'input'],
        'technical_service' => [TechnicalServicePayment::class, 'Servicio técnico', 'input'],
        'income' => [IncomePayment::class, 'Ingreso', 'input'],
        'cash_transaction' => [CashTransaction::class, 'Ingreso POS', 'input'],
        'purchase' => [PurchasePayment::class, 'Compra', 'output'],
        'purchase_settlement' => [PurchaseSettlementPayment::class, 'Liquidación de compra', 'output'],
        'expense' => [ExpensePayment::class, 'Gasto', 'output'],
        'bank_loan_payment' => [BankLoanPayment::class, 'Pago préstamo', 'output'],
        'transfer_account' => [TransferAccountPayment::class, 'Transferencia bancaria', 'transfer'],
    ];

    /**
     * Catalogos para los formularios de ingreso/gasto y el panel de filtros.
     *
     * @return array
     */
    public function tables()
    {
        $open_cash = Cash::where([['user_id', auth()->id()], ['state', true]])->first();

        $id_description = fn($row) => ['id' => $row->id, 'description' => $row->description];

        return [
            'success' => true,
            'data' => [
                'income_types' => IncomeType::get()->map($id_description)->values(),
                'income_reasons' => IncomeReason::all()->map($id_description)->values(),
                'expense_types' => ExpenseType::get()->map($id_description)->values(),
                'expense_reasons' => ExpenseReason::all()->map($id_description)->values(),
                'expense_method_types' => ExpenseMethodType::all()->map(fn($row) => [
                    'id' => $row->id,
                    'description' => $row->description,
                    'has_card' => (bool) $row->has_card,
                ])->values(),
                'payment_method_types' => PaymentMethodType::getPaymentMethodTypes(),
                'payment_destinations' => $this->getPaymentDestinations(),
                'currency_types' => CurrencyType::whereActive()->get()->map(fn($row) => [
                    'id' => $row->id,
                    'description' => $row->description,
                    'symbol' => $row->symbol,
                ])->values(),
                'open_cash' => $open_cash ? [
                    'id' => $open_cash->id,
                    'reference_number' => $open_cash->reference_number,
                    'date_opening' => optional($open_cash->date_opening)->format('Y-m-d'),
                ] : null,
                'expense_number_optional_type_id' => self::EXPENSE_NUMBER_OPTIONAL_TYPE_ID,
                'instance_types' => collect(self::INSTANCE_TYPES)->map(fn($def, $key) => [
                    'id' => $key,
                    'description' => $def[1],
                    'type_movement' => $def[2],
                ])->values(),
            ],
        ];
    }

    /**
     * Movimientos unificados con scroll infinito.
     *
     * Parametros: limit (max 100), cursor, date_start, date_end (Y-m-d, sobre
     * date_of_payment; default mes actual), type_movement (input|output),
     * instance_types[] (claves de INSTANCE_TYPES), destination ("cash" | "bank:{id}"),
     * last_cash_opening (1 = solo la caja abierta del usuario).
     *
     * @param  Request $request
     * @return array
     */
    public function movementsByScroll(Request $request)
    {
        $limit = min((int) $request->input('limit', 20), 100);
        $cursor = $request->input('cursor');

        $query = $this->buildMovementsQuery($request);
        if (!$query) {
            // last_cash_opening sin caja abierta: no hay movimientos que mostrar
            return ['success' => true, 'data' => [], 'pagination' => ['next_cursor' => null, 'has_more' => false]];
        }

        $query->orderBy('id', 'desc');

        $records = $cursor
            ? $query->cursorPaginate($limit, ['*'], 'cursor', $cursor)
            : $query->cursorPaginate($limit);

        return [
            'success' => true,
            'data' => new FinanceMovementCollection($records),
            'pagination' => [
                'next_cursor' => $records->nextCursor()?->encode() ?? null,
                'has_more' => $records->hasMorePages(),
            ],
        ];
    }

    /**
     * Totales del periodo con los mismos filtros del scroll (el saldo no puede
     * acumularse pagina a pagina con cursor). Montos convertidos a PEN.
     *
     * @param  Request $request
     * @return array
     */
    public function movementsSummary(Request $request)
    {
        $query = $this->buildMovementsQuery($request);
        if (!$query) {
            return ['success' => true, 'data' => ['count' => 0, 'total_input' => 0, 'total_output' => 0, 'balance' => 0]];
        }

        $count = 0;
        $total_input = 0.0;
        $total_output = 0.0;

        $query->orderBy('id')->chunk(500, function ($rows) use (&$count, &$total_input, &$total_output) {
            foreach ($rows as $row) {
                $payment = $row->payment;
                if (!$payment) continue;
                $count++;

                if ($row->payment_type === TransferAccountPayment::class) {
                    // La transferencia refleja origen (monto positivo) y destino (negativo):
                    // se anulan entre si, no afectan el balance global.
                    continue;
                }

                $amount = (float) $payment->payment;
                $document = $payment->associated_record_payment ?? null;
                if ($document && ($document->currency_type_id ?? 'PEN') === 'USD') {
                    $amount *= (float) $document->exchange_rate_sale;
                }

                if ($row->type_movement === 'input') $total_input += $amount;
                else $total_output += $amount;
            }
        });

        return [
            'success' => true,
            'data' => [
                'count' => $count,
                'total_input' => round($total_input, 2),
                'total_output' => round($total_output, 2),
                'balance' => round($total_input - $total_output, 2),
            ],
        ];
    }

    /**
     * Registra un ingreso delegando en IncomeController@store.
     *
     * @param  FinanceIncomeRequest $request
     * @return array
     */
    public function storeIncome(FinanceIncomeRequest $request)
    {
        $request->merge([
            'id' => null,
            'customer' => trim((string) $request->input('customer')) ?: '-',
        ]);

        // El controlador web exige su propio FormRequest; la validacion fuerte
        // ya corrio en FinanceIncomeRequest, asi que solo se convierte el tipo.
        $web_request = IncomeRequest::createFrom($request);

        try {
            $result = app(IncomeController::class)->store($web_request);
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return $this->appendPrintUrl($result, 'income');
    }

    public function voidIncome($id)
    {
        return app(IncomeController::class)->voided($id);
    }

    /**
     * Registra un gasto delegando en ExpenseController@store.
     *
     * @param  FinanceExpenseRequest $request
     * @return array
     */
    public function storeExpense(FinanceExpenseRequest $request)
    {
        $request->merge(['id' => null]);

        $web_request = ExpenseRequest::createFrom($request);

        try {
            $result = app(ExpenseController::class)->store($web_request);
        } catch (Exception $e) {
            // Incluye la excepcion del listener de caja (gasto en efectivo sin caja abierta)
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return $this->appendPrintUrl($result, 'expense');
    }

    public function voidExpense($id)
    {
        return app(ExpenseController::class)->voided($id);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Query de movimientos con todos los filtros aplicados. Devuelve null cuando
     * se pidio last_cash_opening y el usuario no tiene caja abierta.
     */
    private function buildMovementsQuery(Request $request)
    {
        // Default: mes actual (la app siempre manda el rango resuelto en cliente)
        $date_start = $request->input('date_start') ?: now()->startOfMonth()->format('Y-m-d');
        $date_end = $request->input('date_end') ?: now()->endOfMonth()->format('Y-m-d');
        $params = (object) ['date_start' => $date_start, 'date_end' => $date_end];

        // El scope encadena orWhereHas: se agrupa para poder añadir filtros AND.
        $query = GlobalPayment::query()
            ->applyFiltersForPerformance()
            ->where(fn($q) => $q->whereFilterPaymentType($params));

        // Tipos de origen / sentido del movimiento
        $instance_types = (array) $request->input('instance_types', []);
        $type_movement = $request->input('type_movement');
        if (empty($instance_types) && in_array($type_movement, ['input', 'output'], true)) {
            $instance_types = collect(self::INSTANCE_TYPES)
                ->filter(fn($def) => $def[2] === $type_movement)
                ->keys()
                ->all();
        }
        if (!empty($instance_types)) {
            $classes = collect(self::INSTANCE_TYPES)
                ->only($instance_types)
                ->map(fn($def) => $def[0])
                ->values()
                ->all();
            $query->whereIn('payment_type', $classes);
        }

        // Destino: caja o cuenta bancaria concreta
        $destination = (string) $request->input('destination', '');
        if ($destination === 'cash') {
            $query->whereDestinationType(Cash::class);
        } elseif (str_starts_with($destination, 'bank:')) {
            $query->whereDestinationType(BankAccount::class)
                ->where('destination_id', (int) substr($destination, 5));
        }

        // Solo la caja abierta del usuario (mismo criterio que la web)
        if ($request->boolean('last_cash_opening')) {
            $cash = Cash::where([['user_id', auth()->id()], ['state', true]])->first();
            if (!$cash) return null;
            $query->whereDestinationType(Cash::class)->where('destination_id', $cash->id);
        }

        return $query;
    }

    // Completa la respuesta del store con la URL del PDF: ruta publica
    // print/{income|expense}/{external_id} (DownloadController@toPrint, CORS via **/print/*). Solo formato a4.
    private function appendPrintUrl($result, string $type)
    {
        if (is_array($result) && ($result['success'] ?? false) && isset($result['data']['id'])) {
            $model = $type === 'expense'
                ? \Modules\Expense\Models\Expense::find($result['data']['id'])
                : \Modules\Finance\Models\Income::find($result['data']['id']);
            if ($model) {
                $result['data']['external_id'] = $model->external_id;
                $result['data']['number'] = $model->number;
                $result['data']['print_url'] = url("print/{$type}/{$model->external_id}");
            }
        }
        return $result;
    }
}
