<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Inventory\Observers;

use App\Models\Tenant\Company;
use Illuminate\Support\Str;
use Modules\Inventory\Models\Guide;
use Modules\Inventory\Models\InventoryTransfer;

class InventoryTransferObserver
{
    public function creating(InventoryTransfer $inventory_transfer)
    {
        $company = Company::query()->first();

        $inventory_transfer->user_id = auth()->id();
        $inventory_transfer->establishment_id = \Modules\Inventory\Models\Warehouse::query()->findOrFail($inventory_transfer->warehouse_id)->establishment_id;
        $inventory_transfer->series = \App\Services\SeriesNumbering::normalizeCode($inventory_transfer->series);
        $inventory_transfer->external_id = Str::uuid()->toString();
        $inventory_transfer->fiscal_environment = $company->fiscal_environment;

        $number = $this->getNumberDocument($inventory_transfer);
        $filename = \App\CoreFacturalo\Requests\Inputs\Functions::filename($company, $inventory_transfer->document_type_id, $inventory_transfer->series, $number, $inventory_transfer->establishment_id);
        $inventory_transfer->number = $number;
        $inventory_transfer->filename = $filename;
    }

    private function getNumberDocument($inventory_transfer)
    {
        return \App\Services\SeriesNumbering::next($inventory_transfer, $inventory_transfer->document_type_id, $inventory_transfer->series, $inventory_transfer->number, $inventory_transfer->establishment_id);

    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
