<?php

namespace Modules\Item\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Item\Http\Requests\ProductVariableRequest;
use Modules\Item\Http\Resources\ProductVariableCollection;
use Modules\Item\Models\ProductVariable;
use Modules\Item\Models\ProductVariableValue;

class ProductVariableController extends Controller
{
    public function index()
    {
        return view('item::product-variables.index');
    }

    public function records(Request $request)
    {
        $records = ProductVariable::query()->orderBy('name');

        if ($request->boolean('active')) {
            $records->whereActive()
                ->with(['values' => function ($query) {
                    $query->whereActive()->orderBy('position');
                }]);
        } else {
            $records->with('values');
        }

        if ($request->input('value')) {
            $records->where('name', 'like', "%{$request->input('value')}%");
        }

        return new ProductVariableCollection($records->get());
    }

    public function record($id)
    {
        return ProductVariable::with('values')->findOrFail($id);
    }

    public function store(ProductVariableRequest $request)
    {
        $id = (int) $request->input('id');
        $warnings = [];

        $variable = DB::connection('tenant')->transaction(function () use ($request, $id, &$warnings) {

            $variable = ProductVariable::firstOrNew(['id' => $id]);
            $variable->name = trim($request->input('name'));
            $variable->value_type = $request->input('value_type');
            if ($request->has('active')) {
                $variable->active = (bool) $request->input('active');
            }
            $variable->save();

            $incoming_ids = collect($request->input('values'))->pluck('id')->filter()->all();

            $removed_values = $variable->values()->whereNotIn('id', $incoming_ids)->get();
            foreach ($removed_values as $removed) {
                try {
                    $removed->delete();
                } catch (Exception $e) {
                    if ($e->getCode() != '23000') {
                        throw $e;
                    }
                    // Valor en uso por variaciones existentes: se desactiva en lugar de eliminar
                    $removed->active = false;
                    $removed->save();
                    $warnings[] = "El valor \"{$removed->value}\" está en uso por variaciones, se desactivó en lugar de eliminarse";
                }
            }

            foreach ($request->input('values') as $index => $row) {
                $value = ProductVariableValue::firstOrNew([
                    'id' => isset($row['id']) ? $row['id'] : null,
                ]);
                $value->product_variable_id = $variable->id;
                $value->value = trim($row['value']);
                $value->color = isset($row['color']) ? $row['color'] : null;
                $value->position = $index;
                if (isset($row['active'])) {
                    $value->active = (bool) $row['active'];
                }
                $value->save();
            }

            return $variable;
        });

        return [
            'success' => true,
            'message' => ($id) ? 'Variable editada con éxito' : 'Variable registrada con éxito',
            'warnings' => $warnings,
            'data' => [
                'id' => $variable->id,
            ],
        ];
    }

    public function toggle($id)
    {
        $variable = ProductVariable::findOrFail($id);
        $variable->active = !$variable->active;
        $variable->save();

        return [
            'success' => true,
            'message' => $variable->active ? 'Variable activada' : 'Variable desactivada',
        ];
    }

    public function destroy($id)
    {
        try {
            $variable = ProductVariable::findOrFail($id);
            $variable->delete();

            return [
                'success' => true,
                'message' => 'Variable eliminada con éxito',
            ];
        } catch (Exception $e) {
            return ($e->getCode() == '23000')
                ? ['success' => false, 'message' => 'La variable está siendo usada por variaciones de productos, no puede eliminarse. Puede desactivarla.']
                : ['success' => false, 'message' => 'Error inesperado, no se pudo eliminar la variable'];
        }
    }
}
