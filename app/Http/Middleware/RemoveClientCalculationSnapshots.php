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
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        unset($values['image_url'],$values['image_url_medium'],$values['image_url_small'],$values['image_urls_expires_at'],$values['image_media_id']);
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        foreach ($values as $key=>$value) {
            if (is_array($value)) $values[$key]=$this->clean($value);
        }
        return $values;
    }
}
