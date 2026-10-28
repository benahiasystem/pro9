<?php

namespace Modules\Sale\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Sale\Http\Resources\PaymentMethodTypeCollection;
use App\Models\Tenant\PaymentMethodType;
use Exception;
use Modules\Sale\Http\Requests\PaymentMethodTypeRequest;

class PaymentMethodTypeController extends Controller
{

    // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########


    public function active(Request $request)
    {
        abort_unless(auth()->user()?->type === 'admin',403);
        $data=$request->validate(['id'=>['required','string','max:2'],'is_active'=>['required','boolean']]);
        $record=PaymentMethodType::findOrFail($data['id']);
        $record->is_active=$data['is_active'];$record->save();
        return ['success'=>true,'message'=>'Disponibilidad actualizada'];
    }

    private function methodInput(PaymentMethodTypeRequest $request): array
    {
        $fields=['id','description','number_days','is_active'];
        $fields=array_merge($fields,['collection_kind','currency_type_id','iva_rate_type_id']);
        $input = $request->only($fields);
        $input['cash_currency_type_id'] = $request->input('collection_kind') === 'cash' ? $request->input('currency_type_id') : null;
        $input['iva_rate_type_id'] = $request->input('iva_rate_type_id');
        return $input;
    }
    // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

    // ######## INICIO MONEDA E IMPUESTO DE MEDIOS ########
    public function tables()
    {
        $db = \Illuminate\Support\Facades\DB::connection('tenant');
        return [
            'currency_types' => $db->table('cat_currency_types')->where('active', 1)->orderBy('id')->get(['id', 'description', 'symbol']),
            'taxes' => $db->table('cat_iva_rate_types')->where('id', 'IGTF')->where('tax_kind', 'IGTF')->get(['id', 'percentage']),
        ];
    }
    // ######## FIN MONEDA E IMPUESTO DE MEDIOS ########

    public function records(Request $request)
    {
        $records = PaymentMethodType::get();

        return new PaymentMethodTypeCollection($records);
    }


    public function record($id)
    {
        $record = PaymentMethodType::findOrFail($id);

        return $record->toArray();
    }

    public function store(PaymentMethodTypeRequest $request)
    {
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        $record = new PaymentMethodType();
        $record->fill($this->methodInput($request));
        $record->is_active = true;
        $record->hka_code = '99';
        $record->save();

        return ['success' => true, 'message' => 'Método de pago registrado con éxito'];
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
    }

    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    public function update(PaymentMethodTypeRequest $request, $id)
    {
        $record = PaymentMethodType::find($id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'No se encontró el método de pago'], 404);
        }
        $record->fill(array_diff_key($this->methodInput($request),['id'=>true]));
        $record->save();

        return ['success' => true, 'message' => 'Método de pago editado con éxito'];
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

    public function destroy($id)
    {
        // ######### INICIO PROTECCIÓN MÉTODOS INICIALES VENEZUELA #########
        if (PaymentMethodType::isInitialPaymentMethodId($id)) {
            return [
                'success' => false,
                'message' => 'Los métodos de pago iniciales no se pueden eliminar',
            ];
        }
        // ######### FIN PROTECCIÓN MÉTODOS INICIALES VENEZUELA #########

        try {

            $record = PaymentMethodType::findOrFail($id);
            $record->delete();

            return [
                'success' => true,
                'message' => 'Método de pago eliminado con éxito'
            ];

        } catch (Exception $e) {

            return ($e->getCode() == '23000') ? ['success' => false,'message' => "El Método de pago esta siendo usado por otros registros, no puede eliminar"] : ['success' => false,'message' => "Error inesperado, no se pudo eliminar el Método de pago "];

        }

    }




}
