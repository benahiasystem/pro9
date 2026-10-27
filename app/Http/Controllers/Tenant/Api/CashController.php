<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
namespace App\Http\Controllers\Tenant\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant\Cash;
use App\Models\Tenant\CashDocument;
use App\Models\Tenant\Document;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\CashDocumentCredit;
use App\Models\Tenant\CashDocumentPayment;


class CashController extends Controller
{

    /**
     * web service para recibo de documentos junto con caja
     * apertura y cierre de caja
     * creacion de relaciones de documentos con caja (notas de venta y facturas/boletas)
     *
     * @param $request {}
     * example:
     * beginningBalance: number
     * dateOpening: "Y-m-d"
     * timeOpening: "H:m:s"
     * internalsId: [{external_id: String, type: String NOTA|BOLETA|'' }]
     */
    public function storeRestaurant(Request $request)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $request->validate(['dateOpening'=>'required|date_format:Y-m-d','timeOpening'=>'required|date_format:H:i:s','internalsId'=>'required|array','internalsId.*.external_id'=>'required|uuid','internalsId.*.type'=>'required|string']);
        return \App\Services\Payments\RestaurantCashImport::store($request->all());
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }

    public function cash_document(Request $request) {

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
        $cashDocumentCredit = $isCredit ? CashDocumentCredit::create([
            'cash_id' => $cash->id,
            $documentField => $document->id,
        ]) : null;

        $cashDocument = $cash->cash_documents()->updateOrCreate([
            'document_id' => $request->document_id,
            'sale_note_id' => $request->sale_note_id,
            'quotation_id' => $request->quotation_id,
        ]);

        $document->payments->each(function($payment) use($cash,$isDocument,$cashDocument){
            CashDocumentPayment::create([
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

    public function opening_cash()
    {
        $cash = Cash::where([['user_id', auth()->user()->id],['state', true]])->first();

        return [
            'success' => ($cash)?true:false,
            'message' => 'Verificar si existe caja abierta',
            'data' => [
                'cash_id' => ($cash)?$cash->id:null,
                'cash' => $cash ? $cash->getApiRowResource() : null,
                'description' => ($cash)?$cash->reference_number . " " . $cash->date_opening . " (" . $cash->user->name . ")":'',
            ]
        ];
    }

    public function opening_cash_check($cash_id)
    {
        $cash = Cash::where([['id', $cash_id],['state', true]])->first();

        return [
            'success' => ($cash)?true:false,
            'message' => 'Verificar si existe caja abierta',
            'data' => [
                'cash_id' => ($cash)?$cash->id:null,
                'description' => ($cash)?$cash->reference_number . " " . $cash->date_opening . " (" . $cash->user->name . ")":'',
            ]
        ];
    }

    public function cash_available()
    {
        $cash = Cash::where('state', true)
            ->with('user')
            ->get()
            ->map(function($cash) {
                return [
                    'id' => $cash->id,
                    'description' => $cash->reference_number . " " . $cash->date_opening . " (" . $cash->user->name . ")",
                ];
            });

        return [
            'success' => true,
            'message' => 'Cajas disponibles',
            'data' => ($cash)?$cash:[]
        ];
    }

    public function close()
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $cash=Cash::where('user_id',auth()->id())->where('state',true)->first();
        if (!$cash) return ['success'=>false,'message'=>'Caja no encontrada'];
        return \App\Services\Payments\CashLedger::close($cash);
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }

}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
