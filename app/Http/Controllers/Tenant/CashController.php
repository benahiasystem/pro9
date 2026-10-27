<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
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

        return compact('users', 'user') + ['report_version'=>2];
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

            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            if ($cash->exists) $cash=Cash::whereKey($cash->id)->lockForUpdate()->firstOrFail();
            if ($cash->exists) \App\Services\Payments\CashLedger::authorize($cash);
            if (!$cash->exists && auth()->user()->type !== 'admin' && (string)$request->input('user_id',auth()->id()) !== (string)auth()->id()) abort(403);
            $cash->fill($cash->exists ? $request->except(['beginning_balance','final_balance','income','state','user_id','date_opening','time_opening','date_closed','time_closed']) : $request->all());
            if (!$cash->exists) {
                $cash->beginning_balance=$request->input('opening_balances.VES',$request->input('beginning_balance','0'));
                $cash->final_balance='0'; $cash->income='0'; $cash->state=true;
            }
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

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


    public function createCashTransaction($cash, $request)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        \App\Services\Payments\CashLedger::open($cash,$request->all());
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }


    /**
     *
     * Usado en:
     * CashController - App
     *
     * @param  int $id
     * @return array
     */
    public function close($id)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $cash=Cash::findOrFail($id);
        if (RestaurantTable::where('status','notavailable')->exists()) return [
            'success'=>false,'message'=>'No se puede cerrar caja, existen mesas abiertas.',
        ];
        return \App\Services\Payments\CashLedger::close($cash);
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
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
        ], $isDocument ? ['status'=>$document->balance <= 0.005 ? 'PROCESSED' : 'PENDING'] : []) : null;

        // NOTA: Se esta colocando dentro de los eventos de los modelos para poder registrarlo en caja
        // Gracias al updateOrCreate la información que primero se creo dentro de evento del modelo, no duplicara la información sino solo
        // lo actualiza
        $cashDocument = $cash->cash_documents()->updateOrCreate([
            'document_id' => $request->document_id,
            'sale_note_id' => $request->sale_note_id,
            'quotation_id' => $request->quotation_id,
        ]);

        $document->payments->each(function($payment) use($cash,$isDocument,$cashDocument){
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
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        return DB::connection('tenant')->transaction(function() use($id) {
            $cash=Cash::whereKey($id)->lockForUpdate()->firstOrFail();
            \App\Services\Payments\CashLedger::authorize($cash);
            $receipts=\Modules\Finance\Models\GlobalPayment::where('cash_session_id',$id);
            if ((clone $receipts)->where('payment_type','!=',CashTransaction::class)->exists() || $cash->cash_documents()->exists()) return ['success'=>false,'message'=>'La caja tiene movimientos o documentos relacionados.'];
            $receipts->delete();
            CashTransaction::where('cash_id',$id)->delete();
            $cash->getConnection()->table('cash_currency_balances')->where('cash_id',$id)->delete();
            $cash->delete();
            return ['success'=>true,'message'=>'Caja eliminada con éxito'];
        });
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }





    public function report_general()
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $sessions=Cash::whereTypeUser()->whereDate('date_opening',date('Y-m-d'))->get();
        $classified_sessions=[];
        foreach ($sessions as $session) $classified_sessions[]=(new \Modules\CashReport\Services\ClassifiedCashReport())->build($session,'cash_summary',[]);
        $company=Company::first();
        $pdf=PDF::loadView('tenant.cash.report_general_pdf',compact('company','classified_sessions'));
        return $pdf->download('Reporte_POS.pdf');
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
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
            $total = (float) \App\Services\ExchangeRates\ExchangeRateMath::finalAmount(\App\Services\ExchangeRates\ExchangeRateMath::rational($total)->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($exchange_rate_sale)), 2);
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
