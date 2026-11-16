<?php

namespace App\Services\Sellnow;

use App\CoreFacturalo\{Facturalo, InputRequest};
use App\Http\Controllers\Tenant\Api\{DocumentController, SaleNoteController};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Shared persistence entry: retain the production transform, validation and policies. */
class ApiSaleCreation
{
    public function persistDocument(array $prepared): Facturalo
    {
        return (new Facturalo())->save($prepared);
    }

    public function create(string $kind, array $payload): array
    {
        $payload['source_module'] = 'VENDEYA';
        $request = Request::create('/api/sellnow/sales', 'POST', $payload);
        $request->setUserResolver(fn () => auth()->user());
        $request->attributes->set('sab.strict_input_exceptions', true);
        $request->attributes->set('sab.defer_sale_effects', true);
        $rules = [
            'items'=>'required|array|min:1','items.*'=>'required|array',
            'control_number'=>'prohibited','numero_control'=>'prohibited','issuer'=>'prohibited',
        ];
        $rules += $kind === 'document' ? [
            'serie_documento'=>'required|string','numero_documento'=>'required|in:#',
            'codigo_tipo_documento'=>'required|in:01','codigo_tipo_moneda'=>'required|in:VES,USD',
            'codigo_tipo_operacion'=>'required|string','fecha_de_vencimiento'=>'required|date',
            'fecha_de_emision'=>'required|date','datos_del_cliente_o_receptor'=>'required|array','totales'=>'required|array',
        ] : [
            'series_id'=>'required|integer|min:1','customer_id'=>'required|integer|min:1',
            'currency_type_id'=>'required|in:VES,USD','date_of_issue'=>'required|date',
        ];
        $request->validate($rules);
        if ($kind === 'sale_note') $request->merge(['number'=>'#']);

        try {
            if ($kind === 'document') {
                // Running the same middleware is essential: DocumentInput also resolves
                // identity, exact fiscal data, series and the transaction's customer.
                $result = app(InputRequest::class)->handle($request,
                    fn ($prepared) => app(DocumentController::class)->store($prepared), 'document', 'api');
            } else {
                // The note controller performs its native currency, identity, series,
                // receipt and inventory validation; deferred mode changes effects only.
                $result = app(SaleNoteController::class)->store($request);
            }
        } catch (\Exception $exception) {
            // Legacy validators/resolvers use plain Exception for known business
            // rejections. Preserve database and programming exception classes.
            if (get_class($exception) === \Exception::class) {
                throw ValidationException::withMessages(['payload'=>$exception->getMessage()]);
            }
            throw $exception;
        }
        if ($result instanceof Response) {
            $body = json_decode($result->getContent(), true) ?: [];
            if ($result->getStatusCode() >= 400) {
                throw ValidationException::withMessages($body['errors'] ?? ['payload' => $body['message'] ?? 'Revise los datos de la venta.']);
            }
            $result = $body;
        }
        if (!is_array($result) || ($result['success'] ?? false) !== true || empty($result['data']['id'])) {
            throw ValidationException::withMessages(['payload' => 'SAB no confirmó la creación de la venta.']);
        }
        return $result;
    }
}
