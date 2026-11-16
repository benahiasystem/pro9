<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Support\Exact\Decimal;
use Modules\Report\Services\OrderNoteConsolidatedAmounts;

class OrderNoteConsolidatedCollection extends ResourceCollection
{
    private ?array $amounts = null;

    public function withAmounts(array $amounts): self
    {
        $this->amounts = $amounts;
        return $this;
    }
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        
        // ######## INICIO CALCULOS EXACTOS SAB ########
        $amounts = $this->amounts ?? OrderNoteConsolidatedAmounts::rows($this->collection);
        return $this->collection->transform(function($row, $key) use ($amounts){
            $line = $amounts[$row->id];
             
            return [
                'id' => $row->id,
                'user' => $row->order_note->user->name,  
                'customer' => $row->order_note->customer->name,  
                'delivery_date' => $row->order_note->delivery_date,  
                'item_description' => $row->item->description,  
                'item_quantity' => Decimal::represent(Decimal::readRequired($line, 'quantity')),
                'total' => Decimal::format(Decimal::readRequired($line, 'total_sale'), 2, '.', ''),
                'currency_type_id' => 'VES',
                'calculation_snapshot' => $line['calculation_snapshot'],
            ];
        });
        // ######## FIN CALCULOS EXACTOS SAB ########
    }

    
}
