<?php
namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\Catalogs\UnitType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\UnitTypeCollection;
use App\Http\Resources\Tenant\UnitTypeResource;
use Illuminate\Http\Request;

class UnitTypeController extends Controller
{
    public function records(Request $request)
    {
        $records = UnitType::query();
        $active = $request->input('active');

        if ($active === '1' || $active === 1 || $active === '0' || $active === 0) {
            $records->where('active', $active ? 1 : 0);
        }

        return new UnitTypeCollection($records->get());
    }

    public function record($id)
    {
        $record = new UnitTypeResource(UnitType::findOrFail($id));

        return $record;
    }

    public function store(Request $request)
    {
        return $this->catalogClosed();
    }

    public function active(Request $request)
    {
        $id = $request->input('id');
        $unit_type = UnitType::findOrFail($id);
        // ######## INICIO CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
        if (UnitType::isReserved((string) $id) && !$request->boolean('active')) {
            return [
                'success' => false,
                'message' => 'UND y SERV deben permanecer activos.',
            ];
        }
        // ######## FIN CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
        $unit_type->active = $request->boolean('active') ? 1 : 0;
        $unit_type->save();

        return [
            'success' => true,
            'message' => 'Estado actualizado con éxito',
        ];
    }

    public function destroy($id)
    {
        return $this->catalogClosed();
    }

    private function catalogClosed()
    {
        return response()->json([
            'success' => false,
            'code' => 'UNIT_CATALOG_CLOSED',
            'message' => 'No se permite crear, editar ni eliminar unidades de medida.',
        ], 409);
    }
}
