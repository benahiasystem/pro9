<?php
namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\Catalogs\TransferReasonType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\TransferReasonTypeRequest;
use App\Http\Resources\Tenant\TransferReasonTypeCollection;
use App\Http\Resources\Tenant\TransferReasonTypeResource;

class TransferReasonTypeController extends Controller
{
    public function records()
    {
        $records = TransferReasonType::query()
            ->whereIn('id', TransferReasonType::CONTRACT_IDS)
            ->orderByRaw("CASE id WHEN '04' THEN 1 WHEN '21' THEN 2 WHEN '22' THEN 3 WHEN '23' THEN 4 WHEN '24' THEN 5 ELSE 6 END")
            ->get();

        return new TransferReasonTypeCollection($records);
    }

    public function record($id)
    {
        $record = new TransferReasonTypeResource(
            TransferReasonType::whereIn('id', TransferReasonType::CONTRACT_IDS)->findOrFail($id)
        );

        return $record;
    }

    public function store(TransferReasonTypeRequest $request)
    {
        if ($request->hasAny(['description', 'active'])) {
            return $this->catalogClosed();
        }

        $id = $request->input('id');
        if (!TransferReasonType::isContractId($id)) {
            return $this->catalogClosed();
        }
        $record = TransferReasonType::whereIn('id', TransferReasonType::CONTRACT_IDS)->find($id);
        if (!$record) {
            return $this->catalogClosed();
        }
        $record->discount_stock = $request->boolean('discount_stock');
        $record->save();

        return [
            'success' => true,
            'message' => 'Configuración de inventario actualizada con éxito',
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
            'code' => 'TRANSFER_REASON_CATALOG_CLOSED',
            'message' => 'No se permite crear, renumerar, desactivar ni eliminar motivos de traslado.',
        ], 409);
    }
}
