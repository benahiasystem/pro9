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
      try{

        $customer = (array)json_decode($request->customer);

        $validator = Validator::make($customer, [
            'telefono' => 'required|numeric',
            'direccion' => 'required',
            'codigo_tipo_documento_identidad' => 'required|numeric',
            'numero_documento' => 'required|numeric',
            'identity_document_type_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
          return response()->json($validator->errors(), 422);
        }


        $user = auth()->user();
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
        $initialOrderStatus = StatusOrder::where('is_order_status', true)->where('is_initial', true)->orderBy('sort_order')->first()
            ?: StatusOrder::where('is_order_status', true)->orderBy('sort_order')->first();
        $initialStatusId = $initialOrderStatus ? $initialOrderStatus->id : null;

        // Estado de pago "Pagado" (aquel que tiene action_mark_payment = true o no es el inicial)
        $paidPaymentStatus = StatusOrder::where('is_payment_status', true)->where('action_mark_payment', true)->first();
        if (!$paidPaymentStatus) {
            $paidPaymentStatus = StatusOrder::where('is_payment_status', true)->where('is_initial', false)->first()
                ?: StatusOrder::where('is_payment_status', true)->first();
        }
        $paidPaymentStatusId = $paidPaymentStatus ? $paidPaymentStatus->id : null;

        $order = Order::create([
            'external_id' => Str::uuid()->toString(),
            'customer' => json_decode( $request->customer ),
            'shipping_address' => $request->input('shipping_address', ''),
            'items' => json_decode( $request->items ),
            'total' => $request->precio_culqi,
            'reference_payment' => 'culqui',
            'status_order_id' => $initialStatusId,
            'payment_status_order_id' => $paidPaymentStatusId,
            'purchase' => json_decode($request->purchase)
        ]);

        $customer_email = $request->email;
        $document = new stdClass;
        $document->client = $user->name;
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

}
