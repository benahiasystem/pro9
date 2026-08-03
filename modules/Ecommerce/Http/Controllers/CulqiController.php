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

        $customer = (array)json_decode($request->customer, true);
        $shippingAddress = (string) $request->input('shipping_address', '');
        $customer = $this->normalizePaymentCustomerData($customer, $shippingAddress);

        $rules = [
            'telefono' => 'required|numeric',
            'codigo_tipo_documento_identidad' => 'required|numeric',
            'numero_documento' => 'required|numeric',
            'identity_document_type_id' => 'required|numeric',
        ];

        if (stripos(trim($shippingAddress), 'Recojo en tienda') !== 0) {
            $rules['direccion'] = 'required|string';
        } else {
            $rules['direccion'] = 'nullable|string';
        }

        $validator = Validator::make($customer, $rules);

        if ($validator->fails()) {
          return response()->json($validator->errors(), 422);
        }


        $user = auth('ecommerce')->user();
        $configuration = ConfigurationEcommerce::first();


        $SECRET_API_KEY = $configuration->token_private_culqui;

        $culqi = new Culqi(array('api_key' => $SECRET_API_KEY));

        $charge = $culqi->Charges->create(
            array(
                "amount" => $request->precio,
                "currency_code" => "PEN",
                "email" => $request->email,
                "description" =>  $request->producto,
                "source_id" => $request->token,
               //  "metadata" => array (
               //      "ruc" => $_POST['ruc'],
               //      "contacto" => $_POST['contacto'],
               //      "telefono" => $_POST['telefono']),
                "installments" => $request->installments
              )
        );

        $chargeObj = $charge;
        if (is_string($charge)) {
            $chargeObj = json_decode($charge);
        } elseif (is_array($charge)) {
            $chargeObj = json_decode(json_encode($charge));
        }

        // Validar si Culqi devuelve explícitamente un objeto de error (tarjeta rechazada)
        if (isset($chargeObj->object) && $chargeObj->object === 'error') {
            return response()->json([
                'success' => false,
                'message' => $chargeObj->user_message ?? 'Su tarjeta fue rechazada. Por favor, intente con otra.'
            ], 422);
        }

        // Validar si Culqi responde con éxito a nivel de conexión pero el estado no es exitoso
        if (isset($chargeObj->outcome) && $chargeObj->outcome->type !== 'venta_exitosa') {
            return response()->json([
                'success' => false,
                'message' => $chargeObj->outcome->user_message ?? 'Su tarjeta fue rechazada. Por favor, intente con otra.'
            ], 422);
        }

        // Estado inicial de la orden
        $initialStatusId = StatusOrder::resolveInitialOrderStatusId();

        // Estado de pago "Pagado" (action_mark_payment = true)
        $paidPaymentStatusId = StatusOrder::resolvePaidPaymentStatusId();

        $order = Order::create([
            'external_id' => Str::uuid()->toString(),
            'customer' => $customer,
            'shipping_address' => $shippingAddress,
            'items' => json_decode( $request->items ),
            'total' => $request->precio_culqi,
            'reference_payment' => 'culqui',
            'status_order_id' => $initialStatusId,
            'payment_status_order_id' => $paidPaymentStatusId,
            'purchase' => json_decode($request->purchase)
        ]);

        // Misma generación de comprobante que al marcar "Pago completado" en admin
        app(OrderDocumentFromStatusService::class)->afterGatewayPaymentCompleted($order);
        $order->refresh();

        $customer_email = $request->email;
        $document = new stdClass;
        $document->client = $user?->name
            ?? ($customer['apellidos_y_nombres_o_razon_social'] ?? 'Cliente');
        $document->product = $request->producto;
        $document->total = $request->precio_culqi;
        $document->items = json_decode($request->items, true);

        $email = $customer_email;
        $mailable = new CulqiEmail($document);
        $id = (int) $request->id;
        $model = __FILE__.";;".__LINE__;
        $sendIt = EmailController::SendMail($email, $mailable, $id, $model);

        return [
            'success' => true,
            'culqui' => $charge,
            'order' => $order,
        ];
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
        if ($direccion === '' && stripos(trim((string) $shippingAddress), 'Recojo en tienda') === 0) {
            $customer['direccion'] = trim((string) $shippingAddress) ?: 'Recojo en tienda';
        }

        return $customer;
    }

}
