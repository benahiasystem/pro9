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

        $culqi = new Culqi(array('api_key' => $SECRET_API_KEY));

        $chargeEmail = $request->email
            ?: ($customer['correo_electronico'] ?? null)
            ?: ($user?->email ?? null);

        $charge = $culqi->Charges->create(
            array(
                "amount" => $request->precio,
                "currency_code" => "PEN",
                "email" => $chargeEmail,
                "description" =>  $request->producto,
                "source_id" => $request->token,
                "installments" => (int) ($request->installments ?? 0)
              )
        );

        $chargeObj = $charge;
        if (is_string($charge)) {
            $chargeObj = json_decode($charge);
        } elseif (is_array($charge)) {
            $chargeObj = json_decode(json_encode($charge));
        }

        // Fuente de verdad: respuesta de Culqi (equivalente al estado del panel: Aprobada / Rechazada).
        $culqiStatus = $this->interpretCulqiCharge($chargeObj);
        if (! $culqiStatus['approved']) {
            Log::warning('Culqi: cobro no aprobado', $culqiStatus);

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
        app(OrderDocumentFromStatusService::class)->afterGatewayPaymentCompleted($order);
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
            $document->order_number = str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
            $document->tracking_token = (string) $order->external_id;
            $document->tracking_url = route('tenant_ecommerce_order_tracking', [
                'pedido' => $document->order_number,
                'token' => $order->external_id,
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
          $message = 'Su tarjeta fue rechazada. Por favor, intente con otra.';
          $error = json_decode($e->getMessage());
          if ($error && isset($error->user_message)) {
              $message = $error->user_message;
          }
          return response()->json([
              'success' => false,
              'message' => $message
          ], 422);
      }
      catch (\Exception $e)
      {
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
            $base['message'] = $chargeObj->user_message
                ?? $chargeObj->merchant_message
                ?? $base['message'];
            $base['outcome'] = $chargeObj->type ?? 'error';

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

}
