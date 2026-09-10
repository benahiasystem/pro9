<?php
namespace App\Http\Controllers\Tenant\Api;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoidedController extends Controller
{
    public function __construct()
    {
        $this->middleware('input.request:voided,api', ['only' => ['store']]);
    }

    public function store(Request $request)
    {
        $fact = DB::connection('tenant')->transaction(function () use($request) {
            $facturalo = new Facturalo();
            $facturalo->save($request->all());
            return $facturalo;
        });

        $document = $fact->getDocument();
        //$response = $fact->getResponse();

        return [
            'success' => true,
            'data' => [
                'external_id' => $document->external_id,
            ]
        ];
    }
}
