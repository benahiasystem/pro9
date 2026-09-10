<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\EmailController;
use App\Http\Controllers\Tenant\QuotationController;
use App\Mail\Tenant\QuotationEmail;
use App\Models\Tenant\Company;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\ExchangeRate;
use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use App\Models\Tenant\Quotation;
use App\Models\Tenant\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class QuotationStorefrontController extends Controller
{
    public function __construct()
    {
        // Misma data compartida que EcommerceController para layouts Porto
        view()->share('records', Item::where('apply_store', 1)->orderBy('id', 'DESC')->take(2)->get());
        $companyModel = Company::first();
        view()->share(
            'ecommerceDescription',
            EcommerceController::getEcommerceDescription($companyModel)
        );
        view()->share('storefront_show_prices', ConfigurationEcommerce::storefrontShowsPrices());
    }

    /**
     * Vista "Mis cotizaciones" en la cuenta del cliente.
     */
    public function index()
    {
        if (! auth('ecommerce')->user()) {
            return redirect('ecommerce');
        }

        if (! $this->quotationsAreEnabled()) {
            return redirect()->route('tenant.ecommerce.index');
        }

        $configuration = ConfigurationEcommerce::first();
        $categories = \Modules\Item\Models\Category::has('items')->get();
        $quotationShowPrices = ConfigurationEcommerce::storefrontQuotationConfig()['show_prices'];

        return view('ecommerce::document_list.quotation', compact(
            'configuration',
            'categories',
            'quotationShowPrices'
        ));
    }

    /**
     * Listado paginado de cotizaciones del Person autenticado.
     */
    public function records(Request $request)
    {
        $user = auth('ecommerce')->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No autenticado.'], 401);
        }

        if (! $this->quotationsAreEnabled()) {
            return $this->quotationsDisabledResponse();
        }

        $query = Quotation::where('customer_id', $user->id)
            ->whereSourceEcommerce()
            ->with(['state_type'])
            ->orderByDesc('id');

        if ($request->filled('date_of_start') || $request->filled('date_of_end')) {
            $start = $request->input('date_of_start', date('Y-m-d'));
            $end = $request->input('date_of_end', date('Y-m-d'));
            $query->whereBetween('date_of_issue', [$start, $end]);
        }

        if ($request->filled('state_type_id') && $request->state_type_id !== 'all') {
            $query->where('state_type_id', $request->state_type_id);
        }

        $paginator = $query->paginate(config('tenant.items_per_page', 10));
        $showPrices = ConfigurationEcommerce::storefrontQuotationConfig()['show_prices'];

        $rows = collect($paginator->items())->map(function (Quotation $quotation) use ($showPrices) {
            $isExpired = $quotation->state_type_id !== '11'
                && $quotation->date_of_due
                && Carbon::parse($quotation->date_of_due)->endOfDay()->lt(now());

            return [
                'id' => $quotation->id,
                'external_id' => $quotation->external_id,
                'number_full' => $quotation->number_full,
                'code' => $quotation->identifier,
                'date_of_issue' => optional($quotation->date_of_issue)->format('Y-m-d'),
                'date_of_due' => $quotation->date_of_due
                    ? Carbon::parse($quotation->date_of_due)->format('Y-m-d')
                    : null,
                'total' => $showPrices ? (float) $quotation->total : null,
                'currency_type_id' => $quotation->currency_type_id,
                'state_type_id' => $quotation->state_type_id,
                'is_expired' => $isExpired,
                'state_type_description' => $isExpired
                    ? 'Vencida'
                    : (optional($quotation->state_type)->description ?? 'Registrado'),
                'print_url' => $showPrices
                    ? url("quotations/print/{$quotation->external_id}/a4")
                    : null,
                'items_count' => $quotation->items()->count(),
                'show_prices' => $showPrices,
            ];
        });

        return [
            'success' => true,
            'data' => $rows,
            'show_prices' => $showPrices,
            'links' => $paginator->toArray(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Detalle de una cotización del cliente autenticado (modal Mis cotizaciones).
     */
    public function show($id)
    {
        $user = auth('ecommerce')->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No autenticado.'], 401);
        }

        if (! $this->quotationsAreEnabled()) {
            return $this->quotationsDisabledResponse();
        }

        $quotation = Quotation::where('customer_id', $user->id)
            ->whereSourceEcommerce()
            ->with(['state_type', 'items'])
            ->find($id);

        if (! $quotation) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada.'], 404);
        }

        $isExpired = $quotation->state_type_id !== '11'
            && $quotation->date_of_due
            && Carbon::parse($quotation->date_of_due)->endOfDay()->lt(now());

        $customer = $quotation->customer;
        $itemIds = $quotation->items->pluck('item_id')->filter()->unique()->values();
        $imagesById = Item::whereIn('id', $itemIds)->pluck('image', 'id');

        $showPrices = ConfigurationEcommerce::storefrontQuotationConfig()['show_prices'];

        $items = $quotation->items->map(function ($row) use ($imagesById, $showPrices) {
            $snapshot = is_object($row->item) ? $row->item : (object) [];
            $image = $imagesById[$row->item_id] ?? 'imagen-no-disponible.jpg';

            return [
                'description' => $snapshot->description
                    ?? $snapshot->name
                    ?? $row->name_product_pdf
                    ?? 'Producto',
                'quantity' => (float) $row->quantity,
                'unit_price' => $showPrices ? (float) $row->unit_price : null,
                'total' => $showPrices ? (float) $row->total : null,
                'image' => $image,
                'currency_symbol' => 'Bs.',
            ];
        })->values();

        return [
            'success' => true,
            'data' => [
                'id' => $quotation->id,
                'code' => $quotation->identifier,
                'customer_name' => $customer->name ?? $user->name,
                'customer_telephone' => $customer->telephone ?? $user->telephone,
                'customer_email' => $customer->email ?? $user->email,
                'customer_address' => $customer->address ?? null,
                'date_of_issue' => optional($quotation->date_of_issue)->format('Y-m-d H:i:s'),
                'date_of_due' => $quotation->date_of_due
                    ? Carbon::parse($quotation->date_of_due)->format('Y-m-d')
                    : null,
                'contact' => $quotation->contact,
                'phone' => $quotation->phone,
                'description' => $quotation->description,
                'state_type_id' => $quotation->state_type_id,
                'is_expired' => $isExpired,
                'state_type_description' => $isExpired
                    ? 'Vencida'
                    : (optional($quotation->state_type)->description ?? 'Registrado'),
                'total' => $showPrices ? (float) $quotation->total : null,
                'currency_symbol' => 'Bs.',
                'print_url' => $showPrices
                    ? url("quotations/print/{$quotation->external_id}/a4")
                    : null,
                'show_prices' => $showPrices,
                'items' => $items,
            ],
        ];
    }

    /**
     * Crea una cotización oficial desde el carrito de la tienda.
     */
    public function store(Request $request)
    {
        if (! $this->quotationsAreEnabled()) {
            return $this->quotationsDisabledResponse();
        }

        $authUser = auth('ecommerce')->user();
        $isGuest = ! $authUser;

        // Invitados: contacto obligatorio. Logueados: se completa desde la sesión.
        $rules = [
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:2000',
        ];

        if ($isGuest) {
            $rules['contact_name'] = 'required|string|max:255';
            $rules['email'] = 'required|email|max:255';
            $rules['telephone'] = 'required|string|max:30';
        } else {
            $rules['contact_name'] = 'nullable|string|max:255';
            $rules['email'] = 'nullable|email|max:255';
            $rules['telephone'] = 'nullable|string|max:30';
        }

        $validator = Validator::make($request->all(), $rules, [
            'contact_name.required' => 'El nombre de contacto es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingresa un correo válido.',
            'telephone.required' => 'El teléfono es obligatorio.',
            'items.required' => 'Agrega al menos un producto para cotizar.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Revisa los datos de la cotización.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $quotationSettings = ConfigurationEcommerce::storefrontQuotationConfig();
        $validityDays = $quotationSettings['validity_days'];

        try {
            $quotation = null;
            $customer = null;

            DB::connection('tenant')->transaction(function () use ($request, $authUser, $isGuest, &$quotation, &$customer, $quotationSettings, $validityDays) {
                if ($isGuest) {
                    $customer = $this->findOrCreateGuestQuotationCustomer(
                        trim((string) $request->input('contact_name')),
                        trim((string) $request->input('email')),
                        trim((string) $request->input('telephone'))
                    );
                } else {
                    $customer = $authUser;
                    $this->syncPersonContact($customer, $request);
                }

                $establishment = Establishment::first();
                if (! $establishment) {
                    throw new Exception('No hay un establecimiento configurado para generar la cotización.');
                }

                $staffUser = User::where('type', 'admin')->orderBy('id')->first()
                    ?? User::orderBy('id')->first();
                if (! $staffUser) {
                    throw new Exception('No hay un usuario del sistema para registrar la cotización.');
                }

                $company = Company::active();
                $exchangeRate = $this->resolveExchangeRate();
                $dateOfIssue = Carbon::now();
                $dateOfDue = $dateOfIssue->copy()->addDays($validityDays);

                // show_prices=false → montos en 0 (definición comercial en backoffice)
                $built = $this->buildItemsAndTotals(
                    $request->input('items'),
                    $exchangeRate,
                    (bool) $quotationSettings['show_prices']
                );
                if (count($built['items']) < 1) {
                    throw new Exception('No se encontraron productos válidos en el carrito.');
                }

                $contactName = trim((string) ($request->input('contact_name') ?: $customer->name));
                $telephone = trim((string) ($request->input('telephone') ?: $customer->telephone));
                $email = trim((string) ($request->input('email') ?: $customer->email));
                $notes = trim((string) $request->input('notes', ''));

                if ($contactName === '' || $email === '' || $telephone === '') {
                    throw new Exception('Completa nombre, correo y teléfono para registrar la cotización.');
                }

                $descriptionParts = array_filter([
                    'Cotización solicitada desde la tienda virtual.',
                    'Vigencia: '.$validityDays.' día'.($validityDays === 1 ? '' : 's').'.',
                    $notes !== '' ? 'Notas del cliente: '.$notes : null,
                ]);

                $tenantTerms = Configuration::select('terms_condition')->first();
                $quotationTerms = trim((string) ($quotationSettings['terms'] ?? ''));
                $termsCondition = $quotationTerms !== ''
                    ? $quotationTerms
                    : ($tenantTerms->terms_condition ?? null);

                $data = [
                    'user_id' => $staffUser->id,
                    'external_id' => Str::uuid()->toString(),
                    'establishment_id' => $establishment->id,
                    'establishment' => EstablishmentInput::set($establishment->id),
                    'fiscal_environment' => $company->fiscal_environment,
                    'state_type_id' => '01',
                    'prefix' => Quotation::SERIES_STANDARD,
                    'series' => '',
                    'number' => 0,
                    'date_of_issue' => $dateOfIssue->format('Y-m-d'),
                    'time_of_issue' => $dateOfIssue->format('H:i:s'),
                    'date_of_due' => $dateOfDue->format('Y-m-d'),
                    'delivery_date' => null,
                    'customer_id' => $customer->id,
                    'customer' => PersonInput::set($customer->id),
                    'currency_type_id' => 'VES',
                    'exchange_rate_sale' => $exchangeRate,
                    'total_prepayment' => 0,
                    'total_discount' => 0,
                    'total_charge' => 0,
                    'total_exportation' => 0,
                    'total_free' => 0,
                    'total_taxed' => $built['totals']['total_taxed'],
                    'total_unaffected' => $built['totals']['total_unaffected'],
                    'total_exonerated' => $built['totals']['total_exonerated'],
                    'total_igv' => $built['totals']['total_igv'],
                    'total_base_other_taxes' => 0,
                    'total_other_taxes' => 0,
                    'total_taxes' => $built['totals']['total_igv'],
                    'total_value' => $built['totals']['total_value'],
                    'subtotal' => $built['totals']['total_value'],
                    'total' => $built['totals']['total'],
                    'charges' => [],
                    'discounts' => [],
                    'prepayments' => [],
                    'guides' => [],
                    'related' => [],
                    'perception' => null,
                    'legends' => [],
                    'shipping_address' => null,
                    'description' => implode("\n", $descriptionParts),
                    'referential_information' => 'Tienda virtual',
                    'source' => Quotation::SOURCE_ECOMMERCE,
                    'contact' => $contactName,
                    'phone' => $telephone,
                    'terms_condition' => $termsCondition,
                    'payment_method_type_id' => '01',
                    'changed' => false,
                ];

                // Actualiza snapshot de cliente con datos del formulario si vienen
                if (is_array($data['customer'])) {
                    if ($email !== '') {
                        $data['customer']['email'] = $email;
                    }
                    if ($telephone !== '') {
                        $data['customer']['telephone'] = $telephone;
                    }
                    if ($contactName !== '') {
                        $data['customer']['name'] = $contactName;
                    }
                }

                $quotation = Quotation::create($data);

                foreach ($built['items'] as $row) {
                    $quotation->items()->create($row);
                }

                // Mismo motor de filename que cotizaciones de empresa: COT-{id}-{Ymd}
                $quotation->filename = join('-', [
                    $quotation->prefix ?: Quotation::SERIES_STANDARD,
                    $quotation->id,
                    $dateOfIssue->format('Ymd'),
                ]);
                $quotation->save();

                try {
                    app(QuotationController::class)->createPdf($quotation, 'a4', $quotation->filename);
                } catch (Exception $pdfError) {
                    Log::warning('No se pudo generar el PDF de cotización ecommerce', [
                        'quotation_id' => $quotation->id,
                        'error' => $pdfError->getMessage(),
                    ]);
                }

                $this->notifyCustomerQuotationEmail($quotation, $email);
            });

            $quotation->load('state_type');

            $listUrl = $isGuest
                ? url('/ecommerce')
                : route('tenant_ecommerce_quotation_list');

            return response()->json([
                'success' => true,
                'message' => 'Cotización registrada correctamente.',
                'quotation' => [
                    'id' => $quotation->id,
                    'external_id' => $quotation->external_id,
                    'number_full' => $quotation->number_full,
                    'code' => $quotation->identifier,
                    'total' => $quotationSettings['show_prices'] ? (float) $quotation->total : null,
                    'date_of_due' => $quotation->date_of_due
                        ? Carbon::parse($quotation->date_of_due)->format('Y-m-d')
                        : null,
                    'validity_days' => $validityDays,
                    'state_type_description' => optional($quotation->state_type)->description ?? 'Registrado',
                    'print_url' => $quotationSettings['show_prices']
                        ? url("quotations/print/{$quotation->external_id}/a4")
                        : null,
                    'list_url' => $listUrl,
                    'is_guest' => $isGuest,
                    'success_message' => $quotationSettings['success_message'],
                    'show_prices' => $quotationSettings['show_prices'],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error al crear cotización desde tienda', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'No se pudo registrar la cotización.',
            ], 422);
        }
    }

    /**
     * Reutiliza un cliente existente por email o crea un Person temporal sin contraseña.
     */
    private function findOrCreateGuestQuotationCustomer(string $name, string $email, string $telephone): Person
    {
        $email = strtolower(trim($email));
        $name = trim($name);
        $telephone = trim($telephone);

        $person = Person::where('type', 'customers')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderBy('id')
            ->first();

        if ($person) {
            $dirty = false;
            if ($name !== '' && (string) $person->name !== $name) {
                // Solo actualizar nombre si el registro parece temporal (sin password / doc genérico)
                if (empty($person->password) || (string) $person->identity_document_type_id === '0') {
                    $person->name = $name;
                    $dirty = true;
                }
            }
            if ($telephone !== '' && (string) $person->telephone !== $telephone) {
                $person->telephone = $telephone;
                $dirty = true;
            }
            if ($dirty) {
                $person->save();
            }

            return $person;
        }

        $number = $this->generateGuestCustomerNumber($email);

        $person = new Person();
        $person->type = 'customers';
        $person->identity_document_type_id = '0';
        $person->number = $number;
        $person->name = $name;
        // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
        $person->country_id = 'VE';
        $person->nationality_id = 'VE';
        // ######## FIN CAMBIO GEOPOLITICO VENEZUELA
        $person->establishment_code = '0000';
        $person->email = $email;
        $person->telephone = $telephone;
        $person->password = null;
        $person->save();

        return $person;
    }

    private function generateGuestCustomerNumber(string $email): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = 'G'.strtoupper(substr(md5($email.'|'.microtime(true).'|'.$attempt), 0, 10));
            $exists = Person::where('type', 'customers')->where('number', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        return 'G'.strtoupper(Str::random(10));
    }

    private function notifyCustomerQuotationEmail(Quotation $quotation, string $email): void
    {
        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $company = Company::active();
            $mailable = new QuotationEmail($company, $quotation);
            EmailController::SendMail($email, $mailable, $quotation->id, 3);
        } catch (Exception $e) {
            Log::warning('No se pudo enviar email de cotización ecommerce', [
                'quotation_id' => $quotation->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncPersonContact($user, Request $request): void
    {
        $dirty = false;

        if ($request->filled('telephone') && $request->telephone !== $user->telephone) {
            $user->telephone = $request->telephone;
            $dirty = true;
        }

        if ($request->filled('email') && $request->email !== $user->email) {
            // No sobrescribir el email de login; solo se usa en el snapshot de la cotización
        }

        if ($dirty) {
            $user->save();
        }
    }

    private function resolveExchangeRate(): float
    {
        $rate = ExchangeRate::where('date', date('Y-m-d'))->first();
        if ($rate && (float) $rate->sale > 0) {
            return (float) $rate->sale;
        }

        $latest = ExchangeRate::orderByDesc('date')->first();
        if ($latest && (float) $latest->sale > 0) {
            return (float) $latest->sale;
        }

        return 1.0;
    }

    /**
     * Construye QuotationItems y totales a partir del carrito.
     */
    /**
     * @param  bool  $persistPrices  Si false (cotización sin precios en tienda), guarda montos en 0
     *                               y deja suggested_unit_price en el JSON del ítem para el admin.
     */
    private function buildItemsAndTotals(array $cartItems, float $exchangeRate, bool $persistPrices = true): array
    {
        $percentageIgv = 18.0;
        $rows = [];
        $totals = [
            'total_taxed' => 0.0,
            'total_exonerated' => 0.0,
            'total_unaffected' => 0.0,
            'total_igv' => 0.0,
            'total_value' => 0.0,
            'total' => 0.0,
        ];

        foreach ($cartItems as $cartRow) {
            $itemId = (int) ($cartRow['item_id'] ?? 0);
            $quantity = (float) ($cartRow['quantity'] ?? 0);
            if ($itemId < 1 || $quantity <= 0) {
                continue;
            }

            $item = Item::with(['unit_type', 'brand'])->where('id', $itemId)->where('apply_store', 1)->first();
            if (! $item) {
                continue;
            }

            $affectation = $item->sale_affectation_igv_type_id ?: '10';
            $catalogUnitPrice = (float) $item->sale_unit_price;
            if ($item->currency_type_id === 'USD') {
                $catalogUnitPrice = round($catalogUnitPrice * $exchangeRate, 2);
            }

            // Sin precios visibles: cotización entra a $0 para definición comercial en backoffice.
            $unitPrice = $persistPrices ? $catalogUnitPrice : 0.0;

            if ($affectation === '10') {
                $unitValue = $unitPrice > 0
                    ? round($unitPrice / (1 + ($percentageIgv / 100)), 6)
                    : 0.0;
                $totalValue = round($unitValue * $quantity, 2);
                $totalIgv = round(($unitPrice * $quantity) - $totalValue, 2);
                $total = round($unitPrice * $quantity, 2);

                $totals['total_taxed'] += $totalValue;
                $totals['total_igv'] += $totalIgv;
            } else {
                // 20 exonerado, 30 inafecto u otros: sin IGV
                $unitValue = round($unitPrice, 6);
                $totalValue = round($unitValue * $quantity, 2);
                $totalIgv = 0.0;
                $total = $totalValue;

                if ($affectation === '20') {
                    $totals['total_exonerated'] += $totalValue;
                } else {
                    $totals['total_unaffected'] += $totalValue;
                }
            }

            $totals['total_value'] += $totalValue;
            $totals['total'] += $total;

            $brandName = null;
            if ($item->relationLoaded('brand') && $item->brand) {
                $brandName = $item->brand->name ?? $item->brand->description ?? null;
            }

            $rows[] = [
                'item_id' => $item->id,
                'item' => [
                    'id' => $item->id,
                    'item_id' => $item->id,
                    'description' => $item->description,
                    'name' => $item->name,
                    'internal_id' => $item->internal_id,
                    'unit_type_id' => $item->unit_type_id,
                    'unit_type' => [
                        'id' => $item->unit_type_id,
                        'description' => optional($item->unit_type)->description,
                    ],
                    'currency_type_id' => 'VES',
                    'sale_unit_price' => $catalogUnitPrice,
                    'unit_price' => $unitPrice,
                    'suggested_unit_price' => $catalogUnitPrice,
                    'sale_affectation_igv_type_id' => $affectation,
                    'is_set' => (int) ($item->is_set ?? 0),
                    'model' => $item->model,
                    'brand' => $brandName,
                    'presentation' => null,
                    'extra_attr_name' => null,
                    'extra_attr_value' => null,
                    'used_points_for_exchange' => false,
                    'prices_pending' => ! $persistPrices,
                ],
                'quantity' => $quantity,
                'unit_value' => $unitValue,
                'affectation_igv_type_id' => $affectation,
                'total_base_igv' => $totalValue,
                'percentage_igv' => $percentageIgv,
                'total_igv' => $totalIgv,
                'total_base_other_taxes' => 0,
                'percentage_other_taxes' => 0,
                'total_other_taxes' => 0,
                'total_taxes' => $totalIgv,
                'price_type_id' => '01',
                'unit_price' => $unitPrice,
                'total_value' => $totalValue,
                'total_charge' => 0,
                'total_discount' => 0,
                'total' => $total,
                'attributes' => [],
                'charges' => [],
                'discounts' => [],
                'warehouse_id' => null,
                'name_product_pdf' => $item->description,
            ];
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return [
            'items' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * Cotizaciones habilitadas en la configuración de la tienda virtual.
     */
    private function quotationsAreEnabled(): bool
    {
        return ConfigurationEcommerce::storefrontQuotationConfig()['enabled'];
    }

    /**
     * Respuesta JSON cuando el cotizador está desactivado.
     */
    private function quotationsDisabledResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Las cotizaciones no están disponibles en la tienda.',
        ], 403);
    }
}
