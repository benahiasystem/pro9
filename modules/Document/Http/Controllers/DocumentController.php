<?php

namespace Modules\Document\Http\Controllers;

use App\Database\IntegerTransport;
use App\Http\Controllers\SearchItemController;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Series;
use App\Services\SeriesResolver;
use App\Models\Tenant\Person;
use App\Models\Tenant\StateType;
use Modules\Inventory\Models\Warehouse as ModuleWarehouse;
use App\Models\Tenant\Item;
use Modules\Document\Traits\SearchTrait;
use Modules\Item\Models\ItemLot;
use Modules\Document\Http\Resources\ItemLotCollection;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Company;
use App\Services\Fiscal\FiscalAmounts;
use App\Services\Fiscal\PrepaymentReference;
use App\Models\Tenant\SaleNoteItem;
use Illuminate\Database\Eloquent\Builder;


class DocumentController extends Controller
{
    use SearchTrait;

    public function data_table()
    {

        $customers = Person::whereType('customers')->orderBy('name')->take(20)->get()->transform(function ($row) {
            return [
                'id' => $row->id,
                'description' => $row->number . ' - ' . $row->name,
                'name' => $row->name,
                'number' => $row->number,
                'identity_document_type_id' => $row->identity_document_type_id,
            ];
        });

        $document_types = DocumentType::whereIn('id', ['01', '03', '07', '08'])->get();
        $series = app(SeriesResolver::class)->applyContext(Series::whereIn('document_type_id', ['01', '03', '07', '08']))->get();
        $establishments = Establishment::where('id', auth()->user()->establishment_id)->get();
        $state_types = StateType::get();

        return compact('customers', 'document_types', 'series', 'establishments', 'state_types');

    }







    public function dataTableCustomers(Request $request)
    {


        $customers = Person::where('number', 'like', "%{$request->input}%")
            ->orWhere('name', 'like', "%{$request->input}%")
            ->whereType('customers')->orderBy('name')
            ->get()->transform(function ($row) {
                return [
                    'id' => $row->id,
                    'description' => $row->number . ' - ' . $row->name,
                    'name' => $row->name,
                    'number' => $row->number,
                    'identity_document_type_id' => $row->identity_document_type_id,
                ];
            });

        return compact('customers');
    }




    public function prepayments($type)
    {
        $query = Document::whereHasPrepayment()->whereAffectationTypePrepayment($type);
        $context = request()->only(['customer_id', 'currency_type_id', 'establishment_id']);
        if ($context !== []) {
            $customer = IntegerTransport::positiveKey($context['customer_id'] ?? null);
            $establishment = IntegerTransport::positiveKey($context['establishment_id'] ?? null);
            $currency = $context['currency_type_id'] ?? null;
            if ($customer === null || $establishment === null || !in_array($currency, ['VES', 'USD'], true)) {
                FiscalAmounts::error('prepayments', 'Indique el cliente, la moneda y la sucursal para consultar anticipos.');
            }

            $query->where('customer_id', $customer)
                ->where('currency_type_id', $currency)
                ->where('establishment_id', $establishment)
                ->where('fiscal_environment', Company::firstOrFail()->fiscal_environment)
                ->whereIn('document_type_id', ['01', '03']);
        }

        return $query->get()->map(fn (Document $row) => PrepaymentReference::available($row, $row->series . '-' . $row->number));
    }


    public function searchItems(Request $request)
    {
        $requestedItems = $request->input('items_id');
        if ($requestedItems !== null) {
            if (!is_array($requestedItems)) {
                FiscalAmounts::error('items_id', 'Indique una lista de identificadores de productos.');
            }
            foreach ($requestedItems as $index => $id) {
                if (IntegerTransport::positiveKey($id) === null) {
                    FiscalAmounts::error("items_id.{$index}", 'Seleccione un producto válido.');
                }
            }
        }
        $items = SearchItemController::getItemsToDocuments($request, forInvoice: true);

        return compact('items');

    }


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Modules\Document\Http\Resources\ItemLotCollection
     */
    public function searchLots(Request $request)
    {
        $records = ItemLot::where('series', 'like', "%{$request->input}%");
        $sale_note_item_id = $request->has('sale_note_item_id') ? $request->sale_note_item_id : null;

        if ($request->document_item_id) {
            //proccess credit note
            $document_item = DocumentItem::query()
                ->findOrFail($request->document_item_id);
            /** @var array $lots */
            $lots = $document_item->item->lots;
            $records
                ->whereIn('id', collect($lots)->pluck('id')->toArray())
                ->where('has_sale', true)
                ->latest();

        } else if ($sale_note_item_id) {
            $records = $this->getRecordsForSaleNoteItem($records, $sale_note_item_id, $request);
        } else {
            $warehouse = ModuleWarehouse::query()
                ->select('id')
                ->where('establishment_id', auth()->user()->establishment_id)
                ->first();
            $records
                ->where('item_id', $request->item_id)
                ->where('has_sale', false)
                ->where('warehouse_id', $warehouse->id)
                ->latest();
        }

//        return new ItemLotCollection($records->get());
        return new ItemLotCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function searchItemLots(Request $request)
    {
        $query = ItemLot::query()
            ->where('series', 'like', "%{$request->input('input')}%");
        $sale_note_item_id = $request->has('sale_note_item_id') ? $request->input('sale_note_item_id') : null;
        $document_item_id = $request->has('document_item_id') ? $request->input('document_item_id') : null;
        $document_id = $request->has('document_id') ? $request->input('document_id') : null;
        $warehouse_id = $request->has('warehouse_id') ? $request->input('warehouse_id') : null;

        if ($document_id) {
            //proccess credit note
            $document_item = DocumentItem::query()
                ->where('document_id',$document_id)->first();
            /** @var array $lots */
            $lots = $document_item->item->lots;
            $query->whereIn('id', collect($lots)->pluck('id')->toArray())
                ->where('has_sale', true)
                ->latest();

        } else if ($sale_note_item_id) {
            $query = $this->getRecordsForSaleNoteItem($query, $sale_note_item_id, $request);
        } else {
            if (is_null($warehouse_id)) {
                $warehouse = ModuleWarehouse::query()
                    ->select('id')
                    ->where('establishment_id', auth()->user()->establishment_id)
                    ->first();
                $warehouse_id = $warehouse->id;
            }
            $query
                ->where('item_id', $request->input('item_id'))
                ->where('has_sale', false)
                ->where('state', 'Activo')
                ->where('warehouse_id', $warehouse_id)
                ->latest();
        }

        return new ItemLotCollection($query->paginate(config('tenant.items_per_page')));
    }


    /**
     *
     * Obtener series disponibles y vendidas en la nota de venta
     * Usado para edicion de item en nv
     *
     * @param ItemLot $records
     * @param int $sale_note_item_id
     * @param Request $request
     * @return Builder
     */
    public function getRecordsForSaleNoteItem($records, $sale_note_item_id, $request)
    {
        // obtener series disponibles
        $records->whereAvailableItemLot($request->item_id)->latest();

        // obtener series vendidas en la nv
        $sale_note_item = SaleNoteItem::findOrFail($sale_note_item_id);
        $lots = $sale_note_item->item->lots;

        $sale_lots = ItemLot::whereIn('id', collect($lots)->pluck('id')->toArray())->where('has_sale', true)->latest();

        return $sale_lots->union($records);
    }


    public function regularizeLots(Request $request)
    {

        $document_item = DocumentItem::findOrFail($request->document_item_id);

        return ItemLot::where('series', 'like', "%{$request->input}%")
            ->whereIn('id', collect($document_item->item->lots)->pluck('id')->toArray())
            ->where('has_sale', true)
            ->get();


    }


    public function searchItemById($id)
    {
        $itemId = IntegerTransport::positiveKey($id);
        abort_unless($itemId !== null, 404);
        $items = SearchItemController::getItemsToDocuments(null, $itemId, forInvoice: true);
        return compact('items');
    }
}
