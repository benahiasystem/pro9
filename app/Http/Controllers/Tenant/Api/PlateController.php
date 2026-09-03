<?php

namespace App\Http\Controllers\Tenant\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API de placas asociadas a un cliente (giro de negocio "grifo/taps").
 *
 * Equivalente movil de BusinessTurnController@savePlates y @getPlates
 * (modulo BusinessTurn), que solo estan expuestos por web.
 */
class PlateController extends Controller
{
    /**
     * Placas registradas de un cliente.
     *
     * GET /api/plates/{person_id}
     *
     * @param  int $person_id
     * @return array
     */
    public function records($person_id)
    {
        $customer = $this->findCustomer($person_id);

        if (!$customer) {
            return $this->customerNotFound();
        }

        return [
            'success' => true,
            'data' => $this->platesOf($customer->id),
        ];
    }

    /**
     * Registrar una placa para un cliente.
     *
     * POST /api/plates
     * Body: { "person_id": 1, "value": "ABC-123" }
     *
     * Acepta tambien la clave "plates" para el valor, por compatibilidad con el
     * endpoint web bussiness_turns/plates.
     *
     * @param  Request $request
     * @return array
     */
    public function store(Request $request)
    {
        // El endpoint web manda la placa en "plates"; aqui la clave principal es
        // "value", igual que la columna y que la respuesta del GET.
        if (!$request->filled('value') && $request->filled('plates')) {
            $request->merge(['value' => $request->input('plates')]);
        }

        $request->validate([
            'person_id' => 'required|integer',
            'value' => 'required|string|max:20',
        ]);

        $customer = $this->findCustomer($request->input('person_id'));

        if (!$customer) {
            return $this->customerNotFound();
        }

        // Las placas se guardan normalizadas (sin espacios extra y en mayusculas)
        // para que el POS no muestre la misma placa repetida con otro formato.
        $value = strtoupper(trim($request->input('value')));

        $plate = DB::connection('tenant')->table('plates')
            ->where('person_id', $customer->id)
            ->where('value', $value)
            ->first();

        if ($plate) {
            return [
                'success' => true,
                'message' => 'La placa ya estaba registrada para el cliente',
                'id' => $plate->id,
                'data' => $this->platesOf($customer->id),
            ];
        }

        $id = DB::connection('tenant')->table('plates')->insertGetId([
            'value' => $value,
            'person_id' => $customer->id,
        ]);

        return [
            'success' => true,
            'message' => 'Placa guardada con éxito',
            'id' => $id,
            'data' => $this->platesOf($customer->id),
        ];
    }

    /**
     * Eliminar una placa del cliente.
     *
     * POST /api/plates/{plate}/destroy
     *
     * @param  int $plate
     * @return array
     */
    public function destroy($plate)
    {
        $deleted = DB::connection('tenant')->table('plates')->where('id', $plate)->delete();

        if (!$deleted) {
            return [
                'success' => false,
                'message' => 'La placa no existe',
            ];
        }

        return [
            'success' => true,
            'message' => 'Placa eliminada con éxito',
        ];
    }

    /**
     * Cliente habilitado por id.
     *
     * @param  int $person_id
     * @return Person|null
     */
    private function findCustomer($person_id)
    {
        return Person::whereType('customers')->whereIsEnabled()->find($person_id);
    }

    /**
     * Placas del cliente con el mismo formato que expone el POS.
     *
     * @param  int $person_id
     * @return array
     */
    private function platesOf($person_id)
    {
        return DB::connection('tenant')->table('plates')
            ->where('person_id', $person_id)
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'value' => $row->value,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array
     */
    private function customerNotFound()
    {
        return [
            'success' => false,
            'message' => 'El cliente no existe o no está habilitado',
            'data' => [],
        ];
    }
}
