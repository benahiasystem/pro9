<?php
namespace App\Http\Controllers\Tenant\Api;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseSettlementController extends Controller
{
    use StorageDocument;

    public function __construct()
    {
        $this->middleware('input.request:purchaseSettlement,api', ['only' => ['store']]);
    }

    public function store(Request $request)
    {

        // dd($request->all());
        
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
                'number_to_letter' => $document->number_to_letter,
            ],
            'links' => [
                'pdf' => $document->download_external_pdf,
            ],
            'response' => $fact->getResponse(),
        ];
    }
 
}
