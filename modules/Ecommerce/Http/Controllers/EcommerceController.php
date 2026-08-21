<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Tenant\EmailController;
use App\Models\Tenant\Configuration;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Http\Resources\Tenant\ItemCollection;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\User;
use App\Models\Tenant\Person;
use Illuminate\Support\Str;
use App\Models\Tenant\Order;
use App\Models\Tenant\ItemsRating;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\StatusOrder;
use Modules\Ecommerce\Http\Resources\ItemBarCollection;
use stdClass;
use Illuminate\Support\Facades\Mail;
use App\Mail\Tenant\CulqiEmail;
use App\Http\Controllers\Tenant\Api\ServiceController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Hyn\Tenancy\Contracts\CurrentHostname;
use Modules\Inventory\Models\InventoryConfiguration;
use App\Http\Resources\Tenant\OrderCollection;
use App\Models\Tenant\Promotion;
use Modules\ApiPeruDev\Data\ServiceData;
use App\Models\Tenant\Document;
use Modules\Item\Models\Category;
use Modules\Item\Models\Brand;
use App\Models\Tenant\Catalogs\Department;
use Modules\Ecommerce\Models\Tenant\DeliveryZone;
use Modules\Ecommerce\Models\Tenant\DeliveryZoneLocation;
use Modules\Ecommerce\Models\Tenant\DiscountCoupon;
use Modules\Ecommerce\Models\Tenant\DiscountCouponUsage;
use Modules\Ecommerce\Models\Tenant\PickupBranch;
use App\Models\Tenant\PersonAddress;
use Modules\Payment\Models\PaymentConfiguration;
use App\Models\System\Configuration as SystemConfiguration;
use Modules\Ecommerce\Jobs\SendOrderStatusEmail;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Services\Tenant\OrderDocumentFromStatusService;
use Modules\Ecommerce\Services\CampaignPriceService;


class EcommerceController extends Controller
{
    /**
     * Descripción general reutilizable para meta tags y otros lugares
     */
    public static function getEcommerceDescription($company = null)
    {
        // Buscar el nombre comercial en varios campos posibles
        $trade_name = data_get($company, 'trade_name')
            ?: 'Tu tienda online';
        return "Compra online en {$trade_name}. Encuentra una amplia variedad de productos de calidad, ofertas exclusivas y precios competitivos, con envíos rápidos y una experiencia de compra segura y confiable.";
    }
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function __construct(){
        // Compartir variable records (los padres de variaciones no se listan en tienda)
        view()->share('records', Item::where('apply_store', 1)->whereDoesntHave('variations')->orderBy('id', 'DESC')->take(2)->get());

        // Compartir descripción ecommerce globalmente usando el modelo Company
        $companyModel = \App\Models\Tenant\Company::first();
        $ecommerceDescription = self::getEcommerceDescription($companyModel);
        view()->share('ecommerceDescription', $ecommerceDescription);

        // Visibilidad de precios en listados / ficha / header (ambos modos de cotización)
        view()->share('storefront_show_prices', ConfigurationEcommerce::storefrontShowsPrices());
    }

    // public function index()
    // {
    //   $dataPaginate = Item::where([['apply_store', 1], ['internal_id','!=', null]])->paginate(15);
    //   $configuration = InventoryConfiguration::first();
    //   return view('ecommerce::index', ['dataPaginate' => $dataPaginate, 'configuration' => $configuration->stock_control]);
    // }
    public function index($name = null, $brand = null)
    {
        if ($name) {
            $name = str_replace('-', ' ', $name);
        }

        $category = Category::where('name', $name)->first();

        // Obtener preferencias de configuración
        $configEcommerce = ConfigurationEcommerce::first();
        $preferences = $configEcommerce && $configEcommerce->preferences
            ? (is_string($configEcommerce->preferences) ? json_decode($configEcommerce->preferences, true) : $configEcommerce->preferences)
            : ['show_description' => 1, 'show_stock' => 0, 'only_available_products' => 0];

        // Obtener el modelo Company para meta tags y descripción
        $company = \App\Models\Tenant\Company::first();

        $order = request()->get('order');

        // Query base (los padres de variaciones se ocultan; sus variaciones son cards normales)
        $query = Item::with('brand')->where([['apply_store', 1], ['internal_id', '!=', null]])
            ->whereDoesntHave('variations');

        if ($brand) {
            $query->where('brand_id', $brand->id);
        }

        // Filtrar solo productos disponibles si está activado
        if (isset($preferences['only_available_products']) && $preferences['only_available_products'] == 1) {
            $query->where('stock', '>', 0);
        }

        // Ordenamiento de productos (issue #151)
        switch ($order) {
            case 'name_asc':
                $query->orderBy('description', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('description', 'desc');
                break;
            case 'price_asc':
                $query->orderBy('sale_unit_price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('sale_unit_price', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'DESC');
                break;
        }

        // Cantidad de productos por página (configurable, por defecto 16)
        $perPage = isset($preferences['products_per_page']) && in_array((int) $preferences['products_per_page'], [8, 12, 16, 24, 32, 40])
            ? (int) $preferences['products_per_page']
            : 16;

        $dataPaginate = $query->category($category ? $category->id : null)
            ->paginate($perPage)
            ->withQueryString();

        $configuration = InventoryConfiguration::first();
        // Mostrar también las categorías recién creadas aunque todavía no tengan
        // productos asociados. Antes `has('items')` hacía que desaparecieran de
        // la navegación y de la sección "Nuestras Categorías".
        $categories_filtered = Category::orderBy('name')->get();

        // Obtener los anuncios publicitarios (spots) activos
        $spots = Promotion::where('apply_restaurant', 0)
            ->where('type', 'spots')
            ->where('status', 1)
            ->orderBy('id', 'ASC')
            ->limit(4)
            ->get();

        // Obtener la descripción general para meta tags
        $ecommerceDescription = self::getEcommerceDescription($company);

        $customLinks = \App\Models\Tenant\ConfigurationEcommerce::getCustomLinks();

        return view('ecommerce::index', [
            'dataPaginate' => $dataPaginate,
            'configuration' => $configuration->stock_control,
            'spots' => $spots,
            'preferences' => $preferences,
            'ecommerceDescription' => $ecommerceDescription,
            'company' => $company,
            'customLinks' => $customLinks,
            'category' => $category,
            'brand' => $brand,
            'categories' => $categories_filtered,
            'categories_list' => $categories_filtered
        ]);
    }

    public function brand($id, $slug = null)
    {
        $brand = Brand::findOrFail((int) $id);
        $canonicalSlug = Str::slug($brand->name);

        if ($canonicalSlug !== '' && $slug !== $canonicalSlug) {
            return redirect()->route('tenant.ecommerce.brand', [
                'id' => $brand->id,
                'slug' => $canonicalSlug,
            ], 301);
        }

        return $this->index(null, $brand);
    }

    /**
     * Genera el sitemap XML del ecommerce para mejorar el SEO.
     * Incluye: home, páginas informativas, categorías con productos y
     * todos los productos publicados (apply_store = 1).
     */
    public function sitemap()
    {
        $urls = [];

        $urls[] = [
            'loc'      => route('tenant.ecommerce.index'),
            'lastmod'  => null,
            'priority' => '1.0',
        ];

        $staticRoutes = [
            'tenant_ecommerce_about_us',
            'tenant_ecommerce_terms_conditions',
            'tenant_ecommerce_privacy_policy',
        ];
        foreach ($staticRoutes as $name) {
            $urls[] = [
                'loc'      => route($name),
                'lastmod'  => null,
                'priority' => '0.5',
            ];
        }

        $categories = Category::has('items')->get();
        foreach ($categories as $category) {
            $slug = Str::slug($category->name, '-');
            if ($slug === '') {
                continue;
            }
            $urls[] = [
                'loc'      => route('tenant.ecommerce.category', $slug),
                'lastmod'  => optional($category->updated_at)->format('Y-m-d'),
                'priority' => '0.6',
            ];
        }

        $items = Item::where([['apply_store', 1], ['internal_id', '!=', null]])
            ->whereDoesntHave('variations')
            ->select('id', 'description', 'updated_at')
            ->orderBy('id', 'DESC')
            ->get();
        foreach ($items as $item) {
            $slug = Str::slug($item->description);
            $urls[] = [
                'loc'      => route('tenant.ecommerce.item', ['id' => $item->id, 'slug' => $slug]),
                'lastmod'  => optional($item->updated_at)->format('Y-m-d'),
                'priority' => '0.8',
            ];
        }

        return response()
            ->view('ecommerce::sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    // public function category(Request $request)
    // {
    //   $dataPaginate = Item::select('i.*')
    //     ->where([['i.apply_store', 1], ['i.internal_id','!=', null], ['it.tag_id', $request->category]])
    //     ->from('items as i')
    //     ->join('item_tags as it', 'it.item_id','i.id')->paginate(15);
    //     $configuration = InventoryConfiguration::first();
    //   return view('ecommerce::index', ['dataPaginate' => $dataPaginate, 'configuration' => $configuration->stock_control]);
    // }

    public function getDescriptionWithPromotion($item, $promotion_id)
    {
        $promotion = Promotion::findOrFail($promotion_id);

        return "{$item->description} - {$promotion->name}";
    }

    public function item(Request $request, $id, $slug = null)
    {
        $id = (int) $id;
        $row = Item::with(['brand', 'category', 'items_sets'])->find($id);

        if (!$row) {
            abort(404);
        }

        // Un padre con variaciones no es vendible: redirige a una variación publicada
        if ($row->variations()->exists()) {
            $target = $row->variations()
                ->where('active', 1)
                ->where('apply_store', 1)
                ->orderByDesc('stock')
                ->first();

            if (!$target) {
                abort(404);
            }

            return redirect()->route('tenant.ecommerce.item', [
                'id' => $target->id,
                'slug' => Str::slug($target->description),
            ]);
        }

        $promotion_id = $request->query('promotion');

        $canonical_slug = Str::slug($row->description);

        if ($canonical_slug !== '' && $slug !== $canonical_slug) {
            $params = ['id' => $id, 'slug' => $canonical_slug];
            if ($promotion_id) {
                $params['promotion'] = $promotion_id;
            }
            return redirect()->route('tenant.ecommerce.item', $params, 301);
        }

        $exchange_rate_sale = $this->getExchangeRateSale();
        $sale_unit_price = ($row->has_igv) ? $row->sale_unit_price : $row->sale_unit_price*1.18;

        $description = $promotion_id ? $this->getDescriptionWithPromotion($row, $promotion_id) : $row->description;

        $record = (object)[
            'id' => $row->id,
            'internal_id' => $row->internal_id,
            'unit_type_id' => $row->unit_type_id,
            'description' => $description,
            'category' => $row->category,
            'brand' => $row->brand,
            'stock' => $row->getStockByWarehouseMain(),
            // 'description' => $row->description,
            'technical_specifications' => $row->technical_specifications,
            'name' => $row->name,
            'second_name' => $row->second_name,
            'sale_unit_price' => ($row->currency_type_id === 'PEN') ? $sale_unit_price : ($sale_unit_price * $exchange_rate_sale),
            'currency_type' => $row->currency_type,
            'has_igv' => (bool) $row->has_igv,
            'sale_unit' => $row->sale_unit_price,
            'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
            'currency_type_symbol' => $row->currency_type->symbol,
            'image' =>  $row->image,
            'image_medium' => $row->image_medium,
            'image_small' => $row->image_small,
            'tags' => $row->tags->pluck('tag_id')->toArray(),
            'images' => $row->images,
            'attributes' => $row->attributes ? $row->attributes : [],
            'promotion_id' => $promotion_id,
            'variation_selector' => $this->getVariationSelector($row),
            'components' => $row->items_sets->map(function ($component) {
                return (object) [
                    'id' => $component->id,
                    'name' => $component->description,
                    'description' => $component->name,
                    'quantity' => (float) $component->pivot->quantity,
                    'unit_type_id' => $component->unit_type_id,
                    'image' => $component->image,
                    'image_medium' => $component->image_medium,
                    'image_small' => $component->image_small,
                ];
            })->values(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $record]);
        }

        // El servicio requiere el modelo Eloquent, no el DTO usado por la vista.
        $campaignPricing = app(CampaignPriceService::class)->forItem($row);
        $categories = \Modules\Item\Models\Category::has('items')->get();
        return view('ecommerce::items.record', compact('record', 'categories'));
    }

    /**
     * Estructura para el selector de variantes del detalle: variables con sus valores
     * y el mapa combinación → variación hermana (elegir otra combinación navega a su URL).
     */
    private function getVariationSelector($row)
    {
        if (!$row->parent_item_id) {
            return null;
        }

        $siblings = Item::where('parent_item_id', $row->parent_item_id)
            ->where('active', 1)
            ->where('apply_store', 1)
            ->with('variationValues.value.variable')
            ->orderBy('id')
            ->get();

        if ($siblings->count() < 2) {
            return null;
        }

        $variables = [];
        $combinations = [];

        foreach ($siblings as $sibling) {
            $value_ids = [];

            foreach ($sibling->variationValues as $variation_value) {
                $value = $variation_value->value;
                $variable = $value ? $value->variable : null;
                if (!$value || !$variable) {
                    continue;
                }

                if (!isset($variables[$variable->id])) {
                    $variables[$variable->id] = [
                        'id' => $variable->id,
                        'name' => $variable->name,
                        'value_type' => $variable->value_type,
                        'values' => [],
                    ];
                }
                $variables[$variable->id]['values'][$value->id] = [
                    'id' => $value->id,
                    'value' => $value->value,
                    'color' => $value->color,
                ];

                $value_ids[] = (int) $value->id;
            }

            sort($value_ids);
            $combinations[] = [
                'variation_id' => $sibling->id,
                'value_ids' => array_values($value_ids),
                'stock' => $sibling->getStockByWarehouseMain(),
                'url' => route('tenant.ecommerce.item', ['id' => $sibling->id, 'slug' => Str::slug($sibling->description)]),
            ];
        }

        $current = $siblings->firstWhere('id', $row->id);
        $current_value_ids = $current
            ? $current->variationValues->pluck('product_variable_value_id')->map(function ($value_id) {
                return (int) $value_id;
            })->sort()->values()->all()
            : [];

        return [
            'variables' => array_map(function ($variable) {
                $variable['values'] = array_values($variable['values']);
                return $variable;
            }, array_values($variables)),
            'combinations' => $combinations,
            'current_value_ids' => $current_value_ids,
        ];
    }

    public function items()
    {
        $records = Item::with('brand')->where('apply_store', 1)->whereDoesntHave('variations')->get();
        return view('ecommerce::items.index', compact('records'));
    }

    public function itemsBar()
    {
        $records = Item::with('brand')->where('apply_store', 1)
            ->when(request('brand_id'), function ($query, $brandId) {
                $query->where('brand_id', (int) $brandId);
            })
            ->whereDoesntHave('variations')->get();
        // return new ItemCollection($records);
        return new ItemBarCollection($records);

    }

    public function partialItem($id)
    {
        $record = Item::find($id);
        return view('ecommerce::items.partial', compact('record'));
    }

    public function detailCart()
    {
        $configuration = ConfigurationEcommerce::first();
        $categories = \Modules\Item\Models\Category::has('items')->get();

        // Obtener el tipo de descuento global configurado (02 afecta la base, 03 no afecta)
        $systemConfig = Configuration::with('globalDiscountType')->first();
        $global_discount_type = $systemConfig->globalDiscountType;

        // Dirección de envío del cliente autenticado (person_addresses + contact.shipping)
        $userAddress = null;
        $userAddresses = [];
        if ($ecommerceUser = auth('ecommerce')->user()) {
            $ecommerceUser = $ecommerceUser->fresh();
            $userAddress = $this->buildUserShippingAddressPayload($ecommerceUser);
            $userAddresses = $this->getUserShippingAddresses($ecommerceUser);
        }

        $enable_electronic_documents = (bool) ($configuration->enable_electronic_documents ?? false);
        $enable_store_pickup          = (bool) ($configuration->enable_store_pickup ?? false);
        $quotation_settings           = ConfigurationEcommerce::storefrontQuotationConfig();
        $quotation_enabled            = $quotation_settings['enabled'];
        $quotation_mode               = $quotation_settings['mode'];
        $quotation_show_prices        = $quotation_settings['show_prices'];
        $quotation_success_message    = $quotation_settings['success_message'];
        $quotation_validity_days      = $quotation_settings['validity_days'];
        $quotation_terms              = $quotation_settings['terms'];
        $enable_yape                  = (bool) ($configuration->enable_yape ?? false);
        $enable_transfer              = (bool) ($configuration->enable_transfer ?? false);

        // Sucursales de recojo activas para el checkout
        $pickup_branches = $enable_store_pickup
            ? PickupBranch::active()->orderBy('name')->get(['id', 'name', 'address'])->toArray()
            : [];

        $payment_configuration = PaymentConfiguration::first();
        $gateway_availability = PaymentConfiguration::getEcommerceGatewayAvailability();
        $enable_yape = $enable_yape && $gateway_availability['yape'];
        $preferences = $configuration->preferences
            ? (is_string($configuration->preferences) ? json_decode($configuration->preferences, true) : $configuration->preferences)
            : [];

        // Validación estricta: Si el switch de Ecommerce está apagado, forzamos false en las credenciales
        // en memoria para asegurarnos que la vista no intente inyectar scripts de pasarelas no autorizadas.
        if (!($preferences['enable_izipay'] ?? false)) {
            $payment_configuration->enabled_izipay = false;
        }
        if (!($preferences['enable_mp'] ?? false)) {
            $payment_configuration->enabled_mp = false;
        }
        if (!($preferences['enable_culqi'] ?? false)) {
            $payment_configuration->enabled_culqi = false;
        }

        $ecommerce_bank_account_ids = array_values(array_filter(array_map(
            'intval',
            (array) ($preferences['ecommerce_bank_account_ids'] ?? [])
        )));

        if (count($ecommerce_bank_account_ids) > 0) {
            $bank_accounts = \App\Models\Tenant\BankAccount::with('bank', 'currency_type')
                ->whereIn('id', $ecommerce_bank_account_ids)
                ->get();
        } else {
            $bank_accounts = collect();
        }

        return view('ecommerce::cart.detail', compact(
            'configuration',
            'categories',
            'global_discount_type',
            'userAddress',
            'userAddresses',
            'enable_electronic_documents',
            'enable_store_pickup',
            'quotation_enabled',
            'quotation_mode',
            'quotation_show_prices',
            'quotation_success_message',
            'quotation_validity_days',
            'quotation_terms',
            'pickup_branches',
            'enable_yape',
            'enable_transfer',
            'payment_configuration',
            'bank_accounts',
            'preferences',
            'gateway_availability'
        ));
    }

    public function orderList()
    {
        if (auth('ecommerce')->user()) {
            $configuration = ConfigurationEcommerce::first();
            $categories = \Modules\Item\Models\Category::has('items')->get();
            return view('ecommerce::document_list.order', compact('configuration', 'categories'));
        } else {
            return redirect('ecommerce');
        }
    }

    public function documentList()
    {
        if (auth('ecommerce')->user()) {
            $categories = \Modules\Item\Models\Category::has('items')->get();
            return view('ecommerce::document_list.document', compact('categories'));
        } else {
            return redirect('ecommerce');
        }
    }

    public function orders(Request $request)
    {
        if (auth('ecommerce')->user()) {
            $user = auth('ecommerce')->user();

            // Inicializar la consulta base
            $records = Order::where(function($query) use ($user) {
                $query->where('customer', 'LIKE', '%' . $user->email . '%')
                      ->orWhereJsonContains('customer->correo_electronico', $user->email);
            });

            // Aplicar filtro de estado si se proporciona
            if ($request->state_order_id) {
                $state_allowed = $request->state_order_id && $request->state_order_id != 'all' ? [$request->state_order_id] : [1, 2, 3, 4];
                $records = $records->whereIn('status_order_id', $state_allowed);
            }

            // Aplicar filtro de fecha si se proporciona
            if ($request->date_of_endd || $request->date_of_start) {
                $date_of_start = $request->date_of_start ?? date('Y-m-d');
                $date_of_end = $request->date_of_endd ?? date('Y-m-d');
                $records = $records->whereBetween('created_at', [$date_of_start, $date_of_end]);
            }

            // Obtener los resultados paginados (más recientes primero)
            $records = $records->orderByDesc('id')->paginate(config('tenant.items_per_page', 10));

            // Transformar los datos manteniendo la estructura de paginación
            $records->getCollection()->transform(function ($row) {
                return $row->getCollectionData();
            });

            return $records;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }
    }

    public function documents(Request $request)
    {
        if (auth('ecommerce')->user()) {
            $user = auth('ecommerce')->user();


            // Buscar órdenes del usuario
            $orders = Order::where(function($query) use ($user) {
                        $query->where('customer', 'LIKE', '%' . $user->email . '%')
                              ->orWhereJsonContains('customer->correo_electronico', $user->email);
                    })->get();

            $arrays_external_id = $orders->pluck('document_external_id')->filter()->toArray();

            if (empty($arrays_external_id)) {
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => config('tenant.items_per_page', 10),
                    'total' => 0
                ]);
            }

            $documents = Document::where(function($q) use($arrays_external_id, $user, $request) {
                            $q->whereIn('external_id', $arrays_external_id)
                                ->orWhere('customer->email', $user->email);
                        });

            if ($request->date_of_endd || $request->date_of_start) {
                $date_of_start = $request->date_of_start ?? date('Y-m-d');
                $date_of_end = $request->date_of_endd ?? date('Y-m-d');
                $documents = $documents->whereBetween('date_of_issue', [$date_of_start, $date_of_end]);
            }

            if ($request->state_type_id ) {
                $state_allowed = $request->state_type_id && $request->state_type_id != 'all'  ? [$request->state_type_id] : ['05', '09', '11'];
                $documents = $documents->whereIn('state_type_id', $state_allowed);
            }

            $documents = $documents->orderBy('date_of_issue', 'desc')
                        ->paginate(config('tenant.items_per_page'));

            // Transformar los datos manteniendo la estructura de paginación
            $documents->getCollection()->transform(function ($dc) {
                return [
                    'number' => $dc->getNumberFullAttribute(),
                    'description' => $dc->document_type->description,
                    'date_of_issue' => $dc->date_of_issue->format('Y-m-d'),
                    'customer' => [
                        'name' => $dc->customer->name,
                        'number' => $dc->customer->number,
                        'identity_document_type_id' => $dc->customer->identity_document_type_id,
                    ],
                    'status' => $dc->state_type->description,
                    'state_type_id' => $dc->state_type_id,
                    'download_pdf' => $dc->download_external_pdf,
                    'download_xml' =>  $dc->download_external_xml,
                    'total' => $dc->total,
                ];
            });

            return $documents;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No autentificado'
            ], 401);
        }
    }

    public function pay()
    {
        return view('ecommerce::cart.pay');
    }

    public function showLogin(Request $request)
    {
        // Auth real = modal #login_register_modal. Evitar vista inexistente ecommerce::user.login (500).
        $path = '/ecommerce';
        $referer = $request->headers->get('referer');

        if (is_string($referer) && $referer !== '') {
            $parts = parse_url($referer);
            $refPath = isset($parts['path']) ? $parts['path'] : '';
            if (
                is_string($refPath)
                && strpos($refPath, '/ecommerce') !== false
                && strpos($refPath, '/ecommerce/login') === false
            ) {
                $path = $refPath;
            }
        }

        $separator = strpos($path, '?') !== false ? '&' : '?';

        return redirect($path . $separator . 'open_login=1');
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::guard('ecommerce')->attempt($credentials)) {
           return[
               'success' => true,
               'message' => 'Login Success'
           ];
        }
        else{
            return[
                'success' => false,
                'message' => 'Usuario o Password incorrectos'
            ];
        }

    }

    public function logout()
    {
        Auth::guard('ecommerce')->logout();

        $referer = request()->headers->get('referer');

        if ($referer && (str_contains($referer, '/pedidos') || str_contains($referer, 'pedidos/'))) {
            return redirect('/pedidos');
        }

        // Detectar si viene de restaurant y redirigir apropiadamente
        if ($referer && str_contains($referer, '/restaurant')) {
            return redirect('restaurant/list/items');
        }

        return redirect('ecommerce');
    }

    public function storeUser(Request $request)
    {
        try{

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'ruc' => 'required|string|min:8|max:11',
                'name' => 'nullable|string|max:255',
                'pswd' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => $validator->errors()->first()
                ];
            }

            $verify = Person::where('email', $request->email)
                        ->orWhere('number', $request->ruc)
                        ->first();
            if($verify)
            {
                return [
                    'success' => false,
                    'message' => 'Email o RUC/DNI no disponible'
                ];
            }

            $type = (strlen($request->ruc)==8) ? 'dni' : 'ruc';
            $name = $request->name;
            $identity_document_type_id = (strlen($request->ruc)==8) ? 1 : 6;
            $address = null;
            $department_id = null;
            $province_id = null;
            $district_id = null;

            $dataDocument = $this->searchDocument($type,$request->ruc);


            if($dataDocument["success"]){
                $name = $dataDocument["data"]["name"];
                if($type==='ruc'){
                    $address = $dataDocument['data']['address'];
                    $departmentId = $dataDocument['data']['location_id'][0] ?? null;
                    $provinceId = $dataDocument['data']['location_id'][1] ?? null;
                    $districtId = $dataDocument['data']['location_id'][2] ?? null;
                }
            }

            if(!($dataDocument["success"]) && $type==='dni'){
                $identity_document_type_id = 0;
            }

            $person = new Person();
            $person->type = 'customers';
            $person->identity_document_type_id = $identity_document_type_id;
            $person->number = $request->ruc;
            $person->name = $name;
            $person->country_id = 'PE';
            $person->nationality_id = 'PE';
            $person->department_id = $department_id;
            $person->province_id = $province_id;
            $person->district_id = $district_id;
            $person->address = $address;
            $person->establishment_code = '0000';
            $person->email = $request->email;
            $person->password = bcrypt($request->pswd);

            $person->save();

            $credentials = [ 'email' => $person->email, 'password' => $request->pswd ];
            Auth::guard('ecommerce')->attempt($credentials);
            return [
                'success' => true,
                'message' => 'Usuario registrado'
            ];

        }catch(Exception $e)
        {
            return [
                'success' => false,
                'message' =>  $e->getMessage()
            ];
        }

    }

    public function transactionFinally(Request $request)
    {
        try{
            //1. confirmar dato de comprobante en order
            $order_generated = Order::find($request->orderId);
            $order_generated->document_external_id = $request->document_external_id;
            $order_generated->number_document = $request->number_document;
            $order_generated->save();

            return [
                'success' => true,
                'message' => 'Order Actualizada',
                'order_total' => $order_generated->total
            ];
        }
        catch(Exception $e)
        {
            return [
                'success' => false,
                'message' =>  $e->getMessage()
            ];
        }

    }

    /**
     * Valida uno o varios códigos de cupón y retorna el mejor aplicable
     */
    public function validateCoupon(Request $request)
    {
        $codes = [];
        if ($request->codes && is_array($request->codes)) {
            $codes = $request->codes;
        } elseif ($request->code) {
            $codes = [$request->code];
        }

        if (empty($codes)) {
            return response()->json(['success' => false, 'message' => 'cupon no existe'], 404);
        }

        $order_total = (float) ($request->order_total ?? 0);
        $user = auth('ecommerce')->user();
        $person_id = $user?->id;

        $found = false;
        $validCoupons = [];

        foreach ($codes as $code) {
            $coupon = DiscountCoupon::where('code', $code)->first();
            if (!$coupon) continue;
            $found = true;

            if (!$coupon->active) continue;
            if ($coupon->is_expired) continue;

            if (!$coupon->canBeUsedBy($person_id, $order_total)) continue;

            $discount = $coupon->calculateDiscountAmount($order_total);
            $validCoupons[] = [
                'coupon' => $coupon,
                'discount' => $discount
            ];
        }

        if (empty($validCoupons)) {
            if (!$found) {
                return response()->json(['success' => false, 'message' => 'cupon no existe'], 404);
            }
            // Al menos uno existía pero ninguno es válido
            return response()->json(['success' => false, 'message' => 'cupon no valido'], 422);
        }

        // Escoger el de mayor descuento
        usort($validCoupons, function ($a, $b) {
            return $b['discount'] <=> $a['discount'];
        });

        $best = $validCoupons[0];
        $coupon = $best['coupon'];
        $discount = $best['discount'];
        $new_total = max(0, round($order_total - $discount, 2));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount' => $discount,
                'new_total' => $new_total,
                'free_shipping' => (bool) $coupon->free_shipping
            ]
        ]);
    }

    /**
     * Aplica el cupón a una orden existente y registra el uso
     */
    public function applyCoupon(Request $request)
    {
        $order = Order::find($request->order_id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Orden no encontrada'], 404);
        }

        $user = auth('ecommerce')->user();
        $person_id = $user?->id;

        $coupon = null;
        if ($request->discount_coupon_id) {
            $coupon = DiscountCoupon::find($request->discount_coupon_id);
        } elseif ($request->discount_coupon_code) {
            $coupon = DiscountCoupon::where('code', $request->discount_coupon_code)->first();
        }

        if (!$coupon) {
            return response()->json(['success' => false, 'message' => 'cupon no existe'], 404);
        }

        if (!$coupon->canBeUsedBy($person_id, $order->total)) {
            return response()->json(['success' => false, 'message' => 'cupon no valido'], 422);
        }

        $discount = $coupon->calculateDiscountAmount($order->total);

        // Actualizar la orden (un solo cupón por venta)
        $order->total_discount = $discount;
        $order->discount_coupon_code = $coupon->code;
        $order->discount_coupon_id = $coupon->id;
        $order->total = round(max(0, $order->total - $discount), 2);
        $order->save();

        // Registrar uso
        DiscountCouponUsage::create([
            'discount_coupon_id' => $coupon->id,
            'person_id' => $person_id,
            'order_id' => $order->id
        ]);

        return response()->json(['success' => true, 'order' => $order]);
    }

    public function paymentCash(Request $request)
    {
        if ($blocked = $this->rejectPurchaseIfQuoteOnly()) {
            return $blocked;
        }

        if (
            $request->input('reference_payment') === 'yape'
            && ! PaymentConfiguration::isYapeConfigured()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Yape está deshabilitado o incompleto en la configuración global de pagos.',
            ], 422);
        }

        $customer = $this->extractPaymentCustomerFromRequest($request);
        $shippingAddress = (string) $request->input('shipping_address', '');
        $customer = $this->normalizePaymentCustomerData($customer, $shippingAddress);
        $request->merge(['customer' => $customer]);

        $validator = $this->makePaymentCustomerValidator($customer, $shippingAddress);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        } else {
            try {
                // Los padres de variaciones no son vendibles: rechaza carritos manipulados
                $item_ids = collect($request->items)->pluck('id')->filter()->all();
                if (count($item_ids) > 0 && Item::whereIn('id', $item_ids)->whereHas('variations')->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El carrito contiene un producto con variaciones: seleccione una variación específica.',
                    ], 422);
                }

                $type = ($request->purchase["datos_del_cliente_o_receptor"]["codigo_tipo_documento_identidad"]=='6')?'ruc':'dni';
                $document_number = $request->purchase["datos_del_cliente_o_receptor"]["numero_documento"];

                $dataDocument = $this->searchDocument($type,$document_number);
                if ($dataDocument["success"]) {
                    $clientData = [ "apellidos_y_nombres_o_razon_social" => $dataDocument["data"]["name"] ];
                    if ($type === 'ruc') {
                        $clientData["direccion"] = $dataDocument['data']['address'];
                        $clientData["ubigeo"] = $dataDocument['data']['location_id'][2] ?? null;
                    }
                    $request->merge([
                        'purchase' => array_merge($request->purchase, [
                            "datos_del_cliente_o_receptor" => array_merge(
                                $request->purchase["datos_del_cliente_o_receptor"],
                                $clientData
                            )
                        ])
                    ]);
                }

                $user = auth('ecommerce')->user();

                $initialOrderStatus = StatusOrder::where('is_order_status', true)->where('is_initial', true)->orderBy('sort_order')->first()
                    ?: StatusOrder::where('is_order_status', true)->orderBy('sort_order')->first();
                $initialStatusId = $initialOrderStatus ? $initialOrderStatus->id : null;

                $initialPaymentStatus = StatusOrder::where('is_payment_status', true)->where('is_initial', true)->orderBy('sort_order')->first()
                    ?: StatusOrder::where('is_payment_status', true)->orderBy('sort_order')->first();
                $initialPaymentStatusId = $initialPaymentStatus ? $initialPaymentStatus->id : null;

                $purchase = is_string($request->purchase)
                    ? (json_decode($request->purchase, true) ?: [])
                    : (array) $request->purchase;

                $isGuestOverride = $request->has('is_guest')
                    ? filter_var($request->input('is_guest'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    : (isset($purchase['checkout']['is_guest'])
                        ? (bool) $purchase['checkout']['is_guest']
                        : null);
                if ($isGuestOverride === true) {
                    $user = null;
                }
                $purchase = Order::attachEcommerceCheckoutMeta($purchase, $user, $isGuestOverride);

                $authoritativeItems = $this->applyAuthoritativeCampaignPrices((array) $request->items);
                $order = Order::create([
                    'external_id' => Str::uuid()->toString(),
                    'customer' =>  $request->customer,
                    'shipping_address' => $request->input('shipping_address', ''),
                    'items' => $authoritativeItems,
                    'total' => $request->precio_culqi,
                    'reference_payment' => $request->input('reference_payment', 'efectivo'),
                    'status_order_id' => $initialStatusId,
                    'payment_status_order_id' => $initialPaymentStatusId,
                    'shipping_status_order_id' => StatusOrder::resolveInitialShippingStatusId(),
                    'purchase' => $purchase,
                ]);

                // Si se envía cupón en la petición, aplicarlo inmediatamente
                if ($request->discount_coupon_code || $request->discount_coupon_id) {
                    $coupon = null;
                    if ($request->discount_coupon_id) {
                        $coupon = DiscountCoupon::find($request->discount_coupon_id);
                    } elseif ($request->discount_coupon_code) {
                        $coupon = DiscountCoupon::where('code', $request->discount_coupon_code)->first();
                    }

                    if ($coupon) {
                        // Si el frontend ya envió el monto de descuento (`total_discount`),
                        // usar ese valor en lugar de recalcularlo sobre el total ya descontado
                        // (evita aplicar el cupón dos veces).
                        if ($request->total_discount && $request->total_discount > 0) {
                            $discount = round((float) $request->total_discount, 2);
                            // reconstruir total original (antes del descuento) para validaciones
                            $original_total = round($order->total + $discount, 2);

                            if ($coupon->canBeUsedBy($user?->id, $original_total)) {
                                $order->total_discount = $discount;
                                $order->discount_coupon_code = $coupon->code;
                                $order->discount_coupon_id = $coupon->id;
                                // el total ya viene con descuento desde el frontend; mantenerlo
                                $order->total = round(max(0, $original_total - $discount), 2);
                                $order->save();

                                DiscountCouponUsage::create([
                                    'discount_coupon_id' => $coupon->id,
                                    'person_id' => $user?->id,
                                    'order_id' => $order->id
                                ]);
                            }
                        } else {
                            // Si frontend no envió el monto, calcularlo en servidor usando el total actual
                            if ($coupon->canBeUsedBy($user?->id, $order->total)) {
                                $discount = $coupon->calculateDiscountAmount($order->total);
                                $order->total_discount = $discount;
                                $order->discount_coupon_code = $coupon->code;
                                $order->discount_coupon_id = $coupon->id;
                                $order->total = round(max(0, $order->total - $discount), 2);
                                $order->save();

                                DiscountCouponUsage::create([
                                    'discount_coupon_id' => $coupon->id,
                                    'person_id' => $user?->id,
                                    'order_id' => $order->id
                                ]);
                            }
                        }
                    }
                }

                // Encolar notificación por correo si el estado inicial lo requiere
                if ($initialOrderStatus && ($initialOrderStatus->action_send_email ?? false)) {
                    try {
                        dispatch(new SendOrderStatusEmail($order->id, $initialOrderStatus->id, $this->buildOrderListUrl()));
                    } catch (\Throwable $e) {
                        \Log::error('Failed to dispatch SendOrderStatusEmail on order creation: '.$e->getMessage());
                    }
                }

                $contact = $this->resolveOrderCustomerContact($request, $user);

                $document = new stdClass;
                $document->client = $contact['name'];
                $document->product = $request->producto;
                $document->total = $request->precio_culqi;
                $document->items = $request->items;
                $document->id = $order->id;
                $document->order_number = $order->publicNumber();
                $document->tracking_url = route('tenant_ecommerce_order_tracking', [
                    'pedido' => $document->order_number,
                ]);

                if (!empty($contact['email'])) {
                    $this->paymentCashEmail($contact['email'], $document);
                }

                //Mail::to($customer_email)->send(new CulqiEmail($document));
                return [
                    'success' => true,
                    'order' => $order
                ];

            }catch(Exception $e)
            {
                return [
                    'success' => false,
                    'message' =>  $e->getMessage()
                ];
            }
        }
    }

    public function paymentMercadoPago(Request $request)
    {
        if ($blocked = $this->rejectPurchaseIfQuoteOnly()) {
            return $blocked;
        }
        if (! PaymentConfiguration::isMercadoPagoConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Mercado Pago está deshabilitado o incompleto en la configuración global de pagos.',
            ], 422);
        }

        $paymentConfig = PaymentConfiguration::first();
        $publicKeyIsTest = str_starts_with(trim((string) $paymentConfig->public_key_mp), 'TEST-');
        $accessTokenIsTest = str_starts_with(trim((string) $paymentConfig->access_token_mp), 'TEST-');

        if ($publicKeyIsTest !== $accessTokenIsTest) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales de Mercado Pago pertenecen a entornos distintos. La Public Key y el Access Token deben ser ambos de prueba o ambos de producción.',
            ], 422);
        }

        // TODO: quitar tras depurar el payload del Payment Brick
        Log::info('MercadoPago ecommerce request payload', $request->all());

        $customer = is_string($request->customer)
            ? json_decode($request->customer, true)
            : (array) $request->customer;

        $formData = $request->input('form_data') ?? $request->input('formData');
        if (is_string($formData)) {
            $formData = json_decode($formData, true);
        }
        if (is_array($formData) && isset($formData['formData'])) {
            $formData = $formData['formData'];
        }

        $paymentReq = new Request([
            'isTenant' => true,
            'form_data' => $formData,
        ]);

        $mpController = app(\Modules\Payment\Http\Controllers\PaymentGatewayController::class);
        $result = $mpController->mercadoPagoCreatePayment($paymentReq);

        if (!empty($result['paid']) || !empty($result['pending'])) {
            // Igual que Culqi: pago aprobado → action_mark_payment; pendiente → estado inicial de pago.
            $paymentStatusId = ! empty($result['paid'])
                ? StatusOrder::resolvePaidPaymentStatusId()
                : StatusOrder::resolveInitialPaymentStatusId();

            $purchase = is_string($request->purchase)
                ? (json_decode($request->purchase, true) ?: [])
                : (array) ($request->purchase ?? []);

            $ecommerceUser = auth('ecommerce')->user();
            $isGuestOverride = $request->has('is_guest')
                ? filter_var($request->input('is_guest'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : (isset($purchase['checkout']['is_guest'])
                    ? (bool) $purchase['checkout']['is_guest']
                    : null);
            if ($isGuestOverride === true) {
                $ecommerceUser = null;
            }
            $purchase = Order::attachEcommerceCheckoutMeta($purchase, $ecommerceUser, $isGuestOverride);

            $rawItems = is_string($request->items) ? json_decode($request->items, true) : $request->items;
            $order = Order::create([
                'external_id' => Str::uuid()->toString(),
                'customer' => $customer,
                'shipping_address' => $request->input('shipping_address', ''),
                'items' => $this->applyAuthoritativeCampaignPrices((array) $rawItems),
                'total' => $request->precio_culqi,
                'reference_payment' => $request->input('reference_payment', 'mp'),
                'status_order_id' => StatusOrder::resolveInitialOrderStatusId(),
                'payment_status_order_id' => $paymentStatusId,
                'shipping_status_order_id' => StatusOrder::resolveInitialShippingStatusId(),
                'purchase' => $purchase,
            ]);

            if (! empty($result['paid'])) {
                try {
                    $result['document'] = app(OrderDocumentFromStatusService::class)
                        ->afterGatewayPaymentCompleted($order);
                } catch (\Throwable $e) {
                    Log::error('MercadoPago: cobro OK pero falló emitir comprobante del pedido '.$order->id.': '.$e->getMessage());
                    $result['document'] = [
                        'generated' => false,
                        'message' => $e->getMessage(),
                        'type' => 'warning',
                    ];
                }
                $order->refresh();
            }

            $result['order'] = $order;
            $result['thank_you_url'] = route('tenant_ecommerce_thank_you', [
                'external_id' => $order->external_id,
            ]);
        } elseif (!empty($result['success'])) {
            // La petición llegó a Mercado Pago, pero el pago no fue aprobado.
            $result['success'] = false;
            $result['message'] = 'El pago no fue aprobado por Mercado Pago.';
        }

        return response()->json($result);
    }

    public function paymentIzipay(Request $request)
    {
        if ($blocked = $this->rejectPurchaseIfQuoteOnly()) {
            return $blocked;
        }
        if (! PaymentConfiguration::isIzipayConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Izipay está deshabilitado o incompleto en la configuración global de pagos.',
            ], 422);
        }

        $customer = is_string($request->customer) ? json_decode($request->customer, true) : (array) $request->customer;

        $purchase = is_string($request->purchase)
            ? (json_decode($request->purchase, true) ?: [])
            : (array) ($request->purchase ?? []);

        $ecommerceUser = auth('ecommerce')->user();
        $isGuestOverride = $request->has('is_guest')
            ? filter_var($request->input('is_guest'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : (isset($purchase['checkout']['is_guest'])
                ? (bool) $purchase['checkout']['is_guest']
                : null);
        if ($isGuestOverride === true) {
            $ecommerceUser = null;
        }
        $purchase = Order::attachEcommerceCheckoutMeta($purchase, $ecommerceUser, $isGuestOverride);

        // Pedido previo al formulario: inicia en pago pendiente hasta confirmar PAID.
        $rawItems = is_string($request->items) ? json_decode($request->items, true) : $request->items;
        $order = Order::create([
            'external_id' => Str::uuid()->toString(),
            'customer' => $customer,
            'shipping_address' => $request->input('shipping_address', ''),
            'items' => $this->applyAuthoritativeCampaignPrices((array) $rawItems),
            'total' => $request->precio_culqi,
            'reference_payment' => 'izipay',
            'status_order_id' => StatusOrder::resolveInitialOrderStatusId(),
            'payment_status_order_id' => StatusOrder::resolveInitialPaymentStatusId(),
            'shipping_status_order_id' => StatusOrder::resolveInitialShippingStatusId(),
            'purchase' => $purchase,
        ]);

        $paymentReq = new Request([
            'isTenant' => true,
            'amount' => round((float) $request->precio_culqi * 100),
            'currency' => 'PEN',
            'orderId' => $order->external_id,
            'customer' => [
                'email' => $customer['correo_electronico'] ?? null,
                'billingDetails' => [
                    'firstName' => $customer['apellidos_y_nombres_o_razon_social'] ?? null,
                    'phoneNumber' => $customer['telefono'] ?? null,
                ],
            ],
        ]);

        $izipayController = app(\Modules\Payment\Http\Controllers\PaymentGatewayController::class);
        $result = $izipayController->izipayCreatePayment($paymentReq);

        return response()->json([
            'success' => $result['success'],
            'formToken' => $result['formToken'] ?? null,
            'publickey_izipay' => \Modules\Payment\Models\PaymentConfiguration::getKryptonPublicKeyIzipay(),
            'order' => $order,
        ]);
    }

    public function transactionIzipay(Request $request)
    {
        $paymentReq = new Request([
            'isTenant' => true,
            'uuid' => $request->uuid,
        ]);

        $izipayController = app(\Modules\Payment\Http\Controllers\PaymentGatewayController::class);
        $result = $izipayController->izipayTransaction($paymentReq);

        // Pago confirmado (PAID): asignar estado con action_mark_payment, igual que Culqi.
        if (! empty($result['paid'])) {
            $externalId = $request->input('external_id')
                ?: data_get($result, 'result.answer.orderDetails.orderId')
                ?: data_get($result, 'result.answer.orderId');

            $order = $externalId
                ? Order::where('external_id', $externalId)->first()
                : null;

            if ($order) {
                $paidPaymentStatusId = StatusOrder::resolvePaidPaymentStatusId();
                $dirty = false;

                if ($paidPaymentStatusId && (int) $order->payment_status_order_id !== $paidPaymentStatusId) {
                    $order->payment_status_order_id = $paidPaymentStatusId;
                    $dirty = true;
                }

                if (! $order->status_order_id) {
                    $order->status_order_id = StatusOrder::resolveInitialOrderStatusId();
                    $dirty = true;
                }

                if ($dirty) {
                    $order->save();
                }

                try {
                    $result['document'] = app(OrderDocumentFromStatusService::class)
                        ->afterGatewayPaymentCompleted($order);
                } catch (\Throwable $e) {
                    Log::error('Izipay: cobro OK pero falló emitir comprobante del pedido '.$order->id.': '.$e->getMessage());
                    $result['document'] = [
                        'generated' => false,
                        'message' => $e->getMessage(),
                        'type' => 'warning',
                    ];
                }
                $result['order'] = $order->fresh(['sale_note', 'payment_status_order']);
            }
        }

        return response()->json($result);
    }

    public function izipayRecord()
    {
        return response()->json([
            'publickey_izipay' => \Modules\Payment\Models\PaymentConfiguration::getKryptonPublicKeyIzipay(),
        ]);
    }

    public function paymentCashEmail($customer_email, $document)
    {
        try {
            $email = $customer_email;
            $mailable = new CulqiEmail($document);
            $id = (int) $document->id;
            $model = __FILE__.";;".__LINE__;
            $sendIt = EmailController::SendMail($email, $mailable, $id, $model);
            /*
            Configuration::setConfigSmtpMail();
            $array_email = explode(',', $customer_email);
            if (count($array_email) > 1) {
                foreach ($array_email as $email_to) {
                    $email_to = trim($email_to);
                if(!empty($email_to)) {
                        Mail::to($email_to)->send(new CulqiEmail($document));
                    }
                }
            } else {
                Mail::to($customer_email)->send(new CulqiEmail($document));
            }*/
        }catch(\Exception $e)
        {
            return true;
        }
    }

    public function ratingItem(Request $request)
    {
        if(auth('ecommerce')->user())
        {
            $user_id = auth('ecommerce')->id();
            $row = ItemsRating::firstOrNew( ['user_id' => $user_id, 'item_id' => $request->item_id ] );
            $row->value = $request->value;
            $row->save();
            return[
                'success' => false,
                'message' => 'Rating Guardado'
            ];
        }
        return[
            'success' => false,
            'message' => 'No se guardo Rating'
        ];

    }

    public function getRating($id)
    {
        if(auth('ecommerce')->user())
        {
            $user_id = auth('ecommerce')->id();
            $row = ItemsRating::where('user_id', $user_id)->where('item_id', $id)->first();
            return[
                'success' => true,
                'value' => ($row) ? $row->value : 0,
                'message' => 'Valor Obtenido'
            ];
        }
        return[
            'success' => false,
            'value' => 0,
            'message' => 'No se obtuvo valor'
        ];

    }

    private function getExchangeRateSale()
    {
        try {
            $exchange_rate = app(ServiceController::class)->exchangeRateTest(date('Y-m-d'));

            return (is_array($exchange_rate) && array_key_exists('sale', $exchange_rate) && $exchange_rate['sale'])
                ? $exchange_rate['sale']
                : 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    public function account()
    {
        if (auth('ecommerce')->user()) {
            $identity_document_types = \App\Models\Tenant\Catalogs\IdentityDocumentType::whereActive()->get();
            $categories = \Modules\Item\Models\Category::get();
            return view('ecommerce::document_list.account', compact('identity_document_types', 'categories'));
        } else {
            return redirect('ecommerce');
        }
    }

    public function saveDataUser(Request $request)
    {
        $user = auth('ecommerce')->user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'first_name' => 'required|string|max:255',
            'paternal_last_name' => 'required|string|max:255',
            'maternal_last_name' => 'nullable|string|max:255',
            'telephone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        // Check unique email manually to avoid tenant DB connection issues with generic Validator
        $verifyEmail = \App\Models\Tenant\Person::where('email', $request->email)
            ->where('id', '!=', $user->id)
            ->first();

        if ($verifyEmail) {
            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico ya está registrado por otro usuario.'
            ]);
        }

        $fullName = trim($request->first_name . ' ' . $request->paternal_last_name . ' ' . $request->maternal_last_name);
        $user->name = $fullName;
        $user->email = $request->email;
        $user->telephone = $request->telephone;

        $contact = $user->contact ? (array)$user->contact : [];
        $contact['first_name'] = $request->first_name;
        $contact['paternal_last_name'] = $request->paternal_last_name;
        $contact['maternal_last_name'] = $request->maternal_last_name;
        $contact['date_of_birth'] = $request->date_of_birth;
        $contact['gender'] = $request->gender;

        $user->contact = $contact;

        $user->save();

        // Registrar la dirección de entrega en el historial de direcciones del cliente si tiene ubigeo completo
        $deliveryAddress = $request->input('delivery_address');
        $districtId      = $request->input('district_id');

        if ($deliveryAddress && $districtId) {
            $user->addresses()->firstOrCreate(
                [
                    'address'     => $deliveryAddress,
                    'district_id' => $districtId,
                ],
                [
                    'country_id'    => 'PE',
                    'department_id' => $request->input('department_id'),
                    'province_id'   => $request->input('province_id'),
                    'district_id'   => $districtId,
                    'address'       => $deliveryAddress,
                    'phone'         => $request->input('telephone'),
                    'main'          => false,
                ]
            );
        }

        return ['success' => true, 'message' => 'Datos actualizados correctamente'];

    }

    /**
     * Devuelve la lista actualizada de direcciones del cliente autenticado (consulta directa a BD).
     */
    public function listShippingAddresses()
    {
        $user = auth('ecommerce')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Debe iniciar sesión para ver sus direcciones.',
            ], 401);
        }

        $freshUser = $user->fresh();

        return response()->json([
            'success'   => true,
            'address'   => $this->buildUserShippingAddressPayload($freshUser),
            'addresses' => $this->getUserShippingAddresses($freshUser),
        ]);
    }

    /**
     * Guarda la dirección de envío del checkout (mapa + referencia) en el usuario autenticado.
     * No exige campos de perfil (nombre/email): solo datos de dirección.
     */
    public function saveShippingAddress(Request $request)
    {
        $user = auth('ecommerce')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Debe iniciar sesión para guardar la dirección.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'address_id'    => 'nullable|string|max:64',
            'address'       => 'required|string|max:500',
            'reference'     => 'nullable|string|max:500',
            'full_address'  => 'nullable|string|max:700',
            'latitude'      => 'nullable|numeric',
            'longitude'     => 'nullable|numeric',
            'department_id' => 'nullable|string|max:2',
            'province_id'   => 'nullable|string|max:4',
            'district_id'   => 'nullable|string|max:6',
            'telephone'     => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $street = trim((string) $request->input('address'));
        $reference = trim((string) $request->input('reference', ''));
        $fullAddress = trim((string) $request->input('full_address', ''));

        $normalizedInput = $this->buildShippingAddressRecord([
            'address'       => $street,
            'reference'     => $reference,
            'full_address'  => $fullAddress,
            'latitude'      => $request->input('latitude'),
            'longitude'     => $request->input('longitude'),
            'department_id' => $request->input('department_id'),
            'province_id'   => $request->input('province_id'),
            'district_id'   => $request->input('district_id'),
        ]);

        $street = $normalizedInput['address'];
        $reference = $normalizedInput['reference'];
        $fullAddress = $normalizedInput['full_address'];

        if ($request->filled('telephone')) {
            $user->telephone = $request->input('telephone');
        }

        $user->address = $fullAddress;

        $contact = $this->getContactArray($user);

        $recordData = $normalizedInput;

        $addresses = $this->getUserShippingAddresses($user);
        $requestedAddressId = trim((string) $request->input('address_id', ''));
        $updated = false;

        if ($requestedAddressId !== '') {
            $recordData['id'] = $requestedAddressId;
            $addressRecord = $this->buildShippingAddressRecord($recordData);

            foreach ($addresses as $index => $item) {
                if (($item['id'] ?? null) === $requestedAddressId) {
                    $addresses[$index] = $addressRecord;
                    $updated = true;
                    break;
                }
            }
        }

        if (!$updated) {
            $recordData['id'] = $this->generateNewShippingAddressId();
            $addressRecord = $this->buildShippingAddressRecord($recordData);
            $addresses[] = $addressRecord;
        }

        $personAddressId = $this->persistPersonAddressRecord($user, [
            'address'       => $street,
            'department_id' => $request->input('department_id'),
            'province_id'   => $request->input('province_id'),
            'district_id'   => $request->input('district_id'),
            'phone'         => $request->input('telephone') ?: $user->telephone,
        ], $requestedAddressId !== '' ? $requestedAddressId : null, !$updated);

        if ($personAddressId !== null) {
            $numericId = (string) $personAddressId;
            if (($addressRecord['id'] ?? '') !== $numericId) {
                foreach ($addresses as $index => $item) {
                    if (($item['id'] ?? null) === $addressRecord['id']) {
                        $addresses[$index]['id'] = $numericId;
                        break;
                    }
                }
                $addressRecord['id'] = $numericId;
            }
        }

        $contact['shipping'] = $addressRecord;
        $contact['shipping_addresses'] = array_values($addresses);
        $user->contact = $contact;
        $user->save();

        $freshUser = $user->fresh();

        return response()->json([
            'success'   => true,
            'message'   => 'Dirección de envío guardada correctamente',
            'address'   => $this->buildUserShippingAddressPayload($freshUser),
            'addresses' => $this->getUserShippingAddresses($freshUser),
        ]);
    }

    /**
     * Elimina una dirección guardada del perfil del cliente (contact.shipping_addresses).
     */
    public function deleteShippingAddress(Request $request)
    {
        $user = auth('ecommerce')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Debe iniciar sesión para eliminar la dirección.',
            ], 401);
        }

        $addressId = trim((string) $request->input('address_id', ''));
        if ($addressId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de dirección inválido.',
            ], 422);
        }

        $contact = $this->getContactArray($user);
        $addresses = $this->getUserShippingAddresses($user);
        $remaining = array_values(array_filter($addresses, function ($item) use ($addressId) {
            return ($item['id'] ?? null) !== $addressId;
        }));

        if (count($remaining) === count($addresses)) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la dirección solicitada.',
            ], 404);
        }

        $contact['shipping_addresses'] = $remaining;

        $activeShipping = !empty($contact['shipping']) && is_array($contact['shipping'])
            ? $contact['shipping']
            : [];

        if (empty($remaining)) {
            unset($contact['shipping']);
            $user->address = null;
            $this->clearPersonAddressRecord($user);
        } elseif (($activeShipping['id'] ?? null) === $addressId) {
            $next = $remaining[0];
            $contact['shipping'] = $next;
            $user->address = $next['full_address'] ?? $next['address'] ?? '';
        }

        if (ctype_digit($addressId)) {
            try {
                $user->addresses()->where('id', (int) $addressId)->delete();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $user->contact = $contact;
        $user->save();

        $freshUser = $user->fresh();
        $freshAddresses = $this->getUserShippingAddresses($freshUser);

        return response()->json([
            'success'   => true,
            'message'   => 'Dirección eliminada correctamente',
            'address'   => !empty($freshAddresses)
                ? $this->buildUserShippingAddressPayload($freshUser)
                : null,
            'addresses' => $freshAddresses,
        ]);
    }

    /**
     * Payload de dirección de envío activa para precargar el checkout.
     */
    private function buildUserShippingAddressPayload($ecommerceUser): ?array
    {
        $addresses = $this->getUserShippingAddresses($ecommerceUser);
        $contact = $this->getContactArray($ecommerceUser);

        if (empty($addresses)) {
            return null;
        }

        $activeId = !empty($contact['shipping']['id'])
            ? (string) $contact['shipping']['id']
            : null;

        if ($activeId) {
            foreach ($addresses as $addr) {
                if (($addr['id'] ?? null) === $activeId) {
                    return $this->appendPhoneToAddressPayload($addr, $ecommerceUser);
                }
            }
        }

        $ecommerceUser->load(['addresses' => function ($query) {
            $query->orderByDesc('main')->orderByDesc('id');
        }]);

        $mainAddress = $ecommerceUser->addresses->firstWhere('main', true)
            ?? $ecommerceUser->addresses->first();

        if ($mainAddress) {
            $mainId = (string) $mainAddress->id;
            foreach ($addresses as $addr) {
                if (($addr['id'] ?? null) === $mainId) {
                    return $this->appendPhoneToAddressPayload($addr, $ecommerceUser, $mainAddress->phone);
                }
            }
        }

        return $this->appendPhoneToAddressPayload($addresses[0], $ecommerceUser);
    }

    /**
     * Agrega teléfono al payload de dirección de envío.
     */
    private function appendPhoneToAddressPayload(array $address, $ecommerceUser, ?string $fallbackPhone = null): array
    {
        $address['phone'] = $ecommerceUser->telephone ?: ($fallbackPhone ?: ($address['phone'] ?? null));

        return $address;
    }

    /**
     * Convierte contact (object|array|null) en array asociativo profundo.
     */
    private function getContactArray($ecommerceUser): array
    {
        if (!$ecommerceUser || !$ecommerceUser->contact) {
            return [];
        }

        $decoded = json_decode(json_encode($ecommerceUser->contact), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Lista de direcciones del cliente: person_addresses (BD) enriquecidas con contact.shipping_addresses.
     */
    private function getUserShippingAddresses($ecommerceUser): array
    {
        if (!$ecommerceUser) {
            return [];
        }

        $ecommerceUser->load(['addresses' => function ($query) {
            $query->orderByDesc('main')->orderByDesc('id');
        }]);

        $contact = $this->getContactArray($ecommerceUser);
        $contactAddresses = $this->extractContactShippingAddresses($contact);
        $personAddresses = $ecommerceUser->addresses;

        if ($personAddresses->isNotEmpty()) {
            $addresses = [];

            foreach ($personAddresses as $personAddress) {
                $contactMatch = $this->findMatchingContactAddress($contactAddresses, $personAddress);
                $record = $this->buildShippingAddressFromPersonAddress($personAddress, $contactMatch);

                if (!empty($record['address']) || !empty($record['full_address'])) {
                    $addresses[] = $record;
                }
            }

            return $addresses;
        }

        if (!empty($contactAddresses)) {
            return $contactAddresses;
        }

        // Listado explícitamente vacío (p. ej. el usuario eliminó todas sus direcciones).
        if (array_key_exists('shipping_addresses', $contact)) {
            return [];
        }

        $street = trim((string) ($ecommerceUser->address ?? ''));
        if ($street === '') {
            return [];
        }

        return [$this->buildShippingAddressRecord([
            'address'      => $street,
            'full_address' => $street,
        ])];
    }

    /**
     * Normaliza direcciones almacenadas en contact.shipping_addresses.
     */
    private function extractContactShippingAddresses(array $contact): array
    {
        $addresses = [];

        if (empty($contact['shipping_addresses']) || !is_array($contact['shipping_addresses'])) {
            return $addresses;
        }

        foreach ($contact['shipping_addresses'] as $item) {
            if (is_object($item)) {
                $item = (array) $item;
            }
            if (!is_array($item)) {
                continue;
            }

            $normalized = $this->buildShippingAddressRecord($item);
            if (!empty($normalized['address']) || !empty($normalized['full_address'])) {
                $addresses[] = $normalized;
            }
        }

        return $addresses;
    }

    /**
     * Convierte un registro de person_addresses al formato del checkout.
     */
    private function buildShippingAddressFromPersonAddress(PersonAddress $personAddress, ?array $contactMatch = null): array
    {
        $parsedPerson = $this->splitAddressReference($personAddress->address ?? '');
        $street = $parsedPerson['street'];
        $reference = $parsedPerson['reference'];

        $record = [
            'id'            => (string) $personAddress->id,
            'address'       => $street,
            'reference'     => $reference,
            'full_address'  => $this->composeFullAddress($street, $reference),
            'latitude'      => null,
            'longitude'     => null,
            'department_id' => $personAddress->department_id,
            'province_id'   => $personAddress->province_id,
            'district_id'   => $personAddress->district_id,
            'phone'         => $personAddress->phone,
        ];

        if (!$contactMatch) {
            $record['location_id'] = $this->buildLocationIdArray(
                $record['department_id'] ?? null,
                $record['province_id'] ?? null,
                $record['district_id'] ?? null
            );

            return $record;
        }

        $parsedContact = $this->splitAddressReference($contactMatch['address'] ?? '');
        $parsedContactFull = $this->splitAddressReference($contactMatch['full_address'] ?? '');

        if ($street === '') {
            $street = $parsedContact['street'] ?: $parsedContactFull['street'];
        }

        $contactReference = trim((string) ($contactMatch['reference'] ?? ''));
        if ($contactReference === '') {
            $contactReference = $parsedContact['reference'] ?: $parsedContactFull['reference'];
        }

        if ($contactReference !== '') {
            $reference = $contactReference;
        }

        $record['address'] = $street;
        $record['reference'] = $reference;
        $record['full_address'] = $this->composeFullAddress($street, $reference);

        if (isset($contactMatch['latitude']) && $contactMatch['latitude'] !== null && $contactMatch['latitude'] !== '') {
            $record['latitude'] = (float) $contactMatch['latitude'];
        }
        if (isset($contactMatch['longitude']) && $contactMatch['longitude'] !== null && $contactMatch['longitude'] !== '') {
            $record['longitude'] = (float) $contactMatch['longitude'];
        }

        $record = array_merge($record, $this->resolveUbigeoIds($record));

        if (!$record['department_id'] && $contactMatch) {
            $contactUbigeo = $this->resolveUbigeoIds($contactMatch);
            if ($contactUbigeo['department_id']) {
                $record['department_id'] = $contactUbigeo['department_id'];
                $record['province_id'] = $contactUbigeo['province_id'];
                $record['district_id'] = $contactUbigeo['district_id'];
            }
        }

        $record['location_id'] = $this->buildLocationIdArray(
            $record['department_id'] ?? null,
            $record['province_id'] ?? null,
            $record['district_id'] ?? null
        );

        return $record;
    }

    /**
     * Busca en contact.shipping_addresses la entrada que corresponde a una fila de person_addresses.
     */
    private function findMatchingContactAddress(array $contactAddresses, PersonAddress $personAddress): ?array
    {
        $personId = (string) $personAddress->id;
        $normalizedPersonAddress = $this->normalizeAddressText($personAddress->address);

        foreach ($contactAddresses as $contactAddress) {
            $contactId = (string) ($contactAddress['id'] ?? '');
            if ($contactId !== '' && $contactId === $personId) {
                return $contactAddress;
            }
        }

        foreach ($contactAddresses as $contactAddress) {
            $candidates = [
                $contactAddress['address'] ?? '',
                $contactAddress['full_address'] ?? '',
            ];

            foreach ($candidates as $candidate) {
                if ($normalizedPersonAddress !== '' && $this->normalizeAddressText($candidate) === $normalizedPersonAddress) {
                    return $contactAddress;
                }
            }
        }

        return null;
    }

    /**
     * Normaliza texto de dirección para comparaciones.
     */
    private function normalizeAddressText(?string $value): string
    {
        $normalized = trim((string) $value);
        $normalized = preg_replace('/\s*-\s*Ref:.*$/i', '', $normalized);

        return mb_strtolower(preg_replace('/\s+/', ' ', $normalized));
    }

    /**
     * Genera un identificador único para una nueva dirección de envío.
     */
    private function generateNewShippingAddressId(): string
    {
        return 'addr_' . bin2hex(random_bytes(8));
    }

    /**
     * Separa calle y referencia cuando vienen concatenadas en un solo texto.
     */
    private function splitAddressReference(?string $text): array
    {
        $raw = trim((string) $text);
        if ($raw === '') {
            return ['street' => '', 'reference' => ''];
        }

        if (preg_match('/\s*-\s*Ref[.:]?\s*(.+)$/iu', $raw, $matches)) {
            return [
                'street'    => trim(preg_replace('/\s*-\s*Ref[.:]?\s*.+$/iu', '', $raw)),
                'reference' => trim($matches[1]),
            ];
        }

        return ['street' => $raw, 'reference' => ''];
    }

    /**
     * Construye la dirección completa sin duplicar la referencia.
     */
    private function composeFullAddress(string $street, string $reference): string
    {
        $street = trim($street);
        $reference = trim($reference);

        if ($street === '') {
            return $reference !== '' ? 'Ref: ' . $reference : '';
        }

        if ($reference === '') {
            return $street;
        }

        return $street . ' - Ref: ' . $reference;
    }

    /**
     * Normaliza un registro de dirección de envío para contact.shipping_addresses.
     */
    private function buildShippingAddressRecord(array $data): array
    {
        $streetInput = trim((string) ($data['address'] ?? ''));
        $referenceInput = trim((string) ($data['reference'] ?? ''));
        $fullInput = trim((string) ($data['full_address'] ?? ''));

        $fromStreet = $this->splitAddressReference($streetInput);
        $fromFull = $this->splitAddressReference($fullInput);

        $street = $fromStreet['street'] ?: $fromFull['street'];
        $reference = $referenceInput ?: ($fromStreet['reference'] ?: $fromFull['reference']);
        $fullAddress = $this->composeFullAddress($street, $reference);
        $ubigeo = $this->resolveUbigeoIds($data);

        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            $seed = $fullAddress ?: $street;
            $id = 'addr_' . substr(md5($seed), 0, 16);
        }

        return [
            'id'            => $id,
            'address'       => $street,
            'reference'     => $reference,
            'full_address'  => $fullAddress,
            'latitude'      => isset($data['latitude']) && $data['latitude'] !== null && $data['latitude'] !== ''
                ? (float) $data['latitude']
                : null,
            'longitude'     => isset($data['longitude']) && $data['longitude'] !== null && $data['longitude'] !== ''
                ? (float) $data['longitude']
                : null,
            'department_id' => $ubigeo['department_id'],
            'province_id'   => $ubigeo['province_id'],
            'district_id'   => $ubigeo['district_id'],
            'location_id'   => $this->buildLocationIdArray(
                $ubigeo['department_id'],
                $ubigeo['province_id'],
                $ubigeo['district_id']
            ),
        ];
    }

    /**
     * Resuelve department/province/district desde campos sueltos o location_id.
     */
    private function resolveUbigeoIds(array $data): array
    {
        $departmentId = $data['department_id'] ?? null;
        $provinceId = $data['province_id'] ?? null;
        $districtId = $data['district_id'] ?? null;

        $locationId = $data['location_id'] ?? null;
        if (is_array($locationId) && count($locationId) === 3) {
            if (!empty($locationId[0]) && !empty($locationId[1]) && !empty($locationId[2])) {
                $departmentId = $locationId[0];
                $provinceId = $locationId[1];
                $districtId = $locationId[2];
            }
        }

        return [
            'department_id' => $departmentId ?: null,
            'province_id'   => $provinceId ?: null,
            'district_id'   => $districtId ?: null,
        ];
    }

    /**
     * Construye el arreglo location_id solo cuando los tres niveles están presentes.
     */
    private function buildLocationIdArray($departmentId, $provinceId, $districtId): array
    {
        if (empty($departmentId) || empty($provinceId) || empty($districtId)) {
            return [];
        }

        return [$departmentId, $provinceId, $districtId];
    }

    /**
     * Elimina los registros de person_addresses del cliente (best-effort).
     */
    private function clearPersonAddressRecord($user): void
    {
        try {
            $user->addresses()->delete();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Actualiza o crea el registro en person_addresses (best-effort; no bloquea si falta ubigeo).
     */
    private function persistPersonAddressRecord($user, array $data, ?string $addressId = null, bool $forceCreate = false): ?int
    {
        $address = trim((string) ($data['address'] ?? ''));
        if ($address === '') {
            return null;
        }

        $payload = [
            'country_id'    => 'PE',
            'address'       => $address,
            'phone'         => $data['phone'] ?? $user->telephone,
            'main'          => true,
            'department_id' => $data['department_id'] ?: null,
            'province_id'   => $data['province_id'] ?: null,
            'district_id'   => $data['district_id'] ?: null,
        ];

        try {
            if ($addressId !== null && $addressId !== '' && ctype_digit($addressId)) {
                $existing = $user->addresses()->find((int) $addressId);
                if ($existing) {
                    $existing->fill($payload);
                    $existing->save();

                    return (int) $existing->id;
                }
            }

            if (!$forceCreate) {
                $existing = $user->addresses()->orderByDesc('id')->first();
                if ($existing) {
                    $existing->fill($payload);
                    $existing->save();

                    return (int) $existing->id;
                }
            }

            $created = $user->addresses()->create($payload);

            return (int) $created->id;
        } catch (\Throwable $e) {
            // Ubigeo inválido u otras FKs: la dirección ya quedó en persons.address / contact.shipping
            report($e);
        }

        return null;
    }

    public function searchDocument($type, $number)
    {
        return (new ServiceData)->service($type, $number);
    }

    /**
     * Construye la URL absoluta de la lista de pedidos usando el hostname del tenant activo en la request.
     */
    private function buildOrderListUrl(): string
    {
        $hostname = app(CurrentHostname::class);
        $fqdn     = $hostname ? $hostname->fqdn : config('app.url');
        $protocol = config('tenant.force_https') ? 'https' : 'http';
        return "{$protocol}://{$fqdn}/ecommerce/order_list";
    }

    /**
     * Consulta pública de RUC/DNI para autocompletar el nombre / razón social
     * en el formulario de registro del ecommerce (invitados, sin auth).
     *
     * Con ?checkout=1 devuelve datos del cliente existente para autocompletar
     * el checkout invitado sin crear registros duplicados.
     */
    public function searchDocumentPublic(Request $request, $number)
    {
        $number = preg_replace('/\D/', '', (string) $number);

        if (strlen($number) === 8) {
            $type = 'dni';
        } elseif (strlen($number) === 11) {
            $type = 'ruc';
        } else {
            return [
                'success' => false,
                'message' => 'El número debe tener 8 dígitos (DNI) u 11 dígitos (RUC).',
            ];
        }

        $isCheckout = $request->boolean('checkout')
            || $request->get('context') === 'checkout';

        $email = strtolower(trim((string) $request->input('email', '')));
        $telephone = preg_replace('/\D/', '', (string) $request->input('telephone', ''));
        $requestedDocTypeId = (string) $request->input('identity_document_type_id', '');
        $hasTripleInput = $isCheckout
            && $email !== ''
            && strlen($telephone) >= 7
            && in_array($requestedDocTypeId, ['1', '6'], true);

        $person = Person::where('number', $number)
            ->where('type', 'customers')
            ->first();

        if ($person && !$isCheckout) {
            return [
                'success' => false,
                'exists' => true,
                'message' => 'Este documento ya está registrado. Si no tienes acceso a tu cuenta, comunícate con la tienda para recuperarlo o',
            ];
        }

        if ($person && $isCheckout) {
            $response = $this->buildCheckoutDocumentLookupResponse($person, $type);
        } else {
            try {
                $result = $this->searchDocument($type, $number);
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'message' => 'No se pudo consultar el documento. Intente nuevamente.',
                ];
            }

            if (empty($result['success'])) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'Datos no encontrados.',
                ];
            }

            $response = [
                'success' => true,
                'type' => $type,
                'name' => $result['data']['name'] ?? '',
            ];
        }

        if ($isCheckout && $hasTripleInput) {
            $tripleResult = $this->resolveGuestCheckoutTripleMatch(
                $number,
                $requestedDocTypeId,
                $email,
                $telephone,
                $person
            );

            $response['triple_match'] = $tripleResult['triple_match'];

            if ($tripleResult['triple_match'] && !empty($tripleResult['is_registered_customer'])) {
                $response['exists'] = true;
                $response['from_database'] = true;
                $response['is_registered_customer'] = true;
                $response['message'] = 'Encontramos tus datos registrados. Puedes actualizarlos si lo necesitas para esta compra.';
            } elseif ($tripleResult['triple_match'] && !empty($tripleResult['address'])) {
                $response['address_loaded'] = true;
                $response['is_returning_guest'] = !empty($tripleResult['is_returning_guest']);
                $response['address'] = $tripleResult['address'];
                $response['department_id'] = $tripleResult['department_id'] ?? null;
                $response['province_id'] = $tripleResult['province_id'] ?? null;
                $response['district_id'] = $tripleResult['district_id'] ?? null;
            }
        }

        return $response;
    }

    /**
     * Respuesta inicial de checkout invitado al consultar solo el documento (sin triple validación).
     */
    protected function buildCheckoutDocumentLookupResponse(Person $person, string $type): array
    {
        $response = [
            'success' => true,
            'type' => $type,
            'name' => $person->name,
        ];

        if (!empty($person->password)) {
            $response['exists'] = true;
            $response['from_database'] = true;
            $response['is_registered_customer'] = true;
            $response['message'] = 'Encontramos tus datos registrados. Puedes actualizarlos si lo necesitas para esta compra.';
        }

        return $response;
    }

    /**
     * Valida coincidencia exacta de documento + correo + teléfono y recupera dirección guardada.
     */
    protected function resolveGuestCheckoutTripleMatch(
        string $number,
        string $docTypeId,
        string $email,
        string $telephone,
        ?Person $person = null
    ): array {
        if (!$this->guestDocumentTypeMatchesNumber($docTypeId, $number)) {
            return ['triple_match' => false];
        }

        if ($person) {
            if (!$this->personMatchesGuestTriple($person, $number, $docTypeId, $email, $telephone)) {
                return ['triple_match' => false];
            }

            if (!empty($person->password)) {
                return [
                    'triple_match' => true,
                    'is_registered_customer' => true,
                ];
            }

            $addressData = $this->extractPersonShippingAddress($person);
            if (!empty($addressData['address'])) {
                return array_merge([
                    'triple_match' => true,
                    'is_returning_guest' => true,
                ], $addressData);
            }
        } else {
            $orderAddress = $this->findGuestOrderAddressByTripleMatch($number, $docTypeId, $email, $telephone);
            if ($orderAddress && !empty($orderAddress['address'])) {
                return array_merge([
                    'triple_match' => true,
                    'is_returning_guest' => true,
                ], $orderAddress);
            }

            return ['triple_match' => false];
        }

        $orderAddress = $this->findGuestOrderAddressByTripleMatch($number, $docTypeId, $email, $telephone);
        if ($orderAddress && !empty($orderAddress['address'])) {
            return array_merge([
                'triple_match' => true,
                'is_returning_guest' => true,
            ], $orderAddress);
        }

        return ['triple_match' => true];
    }

    protected function guestDocumentTypeMatchesNumber(string $docTypeId, string $number): bool
    {
        if ($docTypeId === '1') {
            return strlen($number) === 8;
        }

        if ($docTypeId === '6') {
            return strlen($number) === 11;
        }

        return false;
    }

    protected function personMatchesGuestTriple(
        Person $person,
        string $number,
        string $docTypeId,
        string $email,
        string $telephone
    ): bool {
        $personNumber = preg_replace('/\D/', '', (string) $person->number);
        $personDocType = (string) ($person->identity_document_type_id ?? (strlen($personNumber) === 11 ? '6' : '1'));
        $personEmail = strtolower(trim((string) ($person->email ?? '')));
        $personPhone = preg_replace('/\D/', '', (string) ($person->telephone ?? ''));

        return $personNumber === $number
            && $personDocType === $docTypeId
            && $personEmail === $email
            && $personPhone === $telephone;
    }

    protected function extractPersonShippingAddress(Person $person): array
    {
        $firstAddress = $person->addresses()->first();

        return [
            'address' => $firstAddress->address ?? $person->address,
            'department_id' => $firstAddress->department_id ?? $person->department_id,
            'province_id' => $firstAddress->province_id ?? $person->province_id,
            'district_id' => $firstAddress->district_id ?? $person->district_id,
        ];
    }

    protected function findGuestOrderAddressByTripleMatch(
        string $number,
        string $docTypeId,
        string $email,
        string $telephone
    ): ?array {
        $orders = Order::query()
            ->whereNotNull('shipping_address')
            ->where('shipping_address', '!=', '')
            ->orderByDesc('id')
            ->limit(300)
            ->get(['customer', 'shipping_address']);

        foreach ($orders as $order) {
            $customer = $order->customer;
            if (!$customer) {
                continue;
            }

            $customerNumber = preg_replace('/\D/', '', (string) ($customer->numero_documento ?? ''));
            $customerDocType = (string) ($customer->identity_document_type_id ?? $customer->codigo_tipo_documento_identidad ?? '');
            $customerEmail = strtolower(trim((string) ($customer->correo_electronico ?? '')));
            $customerPhone = preg_replace('/\D/', '', (string) ($customer->telefono ?? ''));

            if ($customerNumber !== $number) {
                continue;
            }
            if ($customerDocType !== $docTypeId) {
                continue;
            }
            if ($customerEmail !== $email) {
                continue;
            }
            if ($customerPhone !== $telephone) {
                continue;
            }

            return [
                'address' => $order->shipping_address,
                'department_id' => null,
                'province_id' => null,
                'district_id' => null,
            ];
        }

        return null;
    }

    public function getGoogleMaps()
    {
        $config = SystemConfiguration::first();
        return $config ? $config->google_maps_api_key : null;
    }

    public function getGoogleMapsScript()
    {
        $apiKey = $this->getGoogleMaps();

        if (empty($apiKey)) {
            return response()->json(['error' => 'Google Maps API key is missing or invalid.'], 400);
        }

        $scriptUrl = "https://maps.googleapis.com/maps/api/js?key={$apiKey}&libraries=places";
        return redirect($scriptUrl);
    }

    public function getLocationCascade()
    {
        $locations = [];
        $departments = Department::where('active', true)->get();

        foreach ($departments as $department) {
            $children_provinces = [];
            foreach ($department->provinces as $province) {
                $children_districts = [];
                foreach ($province->districts as $district) {
                    $children_districts[] = [
                        'value' => $district->id,
                        'label' => $district->description
                    ];
                }
                $children_provinces[] = [
                    'value' => $province->id,
                    'label' => $province->description,
                    'children' => $children_districts
                ];
            }
            $locations[] = [
                'value' => $department->id,
                'label' => $department->description,
                'children' => $children_provinces
            ];
        }

        return response()->json($locations);
    }

    public function termsConditions()
    {
        $config = \App\Models\Tenant\ConfigurationEcommerce::first();
        $terms_conditions = $config ? $config->terms_conditions : null;
        $categories = \Modules\Item\Models\Category::has('items')->get();
        return view('ecommerce::pages_fields.terms_conditions', compact('terms_conditions', 'categories'));
    }

    public function thankYou($external_id)
    {
        $order = Order::where('external_id', $external_id)->firstOrFail();
        $categories = \Modules\Item\Models\Category::has('items')->get();

        $paymentLabels = [
            'efectivo'      => 'Efectivo',
            'yape'          => 'Yape',
            'transferencia' => 'Transferencia',
            'culqi'         => 'Tarjeta (VISA)',
            'paypal'        => 'PayPal',
        ];
        $refPayment   = strtolower($order->reference_payment ?? 'efectivo');
        $paymentLabel = $paymentLabels[$refPayment] ?? ucfirst($refPayment);

        $shipping      = $order->shipping_address ?? '';
        $isPickup      = stripos($shipping, 'Recojo en tienda') === 0;
        $deliveryLabel = $isPickup ? $shipping : 'Envío a domicilio';

        $itemsCount = is_countable($order->items) ? count($order->items) : 0;

        return view('ecommerce::cart.thank_you', compact(
            'order', 'categories', 'paymentLabel', 'deliveryLabel', 'isPickup', 'itemsCount'
        ));
    }

    /**
     * Página pública de seguimiento del pedido (estado de envío).
     */
    public function orderTracking(Request $request)
    {
        $categories = Category::has('items')->get();
        $initialPedido = trim((string) $request->query('pedido', ''));

        $ecommerceConfiguration = ConfigurationEcommerce::first();
        $configurationModel = Configuration::first();
        $phoneWhatsapp = optional($ecommerceConfiguration)->phone_whatsapp
            ?: optional($configurationModel)->phone_whatsapp;
        $whatsappPhone = $phoneWhatsapp ? preg_replace('/\D+/', '', (string) $phoneWhatsapp) : '';
        $showWhatsapp = $whatsappPhone !== '';
        $storeUrl = route('tenant.ecommerce.index');

        return view('ecommerce::cart.order_tracking', compact(
            'categories',
            'initialPedido',
            'showWhatsapp',
            'whatsappPhone',
            'storeUrl'
        ));
    }

    /**
     * Lookup de seguimiento: N° de pedido + DNI/documento del comprador.
     */
    public function orderTrackingLookup(Request $request)
    {
        $denied = [
            'success' => false,
            'message' => 'No encontramos ese pedido o los datos no coinciden.',
        ];

        $raw = trim((string) $request->query('pedido', ''));
        $document = preg_replace('/\D+/', '', (string) $request->query('documento', $request->query('dni', '')));

        if (preg_replace('/\D+/', '', $raw) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Ingresa un número de pedido válido.',
            ]);
        }

        if ($document === '') {
            return response()->json([
                'success' => false,
                'message' => 'Ingresa tu DNI / documento para verificar el pedido.',
            ]);
        }

        $order = Order::with(['shipping_status_order', 'payment_status_order'])->findByPublicNumber($raw);
        if (! $order || ! $this->orderTrackingDocumentMatches($order, $document)) {
            return response()->json($denied);
        }

        // Sesión ecommerce: solo pedidos de la misma cuenta (correo).
        $ecommerceUser = auth('ecommerce')->user();
        if ($ecommerceUser && ! $this->orderTrackingBelongsToUser($order, $ecommerceUser)) {
            return response()->json($denied);
        }

        $paymentLabels = [
            'efectivo'      => 'Efectivo',
            'yape'          => 'Yape',
            'transferencia' => 'Transferencia',
            'culqi'         => 'Tarjeta (VISA)',
            'culqui'        => 'Tarjeta (VISA)',
            'paypal'        => 'PayPal',
            'mp'            => 'Mercado Pago',
            'izipay'        => 'Izipay',
        ];
        $refPayment = strtolower((string) ($order->reference_payment ?? 'efectivo'));
        $paymentLabel = $paymentLabels[$refPayment] ?? ucfirst($refPayment);

        $shipping = (string) ($order->shipping_address ?? '');
        $isPickup = stripos($shipping, 'Recojo en tienda') === 0;
        $deliveryLabel = $isPickup ? ($shipping ?: 'Recojo en tienda') : 'Envío a domicilio';

        $items = [];
        $rawItems = $order->items;
        if (is_string($rawItems)) {
            $rawItems = json_decode($rawItems, true);
        }
        if (is_object($rawItems)) {
            $rawItems = (array) $rawItems;
        }
        if (is_array($rawItems)) {
            foreach ($rawItems as $item) {
                $row = is_object($item) ? (array) $item : $item;
                if (! is_array($row)) {
                    continue;
                }
                $qty = (float) ($row['cantidad'] ?? $row['quantity'] ?? 1);
                $price = (float) ($row['sale_unit_price'] ?? $row['unit_price'] ?? $row['price'] ?? 0);
                $image = $row['image'] ?? $row['image_url'] ?? null;
                if (is_string($image) && $image !== '' && $image !== 'imagen-no-disponible.jpg' && ! preg_match('#^https?://#i', $image)) {
                    $image = asset('storage/uploads/items/'.$image);
                }
                if (! is_string($image) || $image === '' || $image === 'imagen-no-disponible.jpg') {
                    $image = asset('logo/imagen-no-disponible.jpg');
                }
                $items[] = [
                    'description' => (string) ($row['description'] ?? $row['name'] ?? 'Producto'),
                    'quantity' => $qty > 0 ? $qty : 1,
                    'unit_price' => round($price, 2),
                    'total' => round($qty * $price, 2),
                    'image' => $image,
                ];
            }
        }

        $allShippingStatuses = StatusOrder::where('is_shipping_status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'description',
                'color',
                'sort_order',
                'is_initial',
                'is_final',
                'action_notify_dispatch',
            ]);

        $currentShipping = $order->shipping_status_order;
        $currentShippingId = $order->shipping_status_order_id
            ? (int) $order->shipping_status_order_id
            : null;

        // Fin del flujo normal (p. ej. "Entregado"). Estados con sort_order mayor
        // son excepciones (p. ej. "Entrega pendiente") y no van en el timeline
        // salvo que el admin los haya asignado manualmente a este pedido.
        $finalSortOrder = $allShippingStatuses
            ->where('is_final', true)
            ->min('sort_order');

        $currentIsSideStatus = $currentShippingId
            && $finalSortOrder !== null
            && $allShippingStatuses->contains(function ($status) use ($currentShippingId, $finalSortOrder) {
                return (int) $status->id === $currentShippingId
                    && (int) $status->sort_order > (int) $finalSortOrder;
            });

        $shippingStatuses = $allShippingStatuses
            ->filter(function ($status) use ($currentShippingId, $finalSortOrder, $currentIsSideStatus, $isPickup) {
                $isCurrent = $currentShippingId && (int) $status->id === $currentShippingId;
                $isSide = $finalSortOrder !== null
                    && (int) $status->sort_order > (int) $finalSortOrder;

                // Excepción (p. ej. Entrega pendiente): solo si es el estado actual
                if ($isSide) {
                    return $isCurrent;
                }

                // Si el pedido está en excepción, ocultar el final ("Entregado")
                if ($currentIsSideStatus && $status->is_final) {
                    return false;
                }

                // Timeline según modo: domicilio sin recojo; recojo sin "En camino".
                if ($isPickup && $this->isDeliveryOnlyShippingStatus($status)) {
                    return false;
                }
                if (! $isPickup && $this->isPickupOnlyShippingStatus($status)) {
                    return false;
                }

                return true;
            })
            ->map(function ($status) use ($finalSortOrder) {
                $isSide = $finalSortOrder !== null
                    && (int) $status->sort_order > (int) $finalSortOrder;

                return [
                    'id' => (int) $status->id,
                    'description' => $status->description,
                    'color' => $status->color ?: '#ff7a00',
                    'sort_order' => (int) $status->sort_order,
                    'is_initial' => (bool) $status->is_initial,
                    'is_final' => (bool) $status->is_final,
                    'is_side_status' => $isSide,
                ];
            })
            ->values();

        // Si aún no tiene estado, usar el inicial del catálogo como "pendiente actual"
        if (! $currentShippingId && $shippingStatuses->isNotEmpty()) {
            $initial = $shippingStatuses->firstWhere('is_initial', true) ?: $shippingStatuses->first();
            $currentShippingId = $initial ? (int) $initial['id'] : null;
        }

        // Progreso visual del timeline: si el estado real no pertenece a este flujo
        // (p. ej. "Listo para recojo" en domicilio), avanzar al siguiente paso válido.
        $timelineShippingStatusId = $currentShippingId;
        if (
            $currentShippingId
            && $shippingStatuses->isNotEmpty()
            && ! $shippingStatuses->contains('id', $currentShippingId)
        ) {
            $currentSort = (int) optional(
                $allShippingStatuses->firstWhere('id', $currentShippingId)
            )->sort_order;

            $next = $shippingStatuses->first(function ($status) use ($currentSort) {
                return (int) $status['sort_order'] > $currentSort;
            });
            $prev = $shippingStatuses
                ->filter(function ($status) use ($currentSort) {
                    return (int) $status['sort_order'] < $currentSort;
                })
                ->last();

            $timelineShippingStatusId = $next['id'] ?? ($prev['id'] ?? null);
        }

        $catalogCurrent = $currentShippingId
            ? ($shippingStatuses->firstWhere('id', $currentShippingId)
                ?: $allShippingStatuses->firstWhere('id', $currentShippingId))
            : null;

        if ($catalogCurrent && ! is_array($catalogCurrent)) {
            $catalogCurrent = [
                'description' => $catalogCurrent->description,
                'color' => $catalogCurrent->color ?: '#ff7a00',
            ];
        }

        // Timeline de tienda: Pendiente → Pago completado → estados de envío.
        // Evita marcar "Pendiente" con check al pagar (paso aparte).
        $paymentStatus = $order->payment_status_order;
        $isPaymentCompleted = (bool) optional($paymentStatus)->action_mark_payment;
        $pendingPaymentStatus = StatusOrder::where('is_payment_status', true)
            ->where(function ($q) {
                $q->where('action_mark_payment', false)->orWhereNull('action_mark_payment');
            })
            ->orderByDesc('is_initial')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
        $paidPaymentStatus = StatusOrder::where('is_payment_status', true)
            ->where('action_mark_payment', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        $timelineStatuses = collect();
        if ($pendingPaymentStatus) {
            $timelineStatuses->push([
                'id' => (int) $pendingPaymentStatus->id,
                'description' => 'Pendiente',
                'color' => $pendingPaymentStatus->color ?: '#f59e0b',
                'sort_order' => -2,
                'is_initial' => true,
                'is_final' => false,
                'is_side_status' => false,
                'kind' => 'payment_pending',
            ]);
        }
        if ($paidPaymentStatus) {
            $timelineStatuses->push([
                'id' => (int) $paidPaymentStatus->id,
                'description' => 'Pago completado',
                'color' => $paidPaymentStatus->color ?: '#16a34a',
                'sort_order' => -1,
                'is_initial' => false,
                'is_final' => false,
                'is_side_status' => false,
                'kind' => 'payment_completed',
            ]);
        }
        $timelineStatuses = $timelineStatuses
            ->concat($shippingStatuses->map(function (array $status) {
                $status['kind'] = 'shipping';

                return $status;
            }))
            ->values();

        if (! $isPaymentCompleted && $pendingPaymentStatus) {
            $timelineStatusId = (int) $pendingPaymentStatus->id;
            $badgeDescription = 'Pendiente';
            $badgeColor = $pendingPaymentStatus->color ?: '#f59e0b';
        } elseif ($isPaymentCompleted && $paidPaymentStatus && ! $timelineShippingStatusId) {
            // Pagado, pero aún sin estado de envío útil: resaltar "Pago completado".
            $timelineStatusId = (int) $paidPaymentStatus->id;
            $badgeDescription = 'Pago completado';
            $badgeColor = $paidPaymentStatus->color ?: '#16a34a';
        } else {
            $timelineStatusId = $timelineShippingStatusId;
            $badgeDescription = $currentShipping->description
                ?? ($catalogCurrent['description'] ?? 'Pendiente');
            $badgeColor = $currentShipping->color
                ?? ($catalogCurrent['color'] ?? '#ff7a00');
        }

        return response()->json([
            'success' => true,
            'order' => [
                'id' => (int) $order->id,
                'number' => '#' . $order->publicNumber(),
                'order_code' => $order->order_code,
                'total' => round((float) $order->total, 2),
                'payment_label' => $paymentLabel,
                'delivery_label' => $deliveryLabel,
                'is_pickup' => $isPickup,
                'payment_completed' => $isPaymentCompleted,
                'items' => $items,
                'items_count' => count($items),
                'shipping_status_order_id' => $currentShippingId,
                'timeline_status_order_id' => $timelineStatusId,
                'shipping_status_description' => $badgeDescription,
                'shipping_status_color' => $badgeColor,
                'tracking_code' => $order->tracking_code
                    ? (string) $order->tracking_code
                    : null,
                'created_at' => optional($order->created_at)->format('d/m/Y H:i'),
            ],
            'shipping_statuses' => $shippingStatuses,
            'timeline_statuses' => $timelineStatuses,
        ]);
    }

    public function privacyPolicy()
    {
        $config = \App\Models\Tenant\ConfigurationEcommerce::first();
        $privacy_policy = $config ? $config->privacy_policy : null;
        $categories = \Modules\Item\Models\Category::has('items')->get();
        return view('ecommerce::pages_fields.privacy_policy', compact('privacy_policy', 'categories'));
    }

    public function aboutUs()
    {
        $config = \App\Models\Tenant\ConfigurationEcommerce::first();
        $about_us = $config ? $config->about_us : null;
        $categories = \Modules\Item\Models\Category::has('items')->get();
        return view('ecommerce::pages_fields.about_us', compact('about_us', 'categories'));
    }

    public function claimsBook()
    {
        $categories = \Modules\Item\Models\Category::get();
        return view('ecommerce::pages_fields.claims_book', compact('categories'));
    }

    /**
     * Verifica si una dirección tiene cobertura de delivery y retorna todas las zonas aplicables.
     * Recopila matches en todos los niveles de especificidad para que el usuario elija:
     *   1. Dpto + Prov + Distrito (coincidencia exacta)
     *   2. Dpto + Prov (province_id definido, district_id nulo en la fila)
     *   3. Solo Dpto (province_id nulo en la fila)
     *   Si hay coincidencias en varios niveles se devuelven todas para que el cliente seleccione.
     */
    public function checkDeliveryZone(Request $request)
    {
        $department = $request->input('department');
        $province   = $request->input('province');
        $district   = $request->input('district');

        if (empty($department)) {
            return response()->json(['found' => false, 'message' => '']);
        }

        // Colección de zonas activas
        $activeZoneIds = DeliveryZone::active()->pluck('id');

        if ($activeZoneIds->isEmpty()) {
            $message = ConfigurationEcommerce::first()?->delivery_no_coverage_message ?? '';
            return response()->json(['found' => false, 'configured' => false, 'message' => $message]);
        }

        $matchedZoneIds = collect();

        // Nivel 1: coincidencia exacta (dpto + prov + distrito)
        if ($district && $province) {
            $ids = DeliveryZoneLocation::whereIn('delivery_zone_id', $activeZoneIds)
                ->where('department_id', $department)
                ->where('province_id', $province)
                ->where('district_id', $district)
                ->pluck('delivery_zone_id');
            $matchedZoneIds = $matchedZoneIds->merge($ids);
        }

        // Nivel 2: coincidencia por dpto + provincia (sin distrito específico)
        if ($province) {
            $ids = DeliveryZoneLocation::whereIn('delivery_zone_id', $activeZoneIds)
                ->where('department_id', $department)
                ->where('province_id', $province)
                ->whereNull('district_id')
                ->pluck('delivery_zone_id');
            $matchedZoneIds = $matchedZoneIds->merge($ids);
        }

        // Nivel 3: cobertura sólo por departamento
        $ids = DeliveryZoneLocation::whereIn('delivery_zone_id', $activeZoneIds)
            ->where('department_id', $department)
            ->whereNull('province_id')
            ->whereNull('district_id')
            ->pluck('delivery_zone_id');
        $matchedZoneIds = $matchedZoneIds->merge($ids);

        $uniqueZoneIds = $matchedZoneIds->unique()->values();

        if ($uniqueZoneIds->isEmpty()) {
            $message = ConfigurationEcommerce::first()?->delivery_no_coverage_message ?? '';
            return response()->json(['found' => false, 'configured' => true, 'message' => $message]);
        }

        // Retornar todas las zonas que hacen match para que el usuario elija
        $zones = DeliveryZone::whereIn('id', $uniqueZoneIds)
            ->get()
            ->map(fn($zone) => [
                'id'    => $zone->id,
                'name'  => $zone->name,
                'price' => $zone->price,
            ])
            ->values();

        return response()->json([
            'found' => true,
            'zones' => $zones,
        ]);
    }

    /**
     * Bloquea compras cuando la tienda está en modo solo cotización.
     */
    private function rejectPurchaseIfQuoteOnly()
    {
        if (! ConfigurationEcommerce::isStorefrontQuoteOnly()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'La tienda está en modo solo cotización. No es posible realizar compras.',
        ], 403);
    }

    /**
     * Normaliza el payload customer del checkout (invitado o autenticado).
     */
    private function extractPaymentCustomerFromRequest(Request $request): array
    {
        $customer = $request->customer;

        if (is_string($customer)) {
            $customer = json_decode($customer, true) ?? [];
        } elseif (is_object($customer)) {
            $customer = (array) $customer;
        } elseif (!is_array($customer)) {
            $customer = [];
        }

        return $customer;
    }

    /**
     * Valida DNI/documento del comprador del pedido (seguimiento público).
     */
    private function orderTrackingDocumentMatches(Order $order, string $document): bool
    {
        if ($document === '') {
            return false;
        }

        $customer = $order->customer;
        $orderDoc = preg_replace(
            '/\D+/',
            '',
            (string) (data_get($customer, 'numero_documento')
                ?? data_get($customer, 'number')
                ?? data_get($customer, 'identity_document_number')
                ?? '')
        );

        return $orderDoc !== '' && hash_equals($orderDoc, $document);
    }

    /**
     * @deprecated Usar orderTrackingDocumentMatches (pedido + DNI).
     */
    private function orderTrackingIdentityMatches(Order $order, string $email, string $document): bool
    {
        if ($document !== '') {
            return $this->orderTrackingDocumentMatches($order, $document);
        }

        $customer = $order->customer;
        $orderEmail = strtolower(trim((string) data_get($customer, 'correo_electronico', '')));
        if ($orderEmail === '') {
            $orderEmail = strtolower(trim((string) data_get($customer, 'email', '')));
        }

        return $email !== '' && $orderEmail !== '' && hash_equals($orderEmail, $email);
    }

    /**
     * Comprueba que el pedido pertenece al cliente autenticado en la tienda.
     */
    private function orderTrackingBelongsToUser(Order $order, $user): bool
    {
        $userEmail = strtolower(trim((string) ($user->email ?? '')));
        if ($userEmail === '') {
            return false;
        }

        $orderEmail = strtolower(trim((string) data_get($order->customer, 'correo_electronico', '')));
        if ($orderEmail === '') {
            $orderEmail = strtolower(trim((string) data_get($order->customer, 'email', '')));
        }

        return $orderEmail !== '' && hash_equals($orderEmail, $userEmail);
    }

    private function isPickupOnlyShippingStatus($status): bool
    {
        $description = mb_strtolower(trim((string) ($status->description ?? '')), 'UTF-8');

        return str_contains($description, 'recojo')
            || str_contains($description, 'pickup')
            || str_contains($description, 'para recoger');
    }

    /**
     * Estado de envío propio de domicilio (p. ej. "En camino").
     */
    private function isDeliveryOnlyShippingStatus($status): bool
    {
        if (! empty($status->action_notify_dispatch)) {
            return true;
        }

        $description = mb_strtolower(trim((string) ($status->description ?? '')), 'UTF-8');

        return str_contains($description, 'en camino')
            || str_contains($description, 'en tránsito')
            || str_contains($description, 'en transito')
            || str_contains($description, 'despachado')
            || str_contains($description, 'en ruta');
    }

    private function isPickupShippingAddress(?string $shippingAddress): bool
    {
        return stripos(trim((string) $shippingAddress), 'Recojo en tienda') === 0;
    }

    private function normalizePaymentCustomerData(array $customer, ?string $shippingAddress = null): array
    {
        $customer['telefono'] = preg_replace('/\D/', '', (string) ($customer['telefono'] ?? ''));

        $docType = (string) ($customer['identity_document_type_id']
            ?? $customer['codigo_tipo_documento_identidad']
            ?? '0');
        $customer['codigo_tipo_documento_identidad'] = $docType;
        $customer['identity_document_type_id'] = $docType;
        $customer['numero_documento'] = preg_replace('/\D/', '', (string) ($customer['numero_documento'] ?? '0')) ?: '0';

        $direccion = trim((string) ($customer['direccion'] ?? ''));
        if ($direccion === '' && $this->isPickupShippingAddress($shippingAddress)) {
            $customer['direccion'] = trim((string) $shippingAddress) ?: 'Recojo en tienda';
        }

        return $customer;
    }

    private function makePaymentCustomerValidator(array $customer, ?string $shippingAddress = null)
    {
        $rules = [
            'telefono' => 'required|numeric',
            'codigo_tipo_documento_identidad' => 'required|numeric',
            'numero_documento' => 'required|numeric',
            'identity_document_type_id' => 'required|numeric',
        ];

        if (!$this->isPickupShippingAddress($shippingAddress)) {
            $rules['direccion'] = 'required|string';
        } else {
            $rules['direccion'] = 'nullable|string';
        }

        return Validator::make($customer, $rules);
    }

    /**
     * Obtiene email y nombre del cliente desde el usuario autenticado o el payload del request (invitado).
     */
    private function resolveOrderCustomerContact(Request $request, $user = null): array
    {
        $customer = $request->customer;
        if (is_string($customer)) {
            $customer = json_decode($customer, true) ?? [];
        } elseif (is_object($customer)) {
            $customer = (array) $customer;
        } elseif (!is_array($customer)) {
            $customer = [];
        }

        $purchase = $request->purchase;
        if (is_string($purchase)) {
            $purchase = json_decode($purchase, true) ?? [];
        } elseif (is_object($purchase)) {
            $purchase = (array) $purchase;
        } elseif (!is_array($purchase)) {
            $purchase = [];
        }

        $purchaseCustomer = $purchase['datos_del_cliente_o_receptor'] ?? [];
        if (is_object($purchaseCustomer)) {
            $purchaseCustomer = (array) $purchaseCustomer;
        } elseif (!is_array($purchaseCustomer)) {
            $purchaseCustomer = [];
        }

        $email = $user?->email
            ?? ($customer['correo_electronico'] ?? null)
            ?? ($customer['email'] ?? null)
            ?? ($purchaseCustomer['correo_electronico'] ?? null);

        $name = $user?->name
            ?? ($customer['apellidos_y_nombres_o_razon_social'] ?? null)
            ?? ($purchaseCustomer['apellidos_y_nombres_o_razon_social'] ?? null)
            ?? 'Cliente';

        return [
            'email' => $email,
            'name' => $name,
        ];
    }

    private function applyAuthoritativeCampaignPrices(array $lines): array
    {
        if (empty($lines)) {
            return [];
        }

        $items = Item::whereIn('id', collect($lines)->pluck('id')->filter()->unique())->get()->keyBy('id');
        $pricingService = app(CampaignPriceService::class);

        return collect($lines)->map(function ($line) use ($items, $pricingService) {
            $line = is_object($line) ? (array) $line : $line;
            $item = $items->get($line['id'] ?? null);
            if (! $item) {
                return $line;
            }

            $pricing = $pricingService->forItem($item);
            $quantity = max(1, (float) ($line['cantidad'] ?? $line['quantity'] ?? 1));
            $line['original_price'] = $pricing['base_price'];
            $line['compare_at_price'] = $pricing['compare_at_price'];
            $line['sale_unit_price'] = $pricing['final_price'];
            $line['sub_total'] = round($pricing['final_price'] * $quantity, 2);
            $line['discount_campaign_id'] = $pricing['discount_campaign_id'];
            $line['discount_campaign_name'] = $pricing['discount_campaign_name'];
            $line['campaign_discount_percent'] = $pricing['real_discount_percentage'];
            $line['campaign_discount_embedded'] = $pricing['has_real_discount'];

            return $line;
        })->values()->all();
    }
}
