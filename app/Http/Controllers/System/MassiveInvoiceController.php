<?php

namespace App\Http\Controllers\System;

use App\Exports\MassiveInvoiceExport;
use App\Http\Controllers\Controller;
use App\Models\System\MassiveInvoice;
use App\Services\MassiveInvoiceService;
use Illuminate\Http\Request;
use Exception;

class MassiveInvoiceController extends Controller
{
    protected $massiveInvoiceService;

    public function __construct(MassiveInvoiceService $massiveInvoiceService)
    {
        $this->massiveInvoiceService = $massiveInvoiceService;
    }

    public function index()
    {
        return view('system.massive_invoice.index');
    }

    public function downloadFormat()
    {
        $filename = public_path('formats/formato_facturas_masivas.xlsx');
        return response()->download($filename);
    }

    public function upload(Request $request)
    {
        if ($request->hasFile('file')) {
            try {
                $file = $request->file('file');
                $jsonRequests = $this->massiveInvoiceService->processExcel($file);
                
                // Guardamos temporalmente los datos procesados en la sesión
                session(['massive_invoice_data' => $jsonRequests]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Archivo procesado correctamente',
                    'data' => $jsonRequests
                ]);
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al procesar el archivo: ' . $e->getMessage()
                ], 422);
            }
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No se encontró ningún archivo'
        ], 422);
    }

    public function process(Request $request) 
    {
        try {
            $documents = $request->input('documents', []);
            $responses = [];
            $successCount = 0;
            $totalCount = count($documents);
            
            foreach($documents as $document) {
                try {
                    $client = new \GuzzleHttp\Client([
                        'verify' => false,
                        'http_errors' => false
                    ]);
                    
                    $tenant = \App\Models\System\Client::where('number', $document['tenant_id'])->first();
                    if (!$tenant) {
                        throw new \Exception("Cliente no encontrado para el RUC: " . $document['tenant_id']);
                    }

                    // Obtener el dominio base actual y construir la URL de la API
                    $baseUrl = $tenant->hostname->fqdn;
                    $protocol = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';
                    $apiUrl = "{$protocol}://{$baseUrl}/api/documents";

                    \Log::info('Enviando documento a API:', [
                        'url' => $apiUrl,
                        'tenant' => $document['tenant_id'],
                        'data' => $document['data']
                    ]);

                    $response = $client->post($apiUrl, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $document['token'],
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json'
                        ],
                        'json' => $document['data']
                    ]);
                    
                    $statusCode = $response->getStatusCode();
                    $responseBody = json_decode((string) $response->getBody(), true);

                    \Log::info('Respuesta de API:', [
                        'status' => $statusCode,
                        'body' => $responseBody
                    ]);

                    // Validar si la respuesta indica error
                    if (isset($responseBody['success']) && $responseBody['success'] === false) {
                        throw new \Exception($responseBody['message'] ?? 'Error no especificado en la respuesta');
                    }

                    // Intentar obtener el número de comprobante de diferentes maneras
                    $numeroCompleto = null;
                    if(isset($responseBody['data']['number'])) {
                        $numeroCompleto = $responseBody['data']['number'];
                    } elseif(isset($responseBody['data']['serie_numero'])) {
                        $numeroCompleto = $responseBody['data']['serie_numero'];
                    } else {
                        // Si no hay número en la respuesta, usar el enviado
                        $numeroCompleto = $document['data']['serie_documento'];
                    }

                    $estadoEmision = $statusCode === 200 ? 'Registrado localmente' : 'Error';
                    $mensajeEmision = (string) ($responseBody['message'] ?? '');

                    // Extraer montos del documento
                    $totales = $document['data']['totales'];
                    $baseImponible = $totales['total_operaciones_gravadas'] ?? 0;
                    $igv = $totales['total_igv'] ?? 0;
                    $total = $totales['total_venta'] ?? 0;

                    $invoice = \App\Models\System\MassiveInvoice::create([
                        'fecha_emision' => $document['data']['fecha_de_emision'],
                        'fecha_vencimiento' => $document['data']['fecha_de_vencimiento'],
                        'ruc_emisor' => $tenant->number . ' - ' . $tenant->name,
                        'tipo_comprobante' => $document['data']['codigo_tipo_documento'],
                        'serie_comprobante' => $numeroCompleto,
                        'ruc' => $document['data']['datos_del_cliente_o_receptor']['numero_documento'] . ' - ' . 
                               $document['data']['datos_del_cliente_o_receptor']['apellidos_y_nombres_o_razon_social'],
                        'correo' => $document['data']['datos_del_cliente_o_receptor']['correo_electronico'],
                        'moneda' => $document['data']['codigo_tipo_moneda'],
                        'forma_pago' => explode('|', $document['data']['informacion_adicional'])[0] ?? '',
                        'observacion' => explode('|', $document['data']['informacion_adicional'])[1] ?? '',
                        'item' => $document['data']['items'][0]['codigo_interno'],
                        'descripcion_producto' => $document['data']['items'][0]['descripcion'],
                        'cantidad' => $document['data']['items'][0]['cantidad'],
                        'unidad_medida' => $document['data']['items'][0]['unidad_de_medida'],
                        'tipo_afectacion' => $document['data']['items'][0]['codigo_tipo_afectacion_igv'],
                        'precio' => $document['data']['items'][0]['precio_unitario'],
                        'status' => $statusCode === 200 ? 'PROCESADO' : 'ERROR',
                        'nota' => isset($responseBody['message']) ? $responseBody['message'] : '',
                        'external_id' => $responseBody['data']['external_id'] ?? null,
                        'pdf_link' => $responseBody['links']['pdf'] ?? null,
                        'estado_emision' => $estadoEmision,
                        'mensaje_emision' => $mensajeEmision,
                        'total_gravado' => $baseImponible,
                        'total_igv' => $igv,
                        'total_venta' => $total
                    ]);
                    
                    if ($statusCode === 200) {
                        $successCount++;
                    }
                    
                    $responses[] = [
                        'success' => true,
                        'tenant' => $tenant->number,
                        'url' => $apiUrl,
                        'data' => $responseBody
                    ];

                } catch (\Exception $e) {
                    \Log::error('Error procesando documento: ' . $e->getMessage());
                    $responses[] = [
                        'success' => false,
                        'tenant' => $document['tenant_id'] ?? 'No disponible',
                        'error' => $e->getMessage(),
                        'url' => $apiUrl ?? null
                    ];
                }
            }
            
            // Mensaje más preciso basado en el resultado real
            $message = $successCount === $totalCount 
                ? 'Todos los documentos fueron procesados correctamente' 
                : "Se procesaron {$successCount} de {$totalCount} documentos";

            return response()->json([
                'success' => $successCount > 0,
                'message' => $message,
                'responses' => $responses
            ], $successCount > 0 ? 200 : 422);
            
        } catch (\Exception $e) {
            \Log::error('Error general: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error en el proceso: ' . $e->getMessage()
            ], 422);
        }
    }

    public function records(Request $request)
    {
        $records = $this->filteredQuery($request)
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 10));

        return [
            'success' => true,
            'data' => $records
        ];
    }

    public function export(Request $request)
    {
        $records = $this->filteredQuery($request)
            ->orderBy('id', 'desc')
            ->get();

        return (new MassiveInvoiceExport)
            ->records($records)
            ->download('Facturacion_Masiva_' . now()->format('YmdHis') . '.xlsx');
    }

    protected function filteredQuery(Request $request)
    {
        $query = MassiveInvoice::query();

        if ($request->month) {
            $query->whereRaw('DATE_FORMAT(fecha_emision, "%Y-%m") = ?', [$request->month]);
        }

        if ($request->date) {
            $query->whereDate('fecha_emision', $request->date);
        }

        if ($request->serie_numero) {
            $query->where('serie_comprobante', 'like', "%{$request->serie_numero}%");
        }

        if ($request->receptor) {
            $query->where('ruc', 'like', "%{$request->receptor}%");
        }

        if ($request->emisor) {
            $searchTerm = '%' . str_replace(' ', '%', trim($request->emisor)) . '%';
            $query->where('ruc_emisor', 'like', $searchTerm);
        }

        return $query;
    }

    public function downloadFile($id, $type)
    {
        if ($type !== 'pdf') {
            abort(404);
        }

        $invoice = \App\Models\System\MassiveInvoice::findOrFail($id);

        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'http_errors' => false
            ]);

            // Usar el link guardado directamente
            $downloadUrl = $invoice->pdf_link;

            if (empty($downloadUrl)) {
                throw new \Exception("URL de descarga no disponible");
            }

            $response = $client->get($downloadUrl);
            
            if ($response->getStatusCode() !== 200) {
                throw new \Exception("Error al descargar archivo");
            }

            $filename = "{$invoice->serie_comprobante}.pdf";

            return response((string) $response->getBody())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename={$filename}");

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
