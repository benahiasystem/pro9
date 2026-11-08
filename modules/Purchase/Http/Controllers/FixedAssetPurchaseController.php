<?php

namespace Modules\Purchase\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Person;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\Catalogs\ChargeDiscountType;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\DocumentType;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Catalogs\PriceType;
use App\Models\Tenant\Catalogs\AttributeType;
use App\Models\Tenant\Company;
use Illuminate\Support\Str;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;
use Carbon\Carbon;

use Modules\Purchase\Http\Requests\FixedAssetPurchaseRequest;
use Modules\Purchase\Models\FixedAssetPurchase;
use Modules\Purchase\Http\Resources\FixedAssetPurchaseCollection;
use Modules\Purchase\Http\Resources\FixedAssetPurchaseResource;
use Modules\Purchase\Models\FixedAssetItem;


class FixedAssetPurchaseController extends Controller
{


    public function index()
    {
        return view('purchase::fixed_asset_purchases.index');
    }


    public function create($id = null)
    {
        return view('purchase::fixed_asset_purchases.form', compact('id'));
    }

    public function columns()
    {
        return [
            'number' => 'Número',
            'date_of_issue' => 'Fecha de emisión',
            'date_of_due' => 'Fecha de vencimiento',
            'name' => 'Nombre proveedor',
        ];
    }

    public function records(Request $request)
    {

        $records = $this->getRecords($request);

        return new FixedAssetPurchaseCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function searchItems(Request $request)
    {
        $fixed_asset_items = FixedAssetItem::orderBy('description')->where('name', 'like', "%{$request->input}%")->get();
        $items =  $fixed_asset_items->transform(function($row) {
                    $full_description = ($row->internal_id) ? $row->internal_id.' - '.$row->name:$row->name;
                            return [
                                'id' => $row->id,
                                'full_description' => $full_description,
                                'description' => $row->name,
                                'currency_type_id' => $row->currency_type_id,
                                'currency_type_symbol' => $row->currency_type->symbol,
                                'purchase_unit_price' => $row->purchase_unit_price,
                                'unit_type_id' => $row->unit_type_id,
                                'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                            ];
                    });

        return compact('items');
    }

    public function getRecords($request){

        switch ($request->column) {
            case 'name':
                $records = FixedAssetPurchase::whereHas('supplier', function($query) use($request){
                                return $query->where($request->column, 'like', "%{$request->value}%");
                            });

                break;

            default:
                $records = FixedAssetPurchase::where($request->column, 'like', "%{$request->value}%");

                break;
        }

        return $records->whereTypeUser()->latest();

    }

    public function tables()
    {
        $suppliers = $this->table('suppliers');
        $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
        $currency_types = CurrencyType::whereActive()->get();
        $document_types_invoice = DocumentType::whereIn('id', ['01', '03', 'GU75', 'NE76'])->get();
        $discount_types = ChargeDiscountType::whereType('discount')->whereLevel('item')->get();
        $charge_types = ChargeDiscountType::whereType('charge')->whereLevel('item')->get();
        $company = Company::active();

        return compact('suppliers', 'establishment','currency_types', 'discount_types',
                    'charge_types', 'document_types_invoice','company');
    }

    public function item_tables()
    {

        $fixed_asset_items = $this->table('fixed_asset_items');
        $affectation_igv_types = AffectationIgvType::whereActive()->get();
        $price_types = PriceType::whereActive()->get();
        $discount_types = ChargeDiscountType::whereType('discount')->whereLevel('item')->get();
        $charge_types = ChargeDiscountType::whereType('charge')->whereLevel('item')->get();
        $attribute_types = AttributeType::whereActive()->orderByDescription()->get();
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        $show_ubl_attributes = \App\Services\LocalFiscalDocumentPolicy::showUblAttributes();
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

        return compact('fixed_asset_items', 'affectation_igv_types', 'price_types',
                        'discount_types', 'charge_types', 'attribute_types', 'show_ubl_attributes');
    }

    public function record($id)
    {

        $record = new FixedAssetPurchaseResource(FixedAssetPurchase::findOrFail($id));

        return $record;
    }


    public function store(FixedAssetPurchaseRequest $request)
    {
        $data = self::convert($request);
        $purchase = DB::connection('tenant')->transaction(function () use ($data, $request) {
            if (empty($data['items']) || !is_array($data['items'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items'=>'Agregue al menos una partida.']);
            }
            $id = $request->input('id');
            $doc = $id ? FixedAssetPurchase::whereTypeUser()->lockForUpdate()->findOrFail($id) : new FixedAssetPurchase();
            if ($doc->exists && in_array($doc->state_type_id, ['09','11'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['id'=>'No se puede editar una compra anulada o rechazada.']);
            }
            $previousCurrency = $doc->exists ? $doc->currency_type_id : null;
            $header = collect($data)->only(['establishment_id','document_type_id','series','number',
                'date_of_issue','date_of_due','time_of_issue','supplier_id','supplier','currency_type_id','exchange_rate_sale',
                'total_prepayment','total_perception','perception_number','perception_date','charges','discounts','prepayments',
                'guides','related','perception','legends','customer_id','group_id'])->all();
            if (!$doc->exists) $header += ['user_id'=>$data['user_id'],'external_id'=>$data['external_id'],
                'fiscal_environment'=>$data['fiscal_environment'],'state_type_id'=>'01','group_id'=>$data['group_id'],'total'=>'0'];
            $doc->fill($header);
            $doc->save();
            \App\Support\Exact\CommercialCalculation::defer($doc, function () use ($doc, $data, $previousCurrency) {
                $existing = $doc->items()->withoutEagerLoads()->lockForUpdate()->get()->keyBy('id');
                $seen = [];
                foreach ($data['items'] as $row) {
                    $row = (array) $row;
                    $row['item'] = (array) ($row['item'] ?? []);
                    if (!in_array($row['affectation_igv_type_id'] ?? null, ['10','20'], true)) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['items'=>'Seleccione una afectación de IVA vigente.']);
                    }
                    $asset = FixedAssetItem::query()->findOrFail($row['item']['id']);
                    $rowId = $row['id'] ?? null;
                    if ($rowId !== null) {
                        if (isset($seen[$rowId]) || !$existing->has($rowId)) {
                            throw \Illuminate\Validation\ValidationException::withMessages(['items'=>'La partida no pertenece a esta compra o está repetida.']);
                        }
                        $seen[$rowId] = true;
                        $line = $existing->get($rowId);
                        if ($previousCurrency !== $doc->currency_type_id && empty($row['price_input'])) {
                            throw \Illuminate\Validation\ValidationException::withMessages(['items'=>'Indique el precio y la moneda de origen de cada partida al cambiar la moneda.']);
                        }
                        if ((int) $line->fixed_asset_item_id !== (int) $asset->id) {
                            throw \Illuminate\Validation\ValidationException::withMessages(['items'=>'No se puede sustituir el activo de una partida existente.']);
                        }
                    } else {
                        $line = $doc->items()->make();
                        $line->fixed_asset_item_id = $asset->id;
                    }
                    $line->fill(collect($row)->only(['item','quantity','unit_price','affectation_igv_type_id',
                        'price_type_id','discounts','charges','attributes','quantity_input','price_input'])->all());
                    $line->save();
                }
                foreach ($existing as $line) if (!isset($seen[$line->id])) $line->delete();
            });
            return $doc;
        });

        return [
            'success' => true,
            'data' => [
                'id' => $purchase->id,
                'number_full' => $purchase->number_full,
            ],
        ];
    }



    public function voided($id)
    {

        $obj =  FixedAssetPurchase::find($id);
        $obj->state_type_id = 11;
        $obj->save();

        return [
            'success' => true,
            'message' => 'Compra anulada con éxito'
        ];

    }

    public static function convert($inputs)
    {
        $company = Company::active();
        $values = [
            'user_id' => auth()->id(),
            'external_id' => Str::uuid()->toString(),
            'supplier' => PersonInput::set($inputs['supplier_id']),
            'fiscal_environment' => $company->fiscal_environment,
            'group_id' => ($inputs->document_type_id === '01') ? '01':'02',
            'state_type_id' => '01'
        ];

        $inputs->merge($values);

        return $inputs->all();
    }

    public function table($table)
    {
        switch ($table) {
            case 'suppliers':

                $suppliers = Person::whereType('suppliers')->orderBy('name')->get()->transform(function($row) {
                    return [
                        'id' => $row->id,
                        'description' => $row->number.' - '.$row->name,
                        'name' => $row->name,
                        'number' => $row->number,
                        'perception_agent' => (bool) $row->perception_agent,
                        'identity_document_type_id' => $row->identity_document_type_id,
                        'identity_document_type_code' => $row->identity_document_type->code
                    ];
                });
                return $suppliers;

                break;

            case 'fixed_asset_items':

                $fixed_asset_items = FixedAssetItem::orderBy('description')->get();

                return collect($fixed_asset_items)->transform(function($row) {

                            $full_description = ($row->internal_id) ? $row->internal_id.' - '.$row->name:$row->name;

                            return [
                                'id' => $row->id,
                                'full_description' => $full_description,
                                'description' => $row->name,
                                'currency_type_id' => $row->currency_type_id,
                                'currency_type_symbol' => $row->currency_type->symbol,
                                'purchase_unit_price' => $row->purchase_unit_price,
                                'unit_type_id' => $row->unit_type_id,
                                'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                            ];
                    });

                break;
            default:

                return [];

                break;
        }
    }

    public function delete($id)
    {

        try {

            DB::connection('tenant')->transaction(function () use ($id) {

                $row = FixedAssetPurchase::findOrFail($id);
                $row->delete();

            });

            return [
                'success' => true,
                'message' => 'Compra eliminada con éxito'
            ];

        } catch (Exception $e) {

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    public function getPersons($type){

        $persons = Person::whereType($type)->orderBy('name')->take(20)->get()->transform(function($row) {
            return [
                'id' => $row->id,
                'description' => $row->number.' - '.$row->name,
                'name' => $row->name,
                'number' => $row->number,
                'identity_document_type_id' => $row->identity_document_type_id,
            ];
        });

        return $persons;

    }


}
