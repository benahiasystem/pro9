<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\ItemWarehousePrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Series;
use App\Services\SeriesResolver;
use App\Services\PosCatalogStock;
use App\Models\Tenant\PaymentMethodType;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\User;
use Modules\Inventory\Models\Warehouse;
use App\Models\Tenant\Cash;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\PriceLabel;
use Modules\Inventory\Models\InventoryConfiguration;
use Modules\Inventory\Models\ItemWarehouse;
use Exception;
use Modules\Item\Models\Category;
use Modules\Finance\Traits\FinanceTrait;
use App\Models\Tenant\Company;
use Modules\BusinessTurn\Models\BusinessTurn;
use App\Http\Resources\Tenant\PosCollection;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\PosCatalogPrice;
use App\Models\Tenant\Catalogs\{
    ChargeDiscountType
};

class PosController extends Controller
{

    use FinanceTrait;

    /**
     * Cuántos clientes devuelve como máximo la búsqueda del selector del POS.
     */
    const CUSTOMER_SEARCH_LIMIT = 30;

    /**
     * Cuántos clientes viajan al abrir el POS para que el selector no arranque
     * vacío. Es solo la primera pantalla del desplegable: el resto se busca.
     */
    const CUSTOMER_SEED_LIMIT = 50;

    public function index()
    {
        $cash = Cash::where([['user_id', auth()->user()->id], ['state', true]])->first();

        if (!$cash) return redirect()->route('tenant.cash.index', ['redirect_reason' => 'no_cash_pos']);

        $configuration = Configuration::first();

        $company = Company::select('fiscal_environment')->first();
        $company_environment = $company->fiscal_environment;
        $business_turns = BusinessTurn::select('active')->where('id', 4)->first();

        return view('tenant.pos.index', compact('configuration', 'company_environment', 'business_turns'));
    }

    public function index_full()
    {
        $cash = Cash::where([['user_id', auth()->user()->id], ['state', true]])->first();

        if (!$cash) return redirect()->route('tenant.cash.index', ['redirect_reason' => 'no_cash_pos']);

        return view('tenant.pos.index_full');
    }

    public function search_items(Request $request)
    {
        $configuration = Configuration::first();
        $search_item_by_barcode_presentation = $request->search_item_by_barcode_presentation == 'true';

        $establishment_id = auth()->user()->establishment_id;
        $warehouse = Warehouse::where('establishment_id', $establishment_id)->first();

        $input = $request->input_item;
        $items_query = $this->catalogItems()
            ->with(['warehouse_prices' => function ($q) use ($warehouse) {
                $q->where('warehouse_id', $warehouse->id);
            }, 'item_unit_types.prices.priceLabel'])
            ->where(function (Builder $query) use ($input, $search_item_by_barcode_presentation) {
                $query->where('description', 'like', "%{$input}%")
                    ->orWhere('internal_id', 'like', "%{$input}%")
                    ->orWhere('barcode', (string) $input)
                    ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', "%{$input}%"))
                    ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', "%{$input}%"));

                if ($search_item_by_barcode_presentation) {
                    $query->orFilterItemUnitTypeBarcode($input);
                }
            });

        $all_prices_label = PriceLabel::all();

        $items = $items_query->get();
        $stock = PosCatalogStock::forItems($items);
        $items->transform(function ($row) use ($configuration, $search_item_by_barcode_presentation, $request, $all_prices_label, $stock) {

            $full_description = ($row->internal_id) ? $row->internal_id . ' - ' . $row->description : $row->description;

            if($row->warehouse_prices->count() > 0)
            {
                $sale_unit_price = D::readRequired($row->warehouse_prices->first(), 'price');
            }
            else
            {
                $sale_unit_price = D::readRequired($row, 'sale_unit_price');
            }

            return [
                'id' => $row->id,
                'item_id' => $row->id,
                'pos_stock' => $stock[$row->id],
                'full_description' => $full_description,
                'description' => ($row->brand->name) ? $row->description . ' - ' . $row->brand->name : $row->description,
                'currency_type_id' => $row->currency_type_id,
                'internal_id' => $row->internal_id,
                'currency_type_symbol' => $row->currency_type->symbol,
                'sale_unit_price' => D::represent($sale_unit_price),
                'calculation_snapshot' => PosCatalogPrice::values($row, $sale_unit_price)['calculation_snapshot'],
                'purchase_unit_price' => $row->purchase_unit_price,
                'unit_type_id' => $row->unit_type_id,
                'aux_unit_type_id' => $row->unit_type_id,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                'calculate_quantity' => (bool)$row->calculate_quantity,
                'is_set' => (bool)$row->is_set,
                'edit_unit_price' => false,
                'has_igv' => (bool)$row->has_igv,
                'aux_quantity' => 1,
                'aux_sale_unit_price' => D::represent(D::readRequired($row, 'sale_unit_price')),
                'edit_sale_unit_price' => D::represent(D::readRequired($row, 'sale_unit_price')),
                'image_url' => ($row->image !== 'imagen-no-disponible.jpg') ? asset('storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'items' . DIRECTORY_SEPARATOR . $row->image) : asset("/logo/{$row->image}"),
                'sets' => collect($row->sets)->transform(function ($r) {
                    return [
                        $r->individual_item->description
                    ];
                }),
                'warehouses' => collect($row->warehouses)->transform(function ($row) {
                    return [
                        'warehouse_description' => $row->warehouse->description,
                        'stock' => $row->stock,
                    ];
                }),
                'item_unit_types' => $row->getItemUnitTypesForPos($configuration, $all_prices_label),
                'unit_type' => $row->getItemUnitTypesBarcode($search_item_by_barcode_presentation, $request->input_item),
                // 'unit_type' => $row->item_unit_types,
                'category' => ($row->category) ? $row->category->name : null,
                'brand' => ($row->brand) ? $row->brand->name : null,
                'search_item_by_barcode_presentation' => $search_item_by_barcode_presentation,

                'exchange_points' => $row->exchange_points,
                'quantity_of_points' => $row->quantity_of_points,
                'exchanged_for_points' => false, //para determinar si desea canjear el producto
                'used_points_for_exchange' => null, //total de puntos
                'original_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'restrict_sale_cpe' => $row->restrict_sale_cpe,
                'prices' => $row->warehouse_prices,
            ];
        });

        return compact('items');

    }

    public function tables()
    {
        $affectation_igv_types = AffectationIgvType::whereActive()->get();
        $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
        $currency_types = CurrencyType::whereActive()->get();

        $customers = $this->seedCustomers($establishment);
        $user = User::findOrFail(auth()->user()->id);

        $items = $this->table('items');
        $config_tap = BusinessTurn::configurationTaps()->first();

        $categories = Category::all();
        $payment_method_types = PaymentMethodType::getPaymentMethodTypes();
        return compact(
            'items',
            'customers',
            'affectation_igv_types',
            'establishment',
            'user',
            'currency_types',
            'payment_method_types',
            'categories',
            'config_tap'
        );

    }

    public function payment_tables()
    {

        // Series filtradas por contexto (oculta dedicadas / restringe al grupo activo). Ver SeriesResolver.
        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        $series = app(SeriesResolver::class)->applyContext(
            Series::whereIn('document_type_id', ['01', '80'])
                ->where([['establishment_id', auth()->user()->establishment_id], ['contingency', false]])
        )->get();
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

        $payment_method_types = PaymentMethodType::active()->NotCredit()->get();

        $payment_destinations = $this->getPaymentDestinations();
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        $global_discount_types = ChargeDiscountType::getGlobalDiscounts();
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES


        return compact('series', 'payment_method_types', 'payment_destinations', 'global_discount_types');

    }

    /**
     * Formato que espera el selector de clientes del POS (resources/js/views/tenant/pos).
     */
    private function transformCustomers($customers)
    {
        return $customers->transform(function ($row) {
            return [
                'id' => $row->id,
                'description' => $row->number . ' - ' . $row->name,
                'name' => $row->name,
                'number' => $row->number,
                'identity_document_type_id' => $row->identity_document_type_id,
                'identity_document_type_code' => optional($row->identity_document_type)->code,
                'has_discount' => $row->has_discount,
                'is_agent_retention' => $row->is_agent_retention,
                'discount_type' => $row->discount_type,
                'discount_amount' => $row->discount_amount,
                'email' => $row->email,
                'plates' => $row->plates->transform(function ($plate) {
                    return [
                        'id' => $plate->id,
                        'value' => $plate->value,
                    ];
                }),
            ];
        });
    }

    /**
     * Semilla del selector de clientes al abrir el POS.
     *
     * Antes se enviaba la cartera completa: con miles de clientes el <el-select>
     * (que no virtualiza) montaba decenas de miles de nodos y cada medición del
     * marquee forzaba un reflow por opción. Ahora viajan los primeros
     * CUSTOMER_SEED_LIMIT por nombre —para que el desplegable no arranque
     * vacío— más el cliente por defecto; el resto se busca con search_customers.
     */
    private function seedCustomers($establishment)
    {
        $customers = Person::whereType('customers')->whereSalesIdentityActive()->whereIsEnabled()
            ->with(['plates', 'identity_document_type'])
            ->orderBy('name')->take(self::CUSTOMER_SEED_LIMIT)->get();

        // El cliente por defecto del establecimiento tiene que estar sí o sí,
        // aunque alfabéticamente no entre en el tope.
        $customer_id = $establishment ? $establishment->customer_id : null;

        if ($customer_id && !$customers->contains('id', $customer_id)) {
            $default = Person::whereType('customers')->whereSalesIdentityActive()->whereIsEnabled()
                ->with(['plates', 'identity_document_type'])
                ->where('id', $customer_id)->first();

            if ($default) $customers->prepend($default);
        }

        return $this->transformCustomers($customers);
    }

    /**
     * Búsqueda remota del selector de clientes del POS.
     *
     * Acepta `input` (nombre o número de documento) o `id` para rehidratar un
     * cliente concreto (por ejemplo el recién creado desde el modal).
     */
    public function search_customers(Request $request)
    {
        $id = $request->input('id');
        $input = trim((string) $request->input('input', ''));

        $query = Person::whereType('customers')->whereSalesIdentityActive()->whereIsEnabled();

        if ($id) {
            $query->whereIn('id', (array) $id);
        } elseif ($input !== '') {
            $query->where(function ($q) use ($input) {
                $q->where('name', 'like', "%{$input}%")
                    ->orWhere('number', 'like', "%{$input}%");
            });
        } else {
            return ['data' => $this->seedCustomers(
                Establishment::where('id', auth()->user()->establishment_id)->first()
            )];
        }

        $customers = $query->with(['plates', 'identity_document_type'])
            ->orderBy('name')->take(self::CUSTOMER_SEARCH_LIMIT)->get();

        return ['data' => $this->transformCustomers($customers)];
    }

    public function table($table, ?Request $request = null)
    {
        if ($table === 'customers') {
            return $this->transformCustomers(
                Person::whereType('customers')->whereSalesIdentityActive()->whereIsEnabled()->orderBy('name')->get()
            );
        }

        if ($table === 'items') {

            $configuration = Configuration::first();
            $query = $this->catalogItems()->orderBy('description')->take(100);
            $warehouseId = null;
            $request ??= app()->bound('request') ? request() : null;
            if ($request?->query('document_type_id') === '01' && $configuration->active_warehouse_prices) {
                $warehouseId = auth()->user()->establishment->warehouse?->id;
                if ($warehouseId !== null) {
                    $query->with(['billingWarehousePrices' => fn ($prices) => $prices->where('warehouse_id', $warehouseId)]);
                }
            }
            $items = $query->get();
            $stock = PosCatalogStock::forItems($items);
            $items->transform(function (Item $row) use ($configuration, $stock, $warehouseId) {
                    $selected = $warehouseId === null ? null : Item::exactSaleUnitPriceByWarehouse($row, $warehouseId, true);
                    $price = PosCatalogPrice::values($row, $selected);
                    $full_description = ($row->internal_id) ? $row->internal_id . ' - ' . $row->description : $row->description;
                    $currency = $row->currency_type;
                    if(empty($currency )){
                        $currency = CurrencyType::first();
                    }
                    return [
                        'id' => $row->id,
                        'item_id' => $row->id,
                        'pos_stock' => $stock[$row->id],
                        'full_description' => $full_description,
                        'description' => ($row->brand->name) ? $row->description . ' - ' . $row->brand->name : $row->description,
                        'currency_type_id' => $row->currency_type_id,
                        'internal_id' => $row->internal_id,
                        'currency_type_symbol' => $currency->symbol,
                        ...$price,
                        'purchase_unit_price' => $row->purchase_unit_price,
                        'unit_type_id' => $row->unit_type_id,
                        'aux_unit_type_id' => $row->unit_type_id,
                        'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                        'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                        'calculate_quantity' => (bool)$row->calculate_quantity,
                        'has_igv' => (bool)$row->has_igv,
                        'is_set' => (bool)$row->is_set,
                        'edit_unit_price' => false,
                        'aux_quantity' => 1,
                        'edit_sale_unit_price' => $price['sale_unit_price'],
                        'aux_sale_unit_price' => $price['sale_unit_price'],
                        'image_url' => ($row->image !== 'imagen-no-disponible.jpg') ? asset('storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'items' . DIRECTORY_SEPARATOR . $row->image) : asset("/logo/{$row->image}"),
                        'warehouses' => collect($row->warehouses)->transform(function ($row) {
                            return [
                                'warehouse_description' => $row->warehouse->description,
                                'stock' => $row->stock,
                            ];
                        }),
                        'category_id' => ($row->category) ? $row->category->id : null,
                        'sets' => collect($row->sets)->transform(function ($r) {
                            return [
                                $r->individual_item->description
                            ];
                        }),
                        'unit_type' => $row->item_unit_types,
                        'category' => ($row->category) ? $row->category->name : null,
                        'brand' => ($row->brand) ? $row->brand->name : null,

                        'exchange_points' => $row->exchange_points,
                        'quantity_of_points' => $row->quantity_of_points,
                        'exchanged_for_points' => false, //para determinar si desea canjear el producto
                        'used_points_for_exchange' => null, //total de puntos
                        'original_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                        'restrict_sale_cpe' => $row->restrict_sale_cpe,
                    ];
                });
            return $items;
        }

        return [];
    }

    public function payment()
    {
        return view('tenant.pos.payment');
    }

    public function status_configuration()
    {

        $configuration = Configuration::first();

        return $configuration;
    }

    public function validate_stock($item_id, $quantity, Request $request)
    {
        $quantity=D::of(D::input($quantity,'quantity'));
        if ($request->has('quantity_exact')) {
            try {
                $fraction=json_decode($request->query('quantity_exact'),true,8,JSON_THROW_ON_ERROR);
                foreach (['n','d'] as $part) {
                    if (!is_string($fraction[$part] ?? null) || !preg_match('/^[0-9]{1,128}$/D',$fraction[$part])) throw new \InvalidArgumentException();
                }
                $exact=D::of($fraction);
                if (!$quantity->isEqualTo(D::of(D::represent($exact)))) throw new \InvalidArgumentException();
                $quantity=$exact;
            } catch (\Throwable $exception) {
                throw \Illuminate\Validation\ValidationException::withMessages(['quantity'=>'La cantidad no coincide con su fracción exacta.']);
            }
        }
        if ($quantity->isNegative()) throw \Illuminate\Validation\ValidationException::withMessages(['quantity'=>'La cantidad debe ser positiva.']);

        $inventory_configuration = InventoryConfiguration::firstOrFail();
        $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();
        $item_warehouse = ItemWarehouse::where([['item_id', $item_id], ['warehouse_id', $warehouse->id]])->first();
        $item = Item::findOrFail($item_id);

        if ($item->is_set) {
            $sets = $item->sets;

            foreach ($sets as $set) {
                $individual_item = $set->individual_item;
                $individual_quantity = D::readRequired($set,'quantity');
                $total_item_quantity = $individual_quantity->multipliedBy($quantity);
                $item_warehouse = ItemWarehouse::where([
                        ['item_id', $individual_item->id],
                        ['warehouse_id', $warehouse->id]]
                )->first();
                if (!$item_warehouse)
                    return [
                        'success' => false,
                        'message' => "El producto seleccionado no está disponible en su almacén!"
                    ];

                $stock = D::readRequired($item_warehouse,'stock')->minus($total_item_quantity);


                if ($item_warehouse->item->unit_type_id !== 'SERV') {
                    if (($inventory_configuration->stock_control) && $stock->isNegative()) {
                        return [
                            'success' => false,
                            'message' => "El producto {$item_warehouse->item->description} registrado en el conjunto {$item->description} no tiene suficiente stock!"
                        ];
                    }
                }
                // dd($individual_item);
            }


        } else {

            if ($item->unit_type_id == 'SERV') {
                return [
                    'success' => true,
                    'message' => ''
                ];
            }

            if (!$item_warehouse && $item->unit_type_id !== 'SERV')
                return [
                    'success' => false,
                    'message' => "El producto seleccionado no está disponible en su almacén!"
                ];

            $stock = D::readRequired($item_warehouse,'stock')->minus($quantity);


            if ($item_warehouse->item->unit_type_id !== 'SERV') {
                if (($inventory_configuration->stock_control) && $stock->isNegative()) {
                    return [
                        'success' => false,
                        'message' => "El producto {$item_warehouse->item->description} no tiene suficiente stock!"
                    ];
                }
            }

        }

        return [
            'success' => true,
            'message' => ''
        ];

    }

    /**
     * Lista inicial de items en POS
     *
     * @param Request $request
     *
     * @return PosCollection
     */
    public function item(Request $request)
    {
        $items = $this->catalogItems()->orderBy('description');

        if ($request->garage == 1) {
            $items->where('calculate_quantity', 1);
        }

        self::applyVariationsGrouping($items, $request);

        self::FilterItem($items, $request);

        $items_collection = $items->paginate(50);

        return new PosCollection($items_collection);

    }

    /**
     * Agrupa variaciones bajo su producto principal en la grilla del POS.
     * Solo actúa con group_variations=1 (lo envían los modos normal y fast);
     * garage y otros consumidores mantienen el listado plano.
     *
     * @param Item $items
     * @param Request $request
     */
    public static function applyVariationsGrouping(&$items, Request $request)
    {
        if ($request->group_variations != 1) {
            return;
        }

        $input = trim((string) $request->input_item);

        $items->where(function ($query) use ($input) {
            $query->whereNull('parent_item_id');
            // el código exacto de una variación la muestra directo (escáner / búsqueda por código)
            if ($input !== '') {
                $query->orWhere('internal_id', $input)
                    ->orWhere('barcode', $input);
            }
        })
            ->withCount(['variations' => fn (Builder $query) => $query->whereNotService()])
            ->withSum(['variations as variations_stock' => fn (Builder $query) => $query->whereNotService()], 'stock')
            ->with(['variations' => function ($query) {
                $query->whereNotService()
                    ->whereIsActive()
                    ->with(['variationValues.value', 'variationValues.variable'])
                    ->orderBy('id');
            }]);
    }

    /**
     * Fila completa de un item individual con la misma forma que la grilla,
     * usada al seleccionar una variación desde el modal.
     *
     * @param int $id
     *
     * @return PosCollection
     */
    public function singleItem($id)
    {
        return new PosCollection(Item::whereNotService()->where('id', $id)->paginate(1));
    }

    /**
     * Unificacion de los filtros de busqueda de items en POS
     * Se evalua categoria como $request->cat
     * se evalua description, internal_id del item como $request->input_item
     * se evalua name de brand y category como $request->input_item
     *
     * @param Item $item
     * @param Request $request
     */
    public static function FilterItem(&$item, Request $request)
    {
        if (!empty($request->cat)) {
            $item->where('category_id', $request->cat);
        }

        if (!empty($request->input_item)) {
            $input = $request->input_item;
            $keywords = explode(' ', $input);

            $item->where(function ($query) use ($keywords, $input) {
                foreach ($keywords as $word) {
                    $query->where('description', 'like', "%{$word}%");
                }
                $query->orWhere('barcode', '=', $input);
                $query->orWhere('internal_id', 'like', "%{$input}%");
                $query->orWhereHas('brand', function ($subQuery) use ($input) {
                    $subQuery->where('name', 'like', "%{$input}%");
                })->orWhereHas('category', function ($subQuery) use ($input) {
                    $subQuery->where('name', 'like', "%{$input}%");
                });
            });
        }

        $item->whereIsActive();
    }

    /**
     * Se busca items al escribir en input_item desde POS
     *
     * @param Request $request
     *
     * @return PosCollection
     */
    public function search_items_cat(Request $request)
    {
        $item = $this->catalogItems();
        self::applyVariationsGrouping($item, $request);

        self::FilterItem($item, $request);
        return new PosCollection($item->paginate(50));

    }

    private function catalogItems(): Builder
    {
        return Item::query()
            ->where(fn (Builder $query) => $query->whereWarehouse())
            ->whereNotService()
            ->whereIsActive();
    }

    /**
     * vista de venta rapida para POS
     *
     * @param
     *
     * @return view
     */
    public function fast()
    {
        $cash = Cash::where([['user_id', auth()->user()->id], ['state', true]])->first();

        if (!$cash) return redirect()->route('tenant.cash.index', ['redirect_reason' => 'no_cash_fast_sale']);

        $configuration = Configuration::first();

        $company = Company::select('fiscal_environment')->first();
        $company_environment = $company->fiscal_environment;
        $business_turns = BusinessTurn::select('active')->where('id', 4)->first();

        return view('tenant.pos.fast', compact('configuration', 'company_environment', 'business_turns'));
    }

    public function garage()
    {
        $cash = Cash::where([['user_id', auth()->user()->id], ['state', true]])->first();

        if (!$cash) return redirect()->route('tenant.cash.index', ['redirect_reason' => 'no_cash_garage']);

        $configuration = Configuration::first();

        $company = Company::select('fiscal_environment')->first();
        $company_environment = $company->fiscal_environment;
        $business_turns = BusinessTurn::select('active')->where('id', 4)->first();

        return view('tenant.pos.garage', compact('configuration', 'company_environment', 'business_turns'));
    }
}
