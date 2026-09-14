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
            $inputs = $request->all();
            if ($service === 'api') {
                $inputs = $this->transformInputs($inputs, $type);
            }
            $inputs = $this->validationInputs($inputs, $type, $service);
            $request->replace($this->setInputs($inputs, $type, $service));
        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Revise los datos del comprobante.', 'errors' => $e->errors()], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        return $next($request);
    }

    private function transformInputs($inputs, $type)
    {
        $class = "App\\CoreFacturalo\\Requests\\Api\\Transform\\".ucfirst($type)."Transform";
        return $class::transform($inputs);
    }

    private function validationInputs($inputs, $type, $service)
    {
        $class = "App\\CoreFacturalo\\Requests\\".ucfirst($service)."\\Validation\\".ucfirst($type)."Validation";
        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        if ($service === 'api' && $type === 'document') return $class::validation($inputs, true);
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
        return $class::validation($inputs);
    }

    private function setInputs($inputs, $type, $service)
    {
        $class = "App\\CoreFacturalo\\Requests\\Inputs\\".ucfirst($type)."Input";
        return $class::set($inputs, $service);
    }
}
