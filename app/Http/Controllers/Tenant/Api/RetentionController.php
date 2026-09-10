<?php
namespace App\Http\Controllers\Tenant\Api;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class RetentionController extends Controller
{
    public function __construct()
    {
        $this->middleware('input.request:retention,api', ['only' => ['store']]);
    }

    public function store(Request $request)
    {
        $fact = DB::connection('tenant')->transaction(function () use($request) {
            $facturalo = new Facturalo();
            $facturalo->save($request->all());
            $facturalo->createPdf();
            $facturalo->sendEmail();

            return $facturalo;
        });

        $document = $fact->getDocument();
        return [
            'success' => true,
            'data' => [
                'number' => $document->number_full,
                'filename' => $document->filename,
                'external_id' => $document->external_id,
            ],
            'links' => [
                'pdf' => $document->download_external_pdf,
            ],
            'response' => $fact->getResponse(),
        ];
    }
}
