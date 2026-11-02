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
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        return app(\App\Http\Controllers\Tenant\CashController::class)->cash_document($request);
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
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
