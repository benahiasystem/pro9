<?php

namespace App\CoreFacturalo;

use Closure;

class InputRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param  $type
     * @param  $service
     * @return mixed
     * @throws \Exception
     */
    public function handle($request, Closure $next, $type, $service)
    {
        try {
            return \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use ($request, $next, $type, $service) {
                if ($type==='dispatch' && $service==='web') {
                    \App\Services\Fiscal\DeliveryOrderInput::authorize();
                    \App\Models\Tenant\Company::query()->lockForUpdate()->firstOrFail();
                }
                $inputs = $request->all();
                if (isset($inputs['expected_calculation']) && $type === 'document') {
                    // Lock before validation establishes a consistent read snapshot.
                    \App\Models\Tenant\Company::query()->lockForUpdate()->firstOrFail();
                }
                if ($service === 'api') $inputs = $this->transformInputs($inputs, $type);
                $inputs = $this->validationInputs($inputs, $type, $service);
                $request->replace($this->setInputs($inputs, $type, $service));
                $response = $next($request);
                if ($response instanceof \Symfony\Component\HttpFoundation\Response && $response->getStatusCode() >= 400) {
                    throw new InputResponseRollback($response);
                }
                return $response;
            });
        } catch (InputResponseRollback $exception) {
            return $exception->response;
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            // Internal durable-sale callers must distinguish unexpected failures
            // from a known validation rejection, so retries retain their UUID.
            if ($request->attributes->get('pro9.strict_input_exceptions') === true) throw $exception;
            return response()->json(['success' => false, 'message' => 'No se pudo registrar el documento. Revise los datos y la serie seleccionada.'], 400);
        }
    }

    private function transformInputs($inputs, $type)
    {
        $class = "App\\CoreFacturalo\\Requests\\Api\\Transform\\".ucfirst($type)."Transform";
        return $class::transform($inputs);
    }

    private function validationInputs($inputs, $type, $service)
    {
        $class = "App\\CoreFacturalo\\Requests\\".ucfirst($service)."\\Validation\\".ucfirst($type)."Validation";
        return $class::validation($inputs);
    }

    private function setInputs($inputs, $type, $service)
    {
        $class = "App\\CoreFacturalo\\Requests\\Inputs\\".ucfirst($type)."Input";
        return $class::set($inputs, $service);
    }
}
