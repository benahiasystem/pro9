<?php
namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\System\Configuration;
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
use App\Support\Venezuela\Localization;
// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
use Hyn\Tenancy\Contracts\CurrentHostname;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MercadoPago\Payment;
use MercadoPago\SDK;
use Modules\Payment\Models\PaymentConfiguration;
use Modules\Payment\Traits\CulqiTrait;
use Modules\Payment\Traits\IzipayTrait;

class PaymentGatewayController extends Controller 
{
    use CulqiTrait;
    use IzipayTrait;

    public function enabledCheckouts(Request $request)
    {

        $is_tenant = $request->boolean('isTenant', false);
        // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
        if (Localization::nationalCurrencyId() === 'VES') {
            return [
                'checkout' => null,
                'is_tenant' => (bool) app(CurrentHostname::class),
                'message' => 'Las pasarelas configuradas no admiten cobros en bolívares.',
            ];
        }
        // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
        $checkout = $is_tenant ? PaymentConfiguration::enabledCheckout() : Configuration::enabledCheckout();
        return [
            'checkout' => $checkout,
            'is_tenant' => (bool) app(CurrentHostname::class),
        ];
    }

    //Culqi 

    /**
     * Crear cargo en Culqi (flujo de checkout)
     */
    public function culqiCreateCharge(Request $request)
    {
        $is_tenant = $request->boolean('isTenant', false);
        $validated = $request->validate([
            'amount'        => 'required|numeric',
            'currency_code' => 'required|string',
            'email'         => 'nullable|email',
            'source_id'     => 'required|string',
        ]);

        // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
        if (Localization::isNationalCurrency($validated['currency_code'])) {
            return response()->json([
                'success' => false,
                'paid' => false,
                'user_message' => 'Culqi no está habilitado para cobros en bolívares.',
            ], 422);
        }
        // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########

        $privateKey = $this->culqiCredentials($is_tenant);

        if (!$privateKey) {
            return response()->json([
                'success'      => false,
                'paid'         => false,
                'user_message' => 'No se encontraron las credenciales de Culqi configuradas',
            ], 400);
        }

        try {
            $charge = $this->charge(
                ['private_key' => $privateKey],
                [
                    'amount'        => $validated['amount'],
                    'currency_code' => $validated['currency_code'],
                    'email'         => $validated['email'] ?? 'admin@gmail.com',
                    'source_id'     => $validated['source_id'],
                    'capture'       => true,
                ]
            );

            // la respuesta de culqi puede no traer outcome cuando el cargo no se concreta
            $paid = data_get($charge, 'outcome.type') === 'venta_exitosa';

            return response()->json([
                'success' => true,
                'result' => $charge,
                'pending' => data_get($charge, 'status') === 'pending',
                'paid'    => $paid,
            ]);

        } catch (\Culqi\Error\UnhandledError $e) {
            // dd($e);
            $error = json_decode($e->getMessage());
            Log::error('Culqi charge error', ['body' => $e->getMessage()]);

            return response()->json([
                'success'          => false,
                'paid'             => false,
                'merchant_message' => $error->merchant_message ?? 'Error al procesar el cobro',
                'user_message'     => $error->user_message     ?? 'La compra no pudo ser procesada',
                'result' => $error
            ], 400);

        } catch (\Culqi\Error\CulqiException $e) {
            Log::error('Culqi exception', ['message' => $e->getMessage()]);

            return response()->json([
                'success'      => false,
                'paid'         => false,
                'user_message' => $e->getMessage(),
            ], 400);
        }
    }

    public function culqiRecord(Request $request)
    {
        $is_tenant = $request->boolean('isTenant', false);

        $configuration = $is_tenant
            ? PaymentConfiguration::select('publickey_culqi')->first()
            : Configuration::select('token_public_culqui')->first();

        return [
            'publickey_culqi' => $is_tenant
                ? optional($configuration)->publickey_culqi
                : optional($configuration)->token_public_culqui,
        ];
    }

    /**
     * Private key de culqi
     *
     * @return string|null null cuando no hay credenciales configuradas
     */
    private function culqiCredentials(bool $is_tenant = false)
    {
        if ($is_tenant) {
            return optional(PaymentConfiguration::select('privatekey_culqi')->first())->privatekey_culqi;
        }

        return optional(Configuration::select('token_private_culqui')->first())->token_private_culqui;
    }


    //Izipay


    public function izipayCreatePayment(Request $request)
    {

        $is_tenant = $request->boolean('isTenant', false);

        // validate() descarta lo que no esté declarado, el análisis de riesgo de izipay
        // rechaza el pago (PSP_641) cuando billingDetails llega incompleto
        $validated = $request->validate([
            'amount'                                  => 'required|numeric',
            'currency'                                => 'required|string',
            'orderId'                                 => 'nullable|string',
            'customer.email'                          => 'nullable|email',
            'customer.billingDetails.firstName'       => 'nullable|string',
            'customer.billingDetails.lastName'        => 'nullable|string',
            'customer.billingDetails.phoneNumber'     => 'nullable',
            'customer.billingDetails.identityType'    => 'nullable|string',
            'customer.billingDetails.identityCode'    => 'nullable|string',
            'customer.billingDetails.address'         => 'nullable|string',
            'customer.billingDetails.country'         => 'nullable|string',
            'customer.billingDetails.city'            => 'nullable|string',
            'customer.billingDetails.state'           => 'nullable|string',
            'customer.billingDetails.zipCode'         => 'nullable|string',
        ]);

        // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
        if (Localization::isNationalCurrency($validated['currency'])) {
            return [
                'success' => false,
                'formToken' => null,
                'message' => 'Izipay no está habilitado para cobros en bolívares.',
            ];
        }
        // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########

        $validated = $this->normalizeIzipayCustomer($validated);

        $credentials = $this->izipayCredentials($is_tenant);

        if (!$credentials) {
            return [
                'success' => false,
                'formToken' => null,
                'message' => 'No se encontraron las credenciales de Izipay configuradas',
            ];
        }

        $result = $this->createPayment($credentials, $validated, $error);

        return [
            'success' => $result ? true : false,
            'formToken' => $result,
            'message' => $error,
        ];
    }


    /**
     * Izipay rechaza los campos vacíos del billingDetails y su análisis de riesgo
     * penaliza la ausencia de country, se descartan los vacíos y se asume PE
     */
    private function normalizeIzipayCustomer(array $payload): array
    {

        $billing = array_filter(
            data_get($payload, 'customer.billingDetails', []),
            fn ($value) => !is_null($value) && $value !== ''
        );

        $billing['country'] = $billing['country'] ?? 'PE';

        data_set($payload, 'customer.billingDetails', $billing);

        return $payload;

    }

    /**
     * Credenciales de izipay
     *
     * @return array|null null cuando no hay credenciales configuradas o izipay está deshabilitado
     */
    private function izipayCredentials(bool $is_tenant = false)
    {
        return $is_tenant ? PaymentConfiguration::accessIzipay() : Configuration::accessIzipay();
    }


    public function izipayRecord(Request $request)
    {

        $is_tenant = $request->boolean('isTenant', false);

        $configuration = $is_tenant
            ? PaymentConfiguration::select('publickey_izipay')->first()
            : Configuration::select('publickey_izipay')->first();

        return [
            'publickey_izipay' => optional($configuration)->publickey_izipay,
        ];
    }

    public function izipayTransaction(Request $request)
    {
        $is_tenant = $request->boolean('isTenant', false);
        $credentials = $this->izipayCredentials($is_tenant);

        $uuid = $request->validate([
            'uuid' => 'required|string'
        ])['uuid'];

        if (!$credentials) {
            return [
                'success' => false,
                'result' => null,
                'pending' => false,
                'paid' => false,
                'message' => 'No se encontraron las credenciales de Izipay configuradas',
            ];
        }

        $result = $this->getTransaction($credentials, $uuid, $error);

        // la consulta puede fallar (null) o responder un error sin la clave answer
        $status = data_get($result, 'answer.status');

        return [
            'success' => !is_null($status),
            'result' => $result,
            'pending' => $status === 'RUNNING',
            'paid' => $status === 'PAID',
            'message' => $error,
        ];
    }

    /**
     * Access token de mercadopago
     *
     * @return string|null null cuando no hay credenciales configuradas
     */
    public function mercadoPagoCredentials(bool $is_tenant = false)
    {
        if ($is_tenant) {
            return optional(PaymentConfiguration::select('access_token_mp')->first())->access_token_mp;
        }

        return optional(Configuration::select('access_token_mp')->first())->access_token_mp;
    }

    /**
     * Public key de MercadoPago (para inicializar el SDK en el front)
     */
    public function mercadoPagoRecord(Request $request)
    {
        $is_tenant = $request->boolean('isTenant', false);
        $public_key = $is_tenant
            ? optional(PaymentConfiguration::select('public_key_mp')->first())->public_key_mp
            : optional(Configuration::select('public_key_mp')->first())->public_key_mp;

        return [
            'public_key_mp' => $public_key,
        ];
    }

    public function mercadoPagoCreatePayment(Request $request)
    {
        // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
        if (Localization::nationalCurrencyId() === 'VES') {
            return [
                'success' => false,
                'result' => null,
                'paid' => false,
                'message' => 'MercadoPago no está habilitado para cobros en bolívares.',
            ];
        }
        // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
        $is_tenant = $request->boolean('isTenant', false);
        $access_token = $this->mercadoPagoCredentials($is_tenant);

        if (!$access_token) {
            return [
                'success' => false,
                'result' => null,
                'paid' => false,
                'message' => 'No se encontraron las credenciales de MercadoPago configuradas',
            ];
        }

        try {
            $validated = $request->validate([
                'identity_document_type_id' => 'nullable|string',
                'number' => 'nullable|string',
                'email' =>  'nullable|email',
                'plan_id' => 'nullable',
                'order_id' => 'nullable',
                'form_data'                        => 'required|array',
                'form_data.token'                  => 'required|string',
                'form_data.issuer_id'              => 'required',
                'form_data.payment_method_id'      => 'required|string',
                'form_data.transaction_amount'     => 'required|numeric',
                'form_data.installments'           => 'required|integer',
                'form_data.payer'                  => 'required|array',
                'form_data.payer.email'            => 'required|email',
                'form_data.payer.identification'   => 'required|array',
                'form_data.payer.identification.type'   => 'required|string',
                'form_data.payer.identification.number' => 'required|string',
            ]);

            if (!$access_token) {
                return [
                    'success' => false,
                    'result' => null,
                    'message' => 'Datos de configuración incorrectos, comuníquese con el administrador',
                    'paid' => false,
                ];
            }

            SDK::setAccessToken($access_token);
            Payment::setCustomHeader('X-Idempotency-Key', (string) Str::uuid());

            $payment = new Payment();
            $payment->token = $request->input('form_data.token');
            $payment->issuer_id = $request->input('form_data.issuer_id');
            $payment->payment_method_id = $request->input('form_data.payment_method_id');
            $payment->transaction_amount = (float) $request->input('form_data.transaction_amount');
            $payment->installments = (int) $request->input('form_data.installments');
            $payment->payer = $request->input('form_data.payer');

            if (!$payment->save()) {
                $error = $payment->error;

                return [
                    'success' => false,
                    'result' => null,
                    'message' => $error->message ?? 'No se pudo procesar el pago',
                    'details' => $error ? [
                        'status' => $error->status ?? null,
                        'body' => $error,
                    ] : null,
                    'paid' => false,
                ];
            }

            $paid = $this->getStatusPaymentMP($payment->status);

            return [
                'success' => true,
                'paid' => $paid,
                'pending' => in_array($payment->status, ['in_process', 'pending'], true),
                'result' => $this->formatMercadoPagoPaymentResult($payment),
            ];

        } catch (\Throwable $th) {
            Log::error('MercadoPago payment error', ['message' => $th->getMessage()]);

            return [
                'success' => false,
                'result' => null,
                'message' => $th->getMessage(),
                'paid' => false
            ];

        }


    }

    private function formatMercadoPagoPaymentResult(Payment $payment): array
    {
        return [
            'id' => $payment->id ?? null,
            'status' => $payment->status ?? null,
            'status_detail' => $payment->status_detail ?? null,
        ];
    }
    private function getStatusPaymentMP($status)
    {
        return match ($status) {
            'approved', 'authorized'            => true,
            'rejected'                          => false,
            'cancelled'                         => false,
            'refunded', 'charged_back'          => false,
            'pending',
            'in_process', 'in_mediation'        => false,
            default                             => false,
        };
     }

}
