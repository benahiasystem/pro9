<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Item;

class ItemAffectationsIgvController extends Controller
{
    public function records()
    {
        $records = AffectationIgvType::all();

        return $records->transform(function($row) {
            return $row->getRowResource();
        });
    }

    public function changeActive($id, $active)
    {

        $validate = $this->validateAffectationItem($id);
        $affiliation = AffectationIgvType::find($id);

        if ($validate && $active == 0 && $affiliation->active == 1) {
            return [
                'success' => false,
                // ########## INICIO CAMBIO IGV A IVA
                'message' => 'No se puede desactivar esta Afectación IVA porque está asociada a comprobantes/nota de venta.',
                // ######### FIN CAMBIO IGV A IVA
            ];
        }

        $record = AffectationIgvType::findOrFail($id);
        $record->active = $active;
        $record->save();


        return [
            'success' => true,
            // ########## INICIO CAMBIO IGV A IVA
            'message' => 'Afectación IVA actualizada correctamente',
            // ######### FIN CAMBIO IGV A IVA
        ];
    }

    private function validateAffectationItem($id)
    {
       $validate = Item::where('sale_affectation_igv_type_id', $id)->exists(); 

       return $validate;

    }
}