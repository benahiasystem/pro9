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
        if (auth()->user()?->type !== 'admin') throw new \Illuminate\Auth\Access\AuthorizationException();
        $data=$request->validate(['id'=>['required','string','max:2'],'is_active'=>['required','boolean']]);
        \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use ($data) {
            $record=PaymentMethodType::whereKey($data['id'])->lockForUpdate()->firstOrFail();
            $record->is_active=$data['is_active'];
            $record->save();
        });
        return ['success'=>true,'message'=>'Disponibilidad actualizada'];
    }

    private function methodInput(PaymentMethodTypeRequest $request, ?PaymentMethodType $record = null): array
    {
        $fields=['id','description','number_days','is_active','hka_code'];
        $fields=array_merge($fields,['collection_kind','currency_type_id','iva_rate_type_id','bank_account_id','commission_percentage','minimum_commission']);
        $input = $request->only($fields);
        // ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
        if (\App\Services\Payments\HkaPaymentMethods::emissionMode() !== 'digital') {
            $code = $record->hka_code ?? null;
            $input['hka_code'] = \App\Services\Payments\HkaPaymentMethods::compatible($code, $request->input('collection_kind'), $request->input('currency_type_id')) ? $code : null;
        } elseif (!\App\Services\Payments\HkaPaymentMethods::compatible($request->input('hka_code'), $request->input('collection_kind'), $request->input('currency_type_id'))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['hka_code' => 'Seleccione un medio fiscal compatible con la naturaleza y moneda.']);
        }
        // ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
        $input['cash_currency_type_id'] = $request->input('collection_kind') === 'cash' ? $request->input('currency_type_id') : null;
        $input['iva_rate_type_id'] = $request->input('iva_rate_type_id');
        if ($request->input('collection_kind') !== 'account') {
            $input['bank_account_id'] = $input['commission_percentage'] = $input['minimum_commission'] = null;
        } else {
            $account = \App\Models\Tenant\BankAccount::query()->whereKey($request->input('bank_account_id'))->lockForUpdate()->first();
            if (!$account || !$account->status || (string)$account->bank_id !== (string)$request->input('bank_id')
                || $account->currency_type_id !== $request->input('currency_type_id')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['bank_account_id'=>'Seleccione una cuenta activa del banco y la moneda del método.']);
            }
        }
        return $input;
    }
    // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

    // ######## INICIO MONEDA E IMPUESTO DE MEDIOS ########
    public function tables()
    {
        $db = \Illuminate\Support\Facades\DB::connection('tenant');
        $admin = auth()->user()?->type === 'admin';
        return [
            'fiscal_emission_mode' => \App\Services\Payments\HkaPaymentMethods::emissionMode(),
            'hka_payment_methods' => \App\Services\Payments\HkaPaymentMethods::options(),
            'currency_types' => $db->table('cat_currency_types')->where('active', 1)->orderBy('id')->get(['id', 'description', 'symbol']),
            'taxes' => $db->table('cat_iva_rate_types')->where('id', 'IGTF')->where('tax_kind', 'IGTF')->get(['id', 'percentage']),
            'banks' => $admin ? $db->table('banks')->orderBy('description')->get(['id', 'description']) : [],
            'bank_accounts' => $admin ? $db->table('bank_accounts')->where('status', 1)->orderBy('number')->get(['id', 'bank_id', 'number', 'description', 'currency_type_id']) : [],
        ];
    }
    // ######## FIN MONEDA E IMPUESTO DE MEDIOS ########

    public function records(Request $request)
    {
        // ######## INICIO TRAZABILIDAD CONFIGURACION DEL MEDIO ########
        $records = PaymentMethodType::query()
            ->leftJoin('cat_iva_rate_types as method_tax', 'method_tax.id', '=', 'payment_method_types.iva_rate_type_id')
            ->leftJoin('bank_accounts as method_account', 'method_account.id', '=', 'payment_method_types.bank_account_id')
            ->leftJoin('banks as method_bank', 'method_bank.id', '=', 'method_account.bank_id')
            ->get(['payment_method_types.*', 'method_tax.percentage as iva_rate_percentage',
                'method_account.number as bank_account_number', 'method_bank.description as bank_name']);
        // ######## FIN TRAZABILIDAD CONFIGURACION DEL MEDIO ########

        return new PaymentMethodTypeCollection($records);
    }


    public function record($id)
    {
        $record = PaymentMethodType::findOrFail($id);

        return $record->toArray() + [
            'bank_id' => $record->bank_account_id ? \App\Models\Tenant\BankAccount::whereKey($record->bank_account_id)->value('bank_id') : null,
            'hka_compatible' => \App\Services\Payments\HkaPaymentMethods::compatible($record->hka_code, $record->collection_kind, $record->currency_type_id),
        ];
    }

    public function store(PaymentMethodTypeRequest $request)
    {
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use ($request) {
            $record = new PaymentMethodType();
            $record->fill($this->methodInput($request));
            $record->is_active = true;
            $record->save();
        });

        return ['success' => true, 'message' => 'Método de pago registrado con éxito'];
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
    }

    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    public function update(PaymentMethodTypeRequest $request, $id)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        return \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use ($request, $id) {
            $record = PaymentMethodType::whereKey($id)->lockForUpdate()->first();
            if (!$record) {
                return response()->json(['success' => false, 'message' => 'No se encontró el método de pago'], 404);
            }
            $record->fill(array_diff_key($this->methodInput($request, $record),['id'=>true]));
            $record->save();
            return ['success' => true, 'message' => 'Método de pago editado con éxito'];
        });
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

    public function destroy($id)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        if (auth()->user()?->type !== 'admin') throw new \Illuminate\Auth\Access\AuthorizationException();
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
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
