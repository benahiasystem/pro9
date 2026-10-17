<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Calculation results are server-owned; original quantity/price inputs remain available. */
final class RemoveClientCalculationSnapshots
{
    public function handle(Request $request, Closure $next)
    {
        $request->query->replace($this->clean($request->query->all()));
        $input=$request->isJson() ? $request->json() : $request->request;
        $input->replace($this->clean($input->all()));
        return $next($request);
    }

    private function clean(array $values): array
    {
        unset($values['calculation_snapshot']);
        foreach ($values as $key=>$value) {
            if (is_array($value)) $values[$key]=$this->clean($value);
        }
        return $values;
    }
}
