<?php
namespace App\Http\Controllers\Tenant;

use App\Exports\CashProductExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CashRequest;
use App\Http\Resources\Tenant\CashCollection;
use App\Http\Resources\Tenant\CashResource;
use App\Models\Tenant\Cash;
use App\Models\Tenant\CashDocument;
use App\Models\Tenant\Company;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\PaymentMethodType;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\Document;
use App\Models\Tenant\User;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Traits\FinanceTrait;
use Modules\Pos\Models\CashTransaction;
use App\Models\Tenant\CashDocumentCredit;
use Modules\Finance\Models\Income;
use App\CoreFacturalo\Helpers\Template\ReportHelper;
use Modules\CashReport\Services\Builders\ProductsBuilder;
use Modules\CashReport\Services\CashReportRenderer;
use Carbon\Carbon;
use Modules\Restaurant\Models\RestaurantTable;
use App\Models\Tenant\CashDocumentPayment;


/**
 * Class CashController
 *
 * @package App\Http\Controllers\Tenant
 * @mixin  Controller
 */
class CashController extends Controller
{

    use FinanceTrait;

    public function index()
    {
        return view('tenant.cash.index');
    }

    public function columns()
    {
        return [
            'income' => 'Ingresos',
            'user' => 'Vendedor',
        ];
    }

    public function records(Request $request)
    {
        $query = Cash::withOut(['cash_documents'])
                ->whereTypeUser();

        if ($request->column == 'user') {
            $query->whereHas('user', function($q) use($request) {
                $q->where('name', 'like', "%{$request->value}%");
            });
        } else {
            $query->where($request->column, 'like', "%{$request->value}%");
        }

        $query->orderBy('date_opening', 'DESC')
                ->orderBy('time_opening','desc');

        return new CashCollection($query->paginate(config('tenant.items_per_page')));
    }

    public function create()
    {
        return view('tenant.items.form');
    }

    public function tables()
    {
        $user = auth()->user();
        $type = $user->type;
        $users = array();

        switch($type)
        {
            case 'admin':
                $users = User::where('type', 'seller')->get();
                $users->push($user);
                break;
            case 'seller':
                $users = User::where('id', $user->id)->get();
                break;
        }

        return compact('users', 'user');
    }

    public function opening_cash()
    {

        $cash = Cash::where([['user_id', auth()->user()->id],['state', true]])->first();

        return compact('cash');
    }

    /**
     *
     * Usado en:
     * CashController - App
     *
     * @param  int $user_id
     * @return array
     */
    public function opening_cash_check($user_id)
    {
        $cash = Cash::where([['user_id', $user_id],['state', true]])->first();
        return compact('cash');
    }


    /**
     *
     * Usado en:
     * CashController - App
     *
     * @param  int $id
     * @return array
     */
    public function record($id)
    {
        $record = new CashResource(Cash::findOrFail($id));

        return $record;
    }


    /**
     *
     * Usado en:
     * CashController - App
     *
     * @param  CashRequest $request
     * @return array
     */
    public function store(CashRequest $request) {

        $id = $request->input('id');
        $cashId = 0;
        $notFound = false;


        DB::connection('tenant')->transaction(function () use ($id, $request,&$cashId,&$notFound) {
            $user_id = $request->input('user_id');

            if($user_id == 0){
                $user_id = auth()->user()->id;
                $request->merge(['user_id' => auth()->user()->id]);
            }

            if (!$id) {
                // Apertura: si el usuario ya tiene una caja activa se reutiliza, si no se crea una nueva
                $cash = Cash::where('user_id', $user_id)
                        ->where('state', true)
                        ->first();

                if (!$cash) {
                    $cash = new Cash;
                }
            } else {
                // Edición: la caja debe existir, no se crea una nueva
                $cash = Cash::find($id);

                if (!$cash) {
                    $notFound = true;
                    return;
                }
            }

            $cash->fill($request->all());

            if(!$id){
                $cash->date_opening = date('Y-m-d');
                $cash->time_opening = date('H:i:s');
            }

            $cash->save();
            $cashId = $cash->id;
            $this->createCashTransaction($cash, $request);

        });

        if ($notFound) {
            return [
                'success' => false,
                'message' => 'Caja no encontrada',
            ];
        }


        return [
            'success' => true,
            'message' => ($id)?'Caja actualizada con éxito':'Caja aperturada con éxito',
            'data' => [
                'cash_id' => $cashId
            ]
        ];

    }


    public function createCashTransaction($cash, $request){

        $this->destroyCashTransaction($cash);

        $data = [
            'date' => date('Y-m-d'),
            'description' => 'Saldo inicial',
            'payment_method_type_id' => '01',
            'payment' => $request->beginning_balance,
            'payment_destination_id' => 'cash',
            'user_id' => $request->user_id,
        ];

        $cash_transaction = $cash->cash_transaction()->create($data);

        $this->createGlobalPaymentTransaction($cash_transaction, $data);

    }


    /**
     *
     * Usado en:
     * CashController - App
     *
     * @param  int $id
     * @return array
     */
    public function close($id) {

        $cash = Cash::findOrFail($id);

        if(!$cash){
            return [
                'success' => false,
                'message' => 'Caja no encontrada',
            ];
        }

        $notAvailable = RestaurantTable::where('status', 'notavailable')->exists();

        if ($notAvailable) {
            return [
                'success' => false,
                'message' => 'No se puede cerrar caja , existe mesas abiertas.',
            ];
        }

        // dd($cash->cash_documents);

        $cash->date_closed = date('Y-m-d');
        $cash->time_closed = date('H:i:s');

        $final_balance = 0;
        $income = 0;

        foreach ($cash->cash_documents as $cash_document) {


            if($cash_document->sale_note){

                if(in_array($cash_document->sale_note->state_type_id, ['01','03','05','07','13'])){
                    $balance = $cash_document->sale_note->payments()
                    ->whereHas('cashDocumentPayments', function ($query) use ($id) {
                        $query->where('cash_id', $id);
                    })
                    ->sum('payment');

                    $final_balance += ($cash_document->sale_note->currency_type_id == 'VES')
                    ? $balance
                    : ($balance * $cash_document->sale_note->exchange_rate_sale);
                }

                // $final_balance += $cash_document->sale_note->total;

            }
            else if($cash_document->document){

                $note = $cash_document->document->getNotes();

                if (is_null($note) || count($note) === 0) {
                    if(in_array($cash_document->document->state_type_id, ['01','03','05','07','13'])){
                        $balance = $cash_document->document->payments()
                        ->whereHas('cashDocumentPayments', function ($query) use ($id) {
                            $query->where('cash_id', $id);
                        })
                        ->sum('payment');
                        $final_balance += ($cash_document->document->currency_type_id == 'VES')
                            ? $balance
                            : ($balance * $cash_document->document->exchange_rate_sale);
                    }
                } else {
                    foreach ($note as $n) {
                        $sum = $n->isDebit();
                        if ($sum) {
                            $final_balance += ($n->currency_type_id == 'VES')
                                ? $n->total
                                : ($n->total * $n->exchange_rate_sale);
                        } else {
                            $final_balance -= ($n->currency_type_id == 'VES')
                                ? $n->total
                                : ($n->total * $n->exchange_rate_sale);
                        }
                    }

                }



            }
            else if($cash_document->expense_payment){

                $expense = $cash_document->expense_payment->expense;
                if($expense->state_type_id == '05'){

                    $final_balance -= ($expense->currency_type_id == 'VES')
                        ? $cash_document->expense_payment->payment
                        : ($cash_document->expense_payment->payment * $expense->exchange_rate_sale);
                }

                // $final_balance -= $cash_document->expense_payment->payment;
            }
            else if($cash_document->purchase){
                if(in_array($cash_document->purchase->state_type_id, ['01','03','05','07','13'])){
                    if($cash_document->purchase->total_canceled == 1) {
                        $final_balance -= ($cash_document->purchase->currency_type_id == 'VES')
                            ? $cash_document->purchase->total
                            : ($cash_document->purchase->total * $cash_document->purchase->exchange_rate_sale);
                    }
                }
            }
            // cotizacion
            else if($cash_document->quotation)
            {
                $final_balance += ($cash_document->quotation->applyQuotationToCash())
                    ? $cash_document->quotation->getTransformTotal()
                    : 0;
            }


        }

        $incomes=Income::where('user_id', $cash->user_id)->whereTypeUser();
        $incomes=$incomes->whereBetween('date_of_issue',[$cash->date_opening,$cash->date_closed]);
        $incomes=$incomes->whereBetween('time_of_issue',[$cash->time_opening,$cash->time_closed]);
        $incomes=$incomes->get();

        if (isset($incomes[0])) {
            foreach ($incomes as $income) {
                if (in_array($income->state_type_id, ['01','03','05','07','13'])) {
                    $final_balance += ($income->currency_type_id == 'VES')
                        ? $income->total
                        : ($income->total * $income->exchange_rate_sale);
                }
            }
        }


        $cash->final_balance = round($final_balance + $cash->beginning_balance, 2);
        $cash->income = round($final_balance, 2);
        $cash->state = false;
        $cash->save();

        return [
            'success' => true,
            'message' => 'Caja cerrada con éxito',
        ];

    }

    /**
     *
     * Usado en:
     * CashController - App
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function cash_document(Request $request)
    {
        $cash = Cash::where([
            ['user_id', auth()->id()],
            ['state', true],
        ])->firstOrFail();

        $isDocument = $request->document_id !== null;
        $documentModel = $isDocument ? Document::class : SaleNote::class;
        $documentField = $isDocument ? 'document_id' : 'sale_note_id';
        $paymentConditionField = $isDocument ? 'payment_condition_id' : 'payment_method_type_id';
        $creditCondition = $isDocument ? '02' : '09';

        $document = $documentModel::findOrFail((int) $request->$documentField);

        $isCredit = $document->$paymentConditionField === $creditCondition;
        // Evitar duplicicdad si ya existe

        $cashDocumentCredit = $isCredit ? CashDocumentCredit::updateOrCreate([
            'cash_id' => $cash->id,
            $documentField => $document->id,
        ]) : null;

        // NOTA: Se esta colocando dentro de los eventos de los modelos para poder registrarlo en caja
        // Gracias al updateOrCreate la información que primero se creo dentro de evento del modelo, no duplicara la información sino solo
        // lo actualiza
        $cashDocument = $cash->cash_documents()->updateOrCreate([
            'document_id' => $request->document_id,
            'sale_note_id' => $request->sale_note_id,
            'quotation_id' => $request->quotation_id,
        ]);

        $document->payments->each(function($payment) use($cash,$isDocument,$cashDocument){
            if ($isDocument && $payment->source_sale_note_payment_id) return;
            CashDocumentPayment::updateOrCreate([
                'cash_id' => $cash->id,
                $isDocument ? 'document_payment_id' : 'sale_note_payment_id' => $payment->id,
                'cash_document_id' => optional($cashDocument)->id,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Venta con éxito',
        ]);
    }


    /**
     *
     * Usado en:
     * CashController - App
     *
     * @param  int $id
     * @return array
     */
    public function destroy($id)
    {

        $data = DB::connection('tenant')->transaction(function () use ($id) {

            $cash = Cash::findOrFail($id);

            if($cash->global_destination()->where('payment_type', '!=', CashTransaction::class)->count() > 0){
                return [
                    'success' => false,
                    'message' => 'No puede eliminar la caja, tiene transacciones relacionadas'
                ];
            }

            $this->destroyCashTransaction($cash);
            $cash->delete();

            return [
                'success' => true,
                'message' => 'Caja eliminada con éxito'
            ];

        });

        return $data;

    }


    public function destroyCashTransaction($cash){

        $ini_cash_transaction = $cash->cash_transaction;

        if($ini_cash_transaction){
            CashTransaction::find($ini_cash_transaction->id)->delete();
        }

    }


    public function report_general()
    {
        $cashes = Cash::select('id')->whereDate('date_opening', date('Y-m-d'))->pluck('id');
        $cash_documents =  CashDocument::whereIn('cash_id', $cashes)->get();
        // dd($cash_documents);

        $company = Company::first();
        set_time_limit(0);

        $pdf = PDF::loadView('tenant.cash.report_general_pdf', compact("cash_documents", "company"));
        $filename = "Reporte_POS";
        return $pdf->download($filename.'.pdf');

    }


    /**
     * Legacy (web y app móvil): productos PDF inline.
     * La lógica vive en Modules\CashReport.
     *
     * @param  int $id
     * @param  bool $is_garage
     */
    public function report_products($id, $is_garage = false)
    {
        $cash = Cash::findOrFail($id);
        $content = app(CashReportRenderer::class)->pdfContent($is_garage ? 'products_garage' : 'products', $cash);

        $temp = tempnam(sys_get_temp_dir(), 'cash_report_products');
        file_put_contents($temp, $content);

        return response()->file($temp, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Reporte"'
        ]);
    }

    /**
     * Legacy web: productos Excel.
     *
     * @deprecated Usar cash-reports/generate/products/{cash}?format=excel
     */
    public function report_products_excel($id)
    {
        $cash = Cash::findOrFail($id);
        $renderer = app(CashReportRenderer::class);

        return $renderer->excelExport('products', $cash)->download($renderer->filename('products', $cash).'.xlsx');
    }

    /**
     * Compatibilidad: data del reporte de productos.
     */
    public function getDataReport($id, $is_garage = false)
    {
        return app(ProductsBuilder::class)->getDataReport($id, $is_garage);
    }


    public static function CalculeTotalOfCurency(
        $total = 0,
        // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
        $currency_type_id = 'VES',
        // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
        $exchange_rate_sale = 1
    ) {
        // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
        if ($currency_type_id !== 'VES') {
        // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
            $total = $total * $exchange_rate_sale;
        }
        return $total;
    }

    public static function getStateTypeId(){
        return [
            '01', //Registrado
            '03', // Enviado
            '05', // Aceptado
            '07', // Observado
            // '09', // Rechazado
            // '11', // Anulado
            '13' // Por anular
        ];
    }

    public static function FormatNumber($number = 0, $decimal = 2, $decimal_separador = '.', $miles_separador = '') {
        return number_format($number, $decimal, $decimal_separador, $miles_separador);
    }

    public static function getStringPaymentMethod($payment_id) {
        $payment_method = PaymentMethodType::find($payment_id);
        return (!empty($payment_method)) ? $payment_method->description : '';
    }


}
