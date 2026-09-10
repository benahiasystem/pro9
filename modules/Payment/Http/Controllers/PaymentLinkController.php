<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\{
    Document,
    Company,
    Person
};
use Exception, Illuminate\Support\Facades\DB;
use Modules\Payment\Http\Requests\PaymentLinkRequest;
use Modules\Payment\Models\{
    PaymentLink,
    PaymentLinkPayment,
    PaymentLinkType,
    PaymentConfiguration,
};
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Modules\Payment\Mail\PaymentLinkEmail;
use Modules\Finance\Helpers\UploadFileHelper;
use Modules\Payment\Traits\{
    PaymentLinkPaymentTrait,
    PaymentLinkTrait,
};
use Modules\Payment\Http\Resources\{
    PaymentLinkCollection,
    PaymentLinkResource,
};
use Modules\Finance\Traits\FinanceTrait;

class PaymentLinkController extends Controller
{

    use PaymentLinkTrait;
    use PaymentLinkPaymentTrait;
    use FinanceTrait;


    /**
     * Condición de pago a crédito en documentos
     */
    public const CREDIT_PAYMENT_CONDITION_ID = '02';


    /**
     * Número de documento del cliente generico (clientes varios)
     */
    public const GENERAL_CUSTOMER_NUMBER = '99999999';


    public function index()
    {
        return view('payment::payment_links.index');
    }


    public function columns()
    {
        return [
            'uuid' => 'Identificador',
            'total' => 'Total',
        ];
    }


    public function tables()
    {
        $payment_link_types = PaymentLinkType::get();
        $customers = Person::with('addresses')
                ->whereType('customers')
                ->whereIsEnabled()
                ->orderBy('name')
                ->take(20)
                ->get()->transform(function ($row) {
                    /** @var Person $row */
                    return $row->getCollectionData();
                });

        return compact('payment_link_types', 'customers');
    }


    /**
     * Buscar facturas para asociar al link de pago
     *
     * @param  Request $request
     * @return array
     */
    public function searchDocuments(Request $request)
    {
        $input = $request->input('input');

        $documents = Document::query()
                        ->where('document_type_id', '01')
                        ->where('payment_condition_id', self::CREDIT_PAYMENT_CONDITION_ID)
                        ->whereNotIn('state_type_id', Document::VOIDED_REJECTED_IDS)
                        // saldo pendiente > 0
                        ->whereRaw('documents.total > (select coalesce(sum(document_payments.payment), 0) from document_payments where document_payments.document_id = documents.id)')
                        ->when($request->filled('customer_id'), function ($query) use ($request) {
                            $query->where('customer_id', $request->input('customer_id'));
                        })
                        ->when($input, function ($query) use ($input) {
                            $query->where(function ($query) use ($input) {
                                $query->where('series', 'like', "%{$input}%")
                                        ->orWhere('number', 'like', "%{$input}%");
                            });
                        })
                        ->withSum('payments as total_payments', 'payment')
                        ->latest()
                        ->take(20)
                        ->get()
                        ->transform(function ($row) {

                            $pending = round((float) $row->total - (float) $row->total_payments, 2);

                            return [
                                'id' => $row->id,
                                'instance_type' => 'document',
                                'description' => "{$row->number_full} - {$row->currency_type_id} {$row->total} (Pendiente: {$pending})",
                                'number_full' => $row->number_full,
                                'currency_type_id' => $row->currency_type_id,
                                'total' => (float) $row->total,
                                'pending' => $pending,
                            ];
                        });

        return compact('documents');
    }


    public function records(Request $request)
    {
        $records = PaymentLink::where($request->column, 'like', "%{$request->value}%")->latest();

        return new PaymentLinkCollection($records->paginate(config('tenant.items_per_page')));
    }


    /**
     * Buscar link de pago desde form de pagos
     *
     * @param  int $document_payment_id
     * @param  string $instance_type
     * @param  int $payment_link_type_id
     * @return array
     */
    public function record ($payment_link_type_id)
    {

        $payment_link = PaymentLink::find((int)$payment_link_type_id);
                                        // ->where('payment_type', PaymentLink::getModelByType($instance_type))

        if(is_null($payment_link))
        {
            return [
                'has_payment_link' => false,
                'data' => [],
            ];
        }

        return [
            'has_payment_link' => true,
            'data' => $payment_link->getRowResourceWithoutPayment(),
        ];

    }


    /**
     * Registrar/Actualizar link de pago
     *
     * Solo se guarda el detalle de lo que se va a cobrar en estado pendiente,
     * los pagos se generan cuando el link es marcado como pagado
     *
     * @param  PaymentLinkRequest $request
     * @return array
     */
    public function store(PaymentLinkRequest $request)
    {

        $id = $request->input('id');

        $data = DB::connection('tenant')->transaction(function () use ($request, $id) {

            $payment_link = PaymentLink::firstOrNew(['id' => $id]);

            if($payment_link->is_paid) throw new Exception('El link de pago ya fue pagado, no se puede modificar');

            $with_customer = $request->boolean('with_customer', false);
            $person = $with_customer ? $this->getCustomer($request->input('customer_id')) : $this->getGeneralCustomer();

            // comprobantes a cobrar con el link (solo aplica cuando se asocia un cliente)
            $documents = $with_customer ? $this->getInputDocuments($request, $person) : [];

            $total = count($documents) > 0 ? $this->getTotalDocuments($documents) : round((float) $request->input('total'), 2);

            $payment_link->fill([
                'user_id' => $payment_link->user_id ?? auth()->id(),
                'uuid' => $payment_link->uuid ?? Str::uuid()->toString(),
                'fiscal_environment' => $payment_link->fiscal_environment ?? Company::select('fiscal_environment')->firstOrFail()->fiscal_environment,
                // el tipo ya no se elige en el formulario, la pasarela la define el checkout configurado
                'payment_link_type_id' => $payment_link->payment_link_type_id
                    ?? $request->input('payment_link_type_id')
                    ?? $this->getDefaultPaymentLinkTypeId(),
                'person_id' => $person->id,
                'total' => $total,
                'status' => PaymentLink::STATUS_PENDING,
            ]);

            $payment_link->save();

            // se reemplaza el detalle pendiente, el ya pagado no se toca
            $payment_link->payments()->wherePending()->delete();

            $this->storePendingPayments($payment_link, $documents, $total);

            return [
                'id' => $payment_link->id,
                'user_payment_link' => $payment_link->user_payment_link,
            ];
        });


        return [
            'success' => true,
            'message' => $id ? 'Link actualizado con éxito' : 'Link generado con éxito',
            'data' => $data
        ];

    }


    /**
     *
     * Registrar el detalle pendiente de cobro del link de pago
     *
     * Cuando no hay comprobantes se registra una sola fila sin comprobante asociado,
     * que al pagarse genera un ingreso (finanzas)
     *
     * @param  PaymentLink $payment_link
     * @param  array $documents
     * @param  float $total
     * @return void
     */
    private function storePendingPayments($payment_link, $documents, $total)
    {

        if(count($documents) === 0)
        {
            $payment_link->payments()->create([
                'total' => $total,
                'status' => PaymentLinkPayment::STATUS_PENDING,
            ]);

            return;
        }

        foreach ($documents as $row) {

            $payment_link->payments()->create([
                'record_id' => $row['record']->id,
                'record_type' => get_class($row['record']),
                'total' => $row['payment'],
                'status' => PaymentLinkPayment::STATUS_PENDING,
            ]);

        }

    }


    /**
     *
     * Generar link de pago por el saldo pendiente de un comprobante
     *
     * Usado desde el listado de comprobantes
     *
     * @param  Request $request
     * @return array
     */
    public function storeFromDocument(Request $request)
    {

        $document = Document::findOrFail($request->input('document_id'));

        if($document->isVoidedOrRejected())
        {
            return [
                'success' => false,
                'message' => "El comprobante {$document->number_full} está anulado o rechazado"
            ];
        }

        $pending = $this->getPendingPayment($document);

        if($pending <= 0)
        {
            return [
                'success' => false,
                'message' => "El comprobante {$document->number_full} no tiene saldo pendiente"
            ];
        }

        // si ya se generó un link pendiente para el comprobante se reutiliza
        $payment_link = $this->getPendingPaymentLinkByDocument($document);

        if($payment_link)
        {
            return [
                'success' => true,
                'message' => "El comprobante {$document->number_full} ya tiene un link de pago pendiente",
                'data' => $this->getStoreFromDocumentData($payment_link),
            ];
        }

        $payment_link = DB::connection('tenant')->transaction(function () use ($document, $pending) {

            $payment_link = PaymentLink::create([
                'user_id' => auth()->id(),
                'uuid' => Str::uuid()->toString(),
                'fiscal_environment' => Company::select('fiscal_environment')->firstOrFail()->fiscal_environment,
                'payment_link_type_id' => $this->getDefaultPaymentLinkTypeId(),
                'person_id' => $document->customer_id,
                'total' => $pending,
                'status' => PaymentLink::STATUS_PENDING,
            ]);

            $documents = [
                [
                    'instance_type' => 'document',
                    'record' => $document,
                    'payment' => $pending,
                ],
            ];

            $this->storePendingPayments($payment_link, $documents, $pending);

            return $payment_link;

        });

        return [
            'success' => true,
            'message' => 'Link de pago generado con éxito',
            'data' => $this->getStoreFromDocumentData($payment_link),
        ];

    }


    /**
     *
     * Buscar un link de pago pendiente asociado al comprobante
     *
     * @param  Document $document
     * @return PaymentLink|null
     */
    private function getPendingPaymentLinkByDocument($document)
    {

        return PaymentLink::wherePending()
                    ->whereHas('payments', function ($query) use ($document) {
                        $query->wherePending()
                                ->where('record_id', $document->id)
                                ->where('record_type', Document::class);
                    })
                    ->latest()
                    ->first();

    }


    /**
     * @param  PaymentLink $payment_link
     * @return array
     */
    private function getStoreFromDocumentData($payment_link)
    {
        return [
            'id' => $payment_link->id,
            'number_full' => $payment_link->number_full,
            'total' => (float) $payment_link->total,
            'user_payment_link' => $payment_link->user_payment_link,
        ];
    }


    /**
     *
     * Tipo de link de pago usado cuando no se elige uno
     *
     * @return string
     */
    private function getDefaultPaymentLinkTypeId()
    {
        return optional(PaymentLinkType::first())->id ?? '01';
    }


    /**
     *
     * Marcar el link de pago como pagado y generar los pagos de los comprobantes asociados
     *
     * @param  Request $request
     * @return array
     */
    public function confirmPayment(Request $request)
    {

        $payment_link = PaymentLink::findOrFail($request->input('id'));

        if($payment_link->is_paid)
        {
            return [
                'success' => false,
                'message' => 'El link de pago ya fue marcado como pagado'
            ];
        }

        $total_payments = $this->setPaymentLinkAsPaid($payment_link);

        return [
            'success' => true,
            'message' => "El link de pago fue marcado como pagado, se registraron {$total_payments} pago(s)"
        ];

    }


    /**
     *
     * Marcar el link como pagado y registrar los pagos desde la url publica
     *
     * Se llama cuando la pasarela confirma el pago
     *
     * @param  Request $request
     * @param  string $uuid
     * @return array
     */
    public function publicConfirmPayment(Request $request, $uuid)
    {

        $payment_link = PaymentLink::where('uuid', $uuid)->firstOrFail();

        if($payment_link->is_paid)
        {
            return [
                'success' => true,
                'message' => 'El link de pago ya fue registrado como pagado',
            ];
        }

        if(!$request->boolean('paid'))
        {
            return [
                'success' => false,
                'message' => 'El pago no fue aprobado por la pasarela',
            ];
        }

        try {

            $total_payments = $this->setPaymentLinkAsPaid($payment_link);

        } catch (Exception $e) {

            // el link queda pendiente para poder confirmarlo desde el listado
            return [
                'success' => false,
                'message' => "El pago fue aprobado pero no se pudo registrar: {$e->getMessage()}",
            ];

        }

        return [
            'success' => true,
            'message' => "Pago registrado con éxito, se registraron {$total_payments} pago(s)",
        ];

    }


    /**
     *
     * Marcar como pagado y registrar los pagos pendientes
     *
     * @param  PaymentLink $payment_link
     * @return int
     */
    private function setPaymentLinkAsPaid($payment_link)
    {

        return DB::connection('tenant')->transaction(function () use ($payment_link) {

            $total_payments = $this->registerPaymentLinkPayments($payment_link);

            $payment_link->setAsPaid();

            return $total_payments;

        });

    }


    /**
     *
     * Obtener el cliente asociado al link de pago
     *
     * @param  int|null $customer_id
     * @return Person
     */
    private function getCustomer($customer_id)
    {
        $person = Person::find($customer_id);

        if(!$person) throw new Exception('No se encontró el cliente asociado al link de pago');

        return $person;
    }


    /**
     *
     * Obtener el cliente generico (clientes varios)
     *
     * @return Person
     */
    private function getGeneralCustomer()
    {
        $person = Person::where('number', self::GENERAL_CUSTOMER_NUMBER)->first();

        if(!$person) throw new Exception('No se encontró el cliente varios para registrar el link de pago');

        return $person;
    }


    /**
     *
     * Obtener y validar los comprobantes enviados desde el formulario
     *
     * Cada fila contiene el registro a cobrar y el monto a aplicar, se valida
     * que el tipo de comprobante sea soportado, que pertenezca al cliente del link
     * y que el monto no supere el saldo pendiente
     *
     * @param  PaymentLinkRequest $request
     * @param  Person $person
     * @return array
     */
    private function getInputDocuments($request, $person)
    {

        $documents = [];

        foreach ($request->input('documents', []) as $row) {

            $instance_type = $row['instance_type'] ?? 'document';
            $record_id = (int) ($row['document_id'] ?? 0);
            $payment = round((float) ($row['payment'] ?? 0), 2);

            if($record_id === 0) continue;

            $key = "{$instance_type}-{$record_id}";

            if(isset($documents[$key])) throw new Exception('No puede repetir el mismo comprobante en el link de pago');

            $record = $this->getPaymentInstanceRecord($instance_type, $record_id);

            if($payment <= 0) throw new Exception("El monto a pagar de {$record->number_full} debe ser mayor a 0");

            if((int) $record->customer_id !== (int) $person->id) throw new Exception("El comprobante {$record->number_full} no pertenece al cliente seleccionado");

            if($record->isVoidedOrRejected()) throw new Exception("El comprobante {$record->number_full} está anulado o rechazado");

            $pending = $this->getPendingPayment($record);

            if($payment > $pending) throw new Exception("El monto a pagar de {$record->number_full} supera el saldo pendiente ({$pending})");

            $documents[$key] = [
                'instance_type' => $instance_type,
                'record' => $record,
                'payment' => $payment,
            ];

        }

        return array_values($documents);

    }


    /**
     *
     * Obtener el total del link en base a los montos aplicados a los comprobantes
     *
     * @param  array $documents
     * @return float
     */
    private function getTotalDocuments($documents)
    {
        return round(array_sum(array_column($documents, 'payment')), 2);
    }


    /**
     *
     * Registrar/Actualizar link de pago desde el listado
     *
     * @param  PaymentLinkRequest $request
     * @return array
     */
    public function storeWithoutPayment(PaymentLinkRequest $request)
    {

        $id = $request->input('id');
        $record = PaymentLink::firstOrNew(['id' => $id]);

        $record->fill([
            'user_id' => auth()->id(),
            'uuid' => Str::uuid()->toString(),
            'fiscal_environment' => $record->fiscal_environment ?? Company::select('fiscal_environment')->firstOrFail()->fiscal_environment,
            'payment_link_type_id' => $request->payment_link_type_id,
            'total' => $request->total,
        ]);

        $record->save();

        return [
            'success' => true,
            'message' => $id ? 'Link actualizado con éxito' : 'Link generado con éxito'
        ];

    }


    /**
     *
     * Buscar link de pago desde listado
     *
     * @param  int $id
     * @return PaymentLinkResource
     */
    public function recordWithoutPayment($id)
    {
        return new PaymentLinkResource(PaymentLink::findOrFail($id));
    }


    /**
     *
     * Buscar transacciones
     *
     * @param  int $id
     * @return array
     */
    public function transactions($id)
    {
        $payment_link = PaymentLink::findOrFail($id);

        return $payment_link->transactions->transform(function($row){
                    return $row->getRowResource();
                });
    }


    /**
     *
     * Eliminar link de pago
     *
     * @param  int $id
     * @return array
     */
    public function destroy($id)
    {
        $payment_link = PaymentLink::findOrFail($id);

        if($payment_link->transactions->count() > 0)
        {
            return [
                'success' => false,
                'message' => 'El link de pago tiene transacciones relacionadas'
            ];
        }

        if($payment_link->is_paid)
        {
            return [
                'success' => false,
                'message' => 'El link de pago ya fue pagado, tiene pagos registrados'
            ];
        }

        $payment_link->delete();

        return [
            'success' => true,
            'message' => 'Link de pago eliminado con éxito'
        ];
    }


    /**
     *
     * Consultar y validar estado Aceptado de la transacción de mercado pago
     *
     * Si la transacción fue aceptada se marca el link como pagado y se registran los pagos
     *
     * @param  Request $request
     * @return array
     */
    public function queryTransactionState(Request $request)
    {

        $payment_link = PaymentLink::findOrFail($request->id);

        if($payment_link->isTransactionApproved())
        {
            $payment_link->query_transaction = true;
            $payment_link->update();

            $message = 'La transacción asociada tiene estado Aceptado.';

            if(!$payment_link->is_paid)
            {
                $total_payments = $this->setPaymentLinkAsPaid($payment_link);
                $message .= " Se registraron {$total_payments} pago(s).";
            }

            return [
                'success' => true,
                'message' => $message
            ];
        }

        return [
            'success' => false,
            'message' => $payment_link->transactions->count() == 0 ? 'No se encontraron transacciones asociadas.' : 'La transacción asociada no tiene estado Aceptado.'
        ];

    }


    /**
     * Mostrar formulario público del link de pago
     *
     * @param  string $uuid
     * @param  string $payment_link_type_id
     * @param  float $total
     */
    public function publicPaymentLink($uuid, $payment_link_type_id, $input_total)
    {

        $this->validatePublicParams($payment_link_type_id, $input_total);
        $payment_link = $this->getPublicPaymentLink($payment_link_type_id, $uuid);
        $company = $this->getPublicDataCompany();
        $payment_configuration = PaymentConfiguration::getPublicRowResource();

        $apply_conversion = false;
        $total = $this->getTotal($payment_link, $input_total, $apply_conversion);

        return view('payment::payment_links.public.index', compact('payment_link', 'company', 'payment_configuration', 'total', 'apply_conversion'));

    }


    /**
     * Enviar correo
     *
     * @param  Request $request
     * @return array
     */
    public function email(Request $request)
    {

        $company = $this->getPublicDataCompany();

        Mail::to($request->customer_email)->send(new PaymentLinkEmail($company, $request->user_payment_link));

        return [
            'success' => true,
            'message' => 'El correo fue enviado satisfactoriamente'
        ];

    }


    /**
     * Cargar voucher
     *
     * @param  Request $request
     * @return array
     */
    public function uploadedFile(Request $request)
    {

        $validate_upload = UploadFileHelper::validateUploadFile($request, 'file', 'jpg,jpeg,png,svg,webp');
        if(!$validate_upload['success']) return $validate_upload;

        if ($request->hasFile('file'))
        {

            $payment_link = PaymentLink::findOrFail($request->id);

            $new_request = [
                'file' => $request->file('file'),
                'type' => $request->input('type'),
            ];

            $temp_file = UploadFileHelper::getTempFile($new_request);

            if($temp_file['success'])
            {
                $filename = UploadFileHelper::uploadFileFromTempFile('payment_links', $temp_file['data']['filename'], $temp_file['data']['temp_path'], $payment_link->id);
                $payment_link->uploaded_filename = $filename;
                $payment_link->save();

                return [
                    'success' => true,
                    'message' => 'Archivo cargado correctamente',
                ];

            }
        }

        return [
            'success' => false,
            'message' =>  __('app.actions.upload.error'),
        ];
    }

}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
