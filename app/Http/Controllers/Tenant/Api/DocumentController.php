<?php
namespace App\Http\Controllers\Tenant\Api;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\DocumentCollection;
use App\Models\Tenant\Document;
use App\Models\Tenant\StateType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('input.request:document,api', ['only' => ['store', 'storeServer']]);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $print_result = ['auto_printed' => false, 'print_order_id' => null, 'reason' => null];

        $fact = DB::connection('tenant')->transaction(function () use ($request, &$print_result) {
            $facturalo = new Facturalo();
            $facturalo->save($request->all());
            $facturalo->createPdf();
            $print_result = $facturalo->generatePrintOrder();
            $facturalo->sendEmail();

            return $facturalo;
        });

        $document = $fact->getDocument();
        $response = $fact->getResponse();

        return [
            'success' => true,
            'data' => [
                'number' => $document->number_full,
                'filename' => $document->filename,
                'external_id' => $document->external_id,
                'state_type_id' => $document->state_type_id,
                'state_type_description' => $this->getStateTypeDescription($document->state_type_id),
                'number_to_letter' => $document->number_to_letter,
                'id' => $document->id,
                'print_ticket' =>  $document->getUrlPrintByFormat('ticket'),
            ],
            // Resultado de la impresión automática server-side.
            // Si auto_printed=true, el cliente NO debe ejecutar su flujo de
            // descarga+base64+POST a /print-orders (evita doble impresión).
            'print' => $print_result,
            'data_ws' => [
                'message_text' => "Su Factura {$document->number_full} ha sido generada correctamente, puede revisarla en el siguiente enlace: ".url('')."/print/document/{$document->external_id}/".(optional(\App\Models\Tenant\Configuration::first())->qr_api_pdf_format === 'a4' ? 'a4' : 'ticket')."",
                "pdf_a4_filename" => url('')."/api/document-file/document/{$document->external_id}/a4",
                "pdf_ticket_filename" => url('')."/api/document-file/document/{$document->external_id}/ticket",
                "full_filename" => $document->filename.".pdf",
                "customer_telephone" => optional($document->person)->telephone
            ],
            'links' => [
                'pdf' => $document->download_external_pdf,
            ],
            'response' => $response,
        ];
    }

    private function getStateTypeDescription($id)
    {
        return StateType::find($id)->description;
    }

    public function lists($startDate = null, $endDate = null)
    {

        if ($startDate == null)
        {
            $record = Document::whereTypeUser()
                                ->orderBy('date_of_issue', 'desc')
                                ->take(50)
                                ->get();
        }
        else
        {
            $record = Document::whereBetween('date_of_issue', [$startDate, $endDate])
                ->orderBy('date_of_issue', 'desc')
                ->get();
        }

        $records = new DocumentCollection($record);
        return $records;
    }

    /**
     * Devuelve un comprobante por su id.
     *
     * whereTypeUser() evita que un vendedor pueda leer documentos de otro usuario
     * pasando ids ajenos; para los demás perfiles no restringe nada.
     */
    public function record($id)
    {
        $record = Document::whereTypeUser()->find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el comprobante solicitado.',
            ], 404);
        }

        // Se conserva el envoltorio "data" que agregaba JsonResource para no cambiar
        // la forma de la respuesta.
        return response()->json([
            'data' => $record->getApiResourceFind(),
        ]);
    }

    public function updatestatus(Request $request)
    {
        $record = Document::whereExternal_id($request->externail_id)->first();
        $record->state_type_id = $request->state_type_id;
        $record->save();

        return [
            'success' => true,
        ];
    }

}
