<?php
namespace Modules\Ecommerce\Http\Controllers;


use App\Http\Controllers\Tenant\EmailController;
use App\Models\Tenant\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use Culqi\Culqi;
use Culqi\CulqiException;
use Illuminate\Support\Facades\Mail;
use App\Mail\Tenant\CulqiEmail;
use stdClass;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\Order;
use Illuminate\Support\Str;
use App\Models\Tenant\Person;
use Exception;
use App\Models\Tenant\ConfigurationEcommerce;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant\StatusOrder;
use App\Services\Tenant\OrderDocumentFromStatusService;
use Modules\Payment\Models\PaymentConfiguration;



class CulqiController extends Controller
{

    public function __construct()
    {
        // $this->middleware('input.request:document,web', ['only' => ['store']]);
    }

    public function index()
    {

    }

    public function payment(Request $request)
    {
      // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
      if (\App\Models\Tenant\ModelTenant::NATIONAL_CURRENCY_ID === 'VES') {
        return response()->json([
          'success' => false,
          'message' => 'Culqi no está habilitado para cobros en bolívares. Seleccione un medio de pago compatible.',
        ], 422);
      }
      // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########

      if (ConfigurationEcommerce::isStorefrontQuoteOnly()) {
        return response()->json([
          'success' => false,
          'message' => 'La tienda está en modo solo cotización. No es posible realizar compras.',
        ], 403);
      }

      if (! PaymentConfiguration::isCulqiConfigured()) {
        return response()->json([
          'success' => false,
          'message' => 'Culqi está deshabilitado o incompleto en la configuración global de pagos.',
        ], 422);
      }

      try{

        $user = auth('ecommerce')->user();
        $shippingAddress = (string) $request->input('shipping_address', '');
        $customer = $this->extractPaymentCustomerFromRequest($request);
        $purchaseCustomer = $this->extractPurchaseCustomerFromRequest($request);
        $customer = $this->enrichPaymentCustomerData(
            $customer,
            $purchaseCustomer,
            $user,
            $shippingAddress
        );
        $customer = $this->enrichPaymentCustomerFromRequest($request, $customer);
        $customer = $this->normalizePaymentCustomerData($customer, $shippingAddress);

        $rules = [
            'telefono' => 'required|numeric',
            'codigo_tipo_documento_identidad' => 'required|numeric',
            'numero_documento' => 'required|numeric',
            'identity_document_type_id' => 'required|numeric',
        ];

        if ($this->isPickupShippingAddress($shippingAddress)) {
            $rules['direccion'] = 'nullable|string';
        } else {
            $rules['direccion'] = 'required|string';
        }

        $validator = Validator::make($customer, $rules);

        if ($validator->fails()) {
          return response()->json([
            'success' => false,
            'message' => 'Faltan completar campos: ' . implode(', ', array_keys($validator->errors()->toArray())),
            'errors' => $validator->errors(),
          ], 422);
        }


        $configuration = ConfigurationEcommerce::first();
        $paymentConfiguration = PaymentConfiguration::first();

        // Preferir llave secreta global de pagos; fallback al campo legacy de ecommerce.
        $SECRET_API_KEY = $paymentConfiguration->privatekey_culqi
            ?: $configuration->token_private_culqui;
        $PUBLIC_API_KEY = $paymentConfiguration->publickey_culqi
            ?: $configuration->token_public_culqui;

        $chargeAmount = (int) round((float) $request->precio);
        $chargeEmail = $request->email
            ?: ($customer['correo_electronico'] ?? null)
            ?: ($user?->email ?? null);
        $chargeToken = (string) $request->token;
        $chargeInstallments = (int) ($request->installments ?? 0);
        // Culqi usa esta divisa sólo fuera del flujo nacional venezolano,
        // que se bloquea al inicio del método.
        $culqiCurrency = strtoupper('pen');

        Log::info('Culqi: intentando cobro', [
            'amount' => $chargeAmount,
            'amount_raw' => $request->precio,
            'currency' => $culqiCurrency,
            'has_email' => ! empty($chargeEmail),
            'email_domain' => $this->maskEmailDomain($chargeEmail),
            'token_fingerprint' => $this->fingerprintCredential($chargeToken),
            'installments' => $chargeInstallments,
            'public_key' => $this->fingerprintCredential($PUBLIC_API_KEY),
            'secret_key' => $this->fingerprintCredential($SECRET_API_KEY),
            'keys_same_env' => $this->culqiKeysSameEnvironment($PUBLIC_API_KEY, $SECRET_API_KEY),
            'enabled_culqi' => (bool) ($paymentConfiguration->enabled_culqi ?? false),
        ]);

        $culqi = new Culqi(array('api_key' => $SECRET_API_KEY));

        $description = trim((string) ($request->producto ?: 'Compra tienda virtual'));
        if (mb_strlen($description) < 5) {
            $description = 'Compra tienda virtual';
        }
        if (mb_strlen($description) > 80) {
            $description = mb_substr($description, 0, 80);
        }

        $chargePayload = [
            'amount' => $chargeAmount,
            'capture' => true,
            'currency_code' => $culqiCurrency,
            'email' => $chargeEmail,
            'description' => $description,
            'source_id' => $chargeToken,
            'installments' => $chargeInstallments,
        ];

        $antifraud = $this->buildCulqiAntifraudDetails($customer);
        if (! empty($antifraud)) {
            $chargePayload['antifraud_details'] = $antifraud;
        }

        $charge = $culqi->Charges->create($chargePayload);

        $chargeObj = $charge;
        if (is_string($charge)) {
            $chargeObj = json_decode($charge);
        } elseif (is_array($charge)) {
            $chargeObj = json_decode(json_encode($charge));
        }

        // Fuente de verdad: respuesta de Culqi (equivalente al estado del panel: Aprobada / Rechazada).
        $culqiStatus = $this->interpretCulqiCharge($chargeObj);
        if (! $culqiStatus['approved']) {
            Log::warning('Culqi: cobro no aprobado', array_merge($culqiStatus, [
                'culqi_raw' => $this->summarizeCulqiError($chargeObj),
                'public_key' => $this->fingerprintCredential($PUBLIC_API_KEY),
                'secret_key' => $this->fingerprintCredential($SECRET_API_KEY),
                'keys_same_env' => $this->culqiKeysSameEnvironment($PUBLIC_API_KEY, $SECRET_API_KEY),
                'amount' => $chargeAmount,
                'has_email' => ! empty($chargeEmail),
                'token_fingerprint' => $this->fingerprintCredential($chargeToken),
            ]));

            return response()->json([
                'success' => false,
                'message' => $culqiStatus['message'],
                'culqi_status' => $culqiStatus,
            ], 422);
        }

        // Estado inicial de la orden
        $initialStatusId = StatusOrder::resolveInitialOrderStatusId();

        // Estado de pago "Pagado" solo si Culqi confirma venta_exitosa (panel: Aprobada)
        $paidPaymentStatusId = StatusOrder::resolvePaidPaymentStatusId();

        $orderItems = $this->decodeRequestJsonField($request->items);
        $orderPurchase = $this->decodeRequestJsonField($request->purchase);
        if (! is_array($orderPurchase)) {
            $orderPurchase = [];
        }
        $orderPurchase['gateway_payment'] = [
            'provider' => 'culqi',
            'status' => 'approved',
            'panel_status' => 'Aprobada',
            'outcome' => $culqiStatus['outcome'],
            'charge_id' => $culqiStatus['charge_id'],
            'reference_code' => $culqiStatus['reference_code'],
            'amount' => $culqiStatus['amount'],
            'currency' => $culqiStatus['currency'],
            'email' => $request->email,
        ];

        // El front manda is_guest (flujo invitado). No inferir solo por sesión.
        $isGuestOverride = $request->has('is_guest')
            ? filter_var($request->input('is_guest'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : (isset($orderPurchase['checkout']['is_guest'])
                ? (bool) $orderPurchase['checkout']['is_guest']
                : null);
        if ($isGuestOverride === true) {
            $user = null;
        }
        $orderPurchase = Order::attachEcommerceCheckoutMeta($orderPurchase, $user, $isGuestOverride);

        $order = Order::create([
            'external_id' => Str::uuid()->toString(),
            'customer' => $customer,
            'shipping_address' => $shippingAddress,
            'items' => $orderItems,
            'total' => $request->precio_culqi,
            'reference_payment' => 'culqui',
            'status_order_id' => $initialStatusId,
            'payment_status_order_id' => $paidPaymentStatusId,
            'shipping_status_order_id' => StatusOrder::resolveInitialShippingStatusId(),
            'purchase' => $orderPurchase,
        ]);

        // Misma generación de comprobante que al marcar "Pago completado" en admin
        try {
            app(OrderDocumentFromStatusService::class)->afterGatewayPaymentCompleted($order);
        } catch (\Throwable $e) {
            Log::error('Culqi: cobro OK pero falló emitir comprobante del pedido '.$order->id.': '.$e->getMessage());
        }
        $order->refresh();

        try {
            $customer_email = $chargeEmail;
            $document = new stdClass;
            $document->client = $user?->name
                ?? ($customer['apellidos_y_nombres_o_razon_social'] ?? 'Cliente');
            $document->product = $request->producto;
            $document->total = $request->precio_culqi;
            $document->items = is_array($orderItems) ? $orderItems : [];
            $document->id = $order->id;
            $document->order_number = $order->publicNumber();
            $document->tracking_url = route('tenant_ecommerce_order_tracking', [
                'pedido' => $document->order_number,
            ]);

            $email = $customer_email;
            $mailable = new CulqiEmail($document);
            $id = (int) $order->id;
            $model = __FILE__.";;".__LINE__;
            EmailController::SendMail($email, $mailable, $id, $model);
        } catch (\Throwable $e) {
            Log::warning('Culqi: pedido '.$order->id.' creado; fallo al enviar email: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'culqui' => $charge,
            'culqi_status' => $culqiStatus,
            'order' => $order,
        ]);
      }
      catch (CulqiException $e)
      {
          $decoded = json_decode($e->getMessage());
          Log::warning('Culqi: excepción de cobro', [
              'exception_message' => $e->getMessage(),
              'culqi_raw' => $this->summarizeCulqiError($decoded),
          ]);
          $message = 'Su tarjeta fue rechazada. Por favor, intente con otra.';
          if ($decoded && isset($decoded->user_message)) {
              $message = $decoded->user_message;
          }
          return response()->json([
              'success' => false,
              'message' => $message
          ], 422);
      }
      catch (\Exception $e)
      {
          Log::error('Culqi: error inesperado al procesar pago', [
              'message' => $e->getMessage(),
              'exception' => get_class($e),
          ]);
          $message = 'Ocurrió un error al procesar el pago: ' . $e->getMessage();
          $error = json_decode($e->getMessage());
          if ($error && isset($error->user_message)) {
              return response()->json([
                  'success' => false,
                  'message' => $error->user_message
              ], 422);
          }
          return response()->json([
              'success' => false,
              'message' => $message
          ], 400);
      }

    }

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

    private function decodeRequestJsonField($value)
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?? [];
        }

        if (is_array($value)) {
            return $value;
        }

        return [];
    }

    private function extractPurchaseCustomerFromRequest(Request $request): array
    {
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

        return $purchaseCustomer;
    }

    private function isPickupShippingAddress(?string $shippingAddress): bool
    {
        return stripos(trim((string) $shippingAddress), 'Recojo en tienda') === 0;
    }

    /**
     * Completa campos vacíos del customer con purchase / usuario autenticado / shipping_address.
     * No sobrescribe valores que ya vienen del formulario.
     */
    private function enrichPaymentCustomerData(
        array $customer,
        array $purchaseCustomer,
        $user = null,
        ?string $shippingAddress = null
    ): array {
        $fillIfEmpty = function (array &$target, string $key, $value): void {
            $current = trim((string) ($target[$key] ?? ''));
            $value = is_string($value) ? trim($value) : $value;
            if ($current === '' && $value !== null && $value !== '') {
                $target[$key] = $value;
            }
        };

        foreach ([
            'telefono',
            'direccion',
            'correo_electronico',
            'numero_documento',
            'codigo_tipo_documento_identidad',
            'identity_document_type_id',
            'apellidos_y_nombres_o_razon_social',
            'codigo_pais',
            'ubigeo',
        ] as $key) {
            $fillIfEmpty($customer, $key, $purchaseCustomer[$key] ?? null);
        }

        if ($user) {
            $fillIfEmpty($customer, 'telefono', $user->telephone ?? null);
            $fillIfEmpty($customer, 'direccion', $user->address ?? null);
            $fillIfEmpty($customer, 'correo_electronico', $user->email ?? null);
            $fillIfEmpty($customer, 'numero_documento', $user->number ?? null);
            $fillIfEmpty($customer, 'codigo_tipo_documento_identidad', $user->identity_document_type_id ?? null);
            $fillIfEmpty($customer, 'identity_document_type_id', $user->identity_document_type_id ?? null);
            $fillIfEmpty($customer, 'apellidos_y_nombres_o_razon_social', $user->name ?? null);
        }

        $fillIfEmpty($customer, 'direccion', $shippingAddress);

        return $customer;
    }

    /**
     * Respaldo adicional para checkout invitado (email Culqi, shipping, purchase).
     */
    private function enrichPaymentCustomerFromRequest(Request $request, array $customer): array
    {
        $fillIfEmpty = function (array &$target, string $key, $value): void {
            $current = trim((string) ($target[$key] ?? ''));
            $value = is_string($value) ? trim($value) : $value;
            if ($current === '' && $value !== null && $value !== '') {
                $target[$key] = $value;
            }
        };

        $purchaseCustomer = $this->extractPurchaseCustomerFromRequest($request);

        foreach ([
            'telefono',
            'direccion',
            'correo_electronico',
            'numero_documento',
            'codigo_tipo_documento_identidad',
            'identity_document_type_id',
            'apellidos_y_nombres_o_razon_social',
        ] as $key) {
            $fillIfEmpty($customer, $key, $purchaseCustomer[$key] ?? null);
        }

        $fillIfEmpty($customer, 'correo_electronico', $request->input('email'));
        $fillIfEmpty($customer, 'direccion', $request->input('shipping_address'));

        return $customer;
    }

    /**
     * Interpreta la respuesta de Charges->create según criterios del panel Culqi.
     * Solo "venta_exitosa" cuenta como Aprobada → pedido pagado en admin.
     *
     * @param  object|null  $chargeObj
     * @return array{
     *     approved: bool,
     *     message: string,
     *     outcome: string|null,
     *     charge_id: string|null,
     *     reference_code: string|null,
     *     amount: int|null,
     *     currency: string|null,
     *     panel_status: string
     * }
     */
    private function interpretCulqiCharge($chargeObj): array
    {
        $base = [
            'approved' => false,
            'message' => 'Su tarjeta fue rechazada. Por favor, intente con otra.',
            'outcome' => null,
            'charge_id' => null,
            'reference_code' => null,
            'amount' => null,
            'currency' => null,
            'panel_status' => 'Rechazada',
        ];

        if (! is_object($chargeObj)) {
            $base['message'] = 'Culqi no devolvió una respuesta válida del cobro.';

            return $base;
        }

        $base['charge_id'] = isset($chargeObj->id) ? (string) $chargeObj->id : null;
        $base['reference_code'] = isset($chargeObj->reference_code) ? (string) $chargeObj->reference_code : null;
        $base['amount'] = isset($chargeObj->amount) ? (int) $chargeObj->amount : null;
        $base['currency'] = isset($chargeObj->currency_code) ? (string) $chargeObj->currency_code : null;

        if (isset($chargeObj->object) && $chargeObj->object === 'error') {
            $type = (string) ($chargeObj->type ?? 'error');
            $userMessage = (string) ($chargeObj->user_message ?? '');
            $merchantMessage = (string) ($chargeObj->merchant_message ?? '');

            if ($type === 'api_error') {
                $base['message'] = 'Culqi no pudo procesar el cobro en este momento (error interno de Culqi). '
                    . 'Verifica tus llaves de prueba en el panel, usa una tarjeta de prueba oficial e inténtalo de nuevo. '
                    . 'Si persiste, revisa el estado del servicio en Culqi o contacta a culqi.com/soporte.';
            } else {
                $base['message'] = $userMessage !== ''
                    ? $userMessage
                    : ($merchantMessage !== '' ? $merchantMessage : $base['message']);
            }
            $base['outcome'] = $type;

            return $base;
        }

        $outcomeType = data_get($chargeObj, 'outcome.type');
        $base['outcome'] = $outcomeType ? (string) $outcomeType : null;

        if ($outcomeType === 'venta_exitosa') {
            return array_merge($base, [
                'approved' => true,
                'message' => data_get($chargeObj, 'outcome.user_message') ?: 'Pago aprobado por Culqi.',
                'panel_status' => 'Aprobada',
            ]);
        }

        if ($outcomeType) {
            $base['message'] = data_get($chargeObj, 'outcome.user_message')
                ?: data_get($chargeObj, 'outcome.merchant_message')
                ?: $base['message'];

            return $base;
        }

        // Sin outcome no asumimos aprobación (evita marcar pagado sin confirmación del panel).
        $base['message'] = 'Culqi no confirmó la venta como exitosa. Intente nuevamente o use otra tarjeta.';

        return $base;
    }

    private function normalizePaymentCustomerData(array $customer, ?string $shippingAddress = null): array
    {
        $customer['telefono'] = preg_replace('/\D/', '', (string) ($customer['telefono'] ?? ''));

        $docType = trim((string) ($customer['identity_document_type_id']
            ?? $customer['codigo_tipo_documento_identidad']
            ?? '0'));
        if ($docType === '') {
            $docType = '0';
        }
        $customer['codigo_tipo_documento_identidad'] = $docType;
        $customer['identity_document_type_id'] = $docType;
        $customer['numero_documento'] = preg_replace('/\D/', '', (string) ($customer['numero_documento'] ?? '0')) ?: '0';

        $direccion = trim((string) ($customer['direccion'] ?? ''));
        if ($direccion === '' && trim((string) $shippingAddress) !== '') {
            // Entrega a domicilio y recojo: reutilizar shipping_address si direccion viene vacía.
            $customer['direccion'] = trim((string) $shippingAddress);
        } elseif ($direccion === '' && $this->isPickupShippingAddress($shippingAddress)) {
            $customer['direccion'] = 'Recojo en tienda';
        } else {
            $customer['direccion'] = $direccion;
        }

        return $customer;
    }

    /**
     * Huella segura de credencial Culqi (prefijo + últimos 4), sin exponer el secreto completo.
     */
    private function fingerprintCredential(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $prefix = substr($value, 0, 8);
        $suffix = substr($value, -4);

        return $prefix.'…'.$suffix.' (len='.strlen($value).')';
    }

    private function maskEmailDomain(?string $email): ?string
    {
        $email = trim((string) $email);
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        $parts = explode('@', $email, 2);

        return '*@'.$parts[1];
    }

    private function culqiKeysSameEnvironment(?string $publicKey, ?string $secretKey): ?bool
    {
        $publicKey = trim((string) $publicKey);
        $secretKey = trim((string) $secretKey);
        if ($publicKey === '' || $secretKey === '') {
            return null;
        }

        $publicIsTest = str_contains($publicKey, '_test_');
        $publicIsLive = str_contains($publicKey, '_live_');
        $secretIsTest = str_contains($secretKey, '_test_');
        $secretIsLive = str_contains($secretKey, '_live_');

        if (($publicIsTest || $publicIsLive) && ($secretIsTest || $secretIsLive)) {
            return ($publicIsTest && $secretIsTest) || ($publicIsLive && $secretIsLive);
        }

        return null;
    }

    /**
     * Resume el objeto error/respuesta Culqi para diagnóstico.
     *
     * @param  mixed  $chargeObj
     */
    private function summarizeCulqiError($chargeObj): array
    {
        if (! is_object($chargeObj)) {
            return [
                'valid_object' => false,
                'type' => gettype($chargeObj),
            ];
        }

        return [
            'valid_object' => true,
            'object' => $chargeObj->object ?? null,
            'type' => $chargeObj->type ?? data_get($chargeObj, 'outcome.type'),
            'code' => $chargeObj->code ?? null,
            'decline_code' => $chargeObj->decline_code ?? data_get($chargeObj, 'outcome.decline_code'),
            'param' => $chargeObj->param ?? null,
            'user_message' => $chargeObj->user_message ?? data_get($chargeObj, 'outcome.user_message'),
            'merchant_message' => $chargeObj->merchant_message ?? data_get($chargeObj, 'outcome.merchant_message'),
            'charge_id' => isset($chargeObj->id) ? (string) $chargeObj->id : null,
        ];
    }

    /**
     * Datos antifraude opcionales recomendados por Culqi al crear un cargo.
     */
    private function buildCulqiAntifraudDetails(array $customer): array
    {
        $fullName = trim((string) ($customer['apellidos_y_nombres_o_razon_social'] ?? ''));
        $firstName = 'Cliente';
        $lastName = 'Ecommerce';
        if ($fullName !== '') {
            $parts = preg_split('/\s+/', $fullName) ?: [];
            if (count($parts) === 1) {
                $firstName = $parts[0];
            } elseif (count($parts) > 1) {
                $firstName = array_shift($parts);
                $lastName = implode(' ', $parts);
            }
        }

        $phone = preg_replace('/\D+/', '', (string) ($customer['telefono'] ?? ''));
        $address = trim((string) ($customer['direccion'] ?? ''));
        if (mb_strlen($address) > 100) {
            $address = mb_substr($address, 0, 100);
        }

        $details = [
            'first_name' => mb_substr($firstName, 0, 50) ?: 'Cliente',
            'last_name' => mb_substr($lastName, 0, 50) ?: 'Ecommerce',
            'country_code' => $customer['codigo_pais'] ?? 'VE',
        ];

        if ($address !== '') {
            $details['address'] = $address;
            $details['address_city'] = 'LIMA';
        }

        if ($phone !== '' && strlen($phone) >= 6) {
            $details['phone_number'] = $phone;
        }

        return $details;
    }

}
