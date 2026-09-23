<?php

namespace App\CoreFacturalo;

use App\Http\Controllers\Tenant\EmailController;
use App\Models\Tenant\DispatchItem;
use App\Services\SalesDocumentTypePolicy;
use App\Services\SalesCustomerIdentityPolicy;
use App\Services\LocalFiscalDocumentPolicy;
use Exception;
use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use App\Traits\KardexTrait;
use App\Models\Tenant\Voided;
use App\Models\Tenant\Company;
use App\Models\Tenant\Establishment;
use Mpdf\Config\FontVariables;
use App\Models\Tenant\Dispatch;
use App\Models\Tenant\Document;
use App\Models\Tenant\Retention;
use Mpdf\Config\ConfigVariables;
use App\Models\Tenant\Perception;
use App\Mail\Tenant\DocumentEmail;
use App\Models\Tenant\Configuration;
use Modules\Finance\Traits\FinanceTrait;
use App\CoreFacturalo\Helpers\QrCode\QrCodeGenerate;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use Modules\Inventory\Models\Warehouse;
use App\CoreFacturalo\Requests\Inputs\Functions;
use App\Models\Tenant\PurchaseSettlement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Traits\FilePaymentTrait;


/**
 * Class Facturalo
 *
 * @package App\CoreFacturalo
 */
class Facturalo
{
    use StorageDocument, FinanceTrait, KardexTrait, FilePaymentTrait;

    const REGISTERED = '01';
    const SENT = '03';
    const ACCEPTED = '05';
    const OBSERVED = '07';
    const REJECTED = '09';
    const CANCELING = '13';
    const VOIDED = '11';

    protected $configuration;
    protected $company;
    protected $document;
    protected $type;
    protected $actions;
    protected $response;
    protected $apply_change;

    // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
    private ?int $fiscalReservationId = null;
    private bool $newFiscalRegistration = false;

    public function wasNewFiscalRegistration(): bool
    {
        return $this->newFiscalRegistration;
    }

    /** Caller resolves the authorized channel/profile and retains the same key on retries. */
    public function saveFiscal(array $inputs, int $profileId, string $operationKey, string $fingerprint, string $channel, ?int $deviceGroupId = null, ?array $orderSource = null): self
    {
        $this->newFiscalRegistration = false;
        if (!in_array($inputs['type'] ?? null, ['invoice', 'credit', 'debit', 'dispatch'], true) || !empty($inputs['id'])) {
            throw new \DomainException('Este registro fiscal requiere una factura, nota u orden de entrega nueva.');
        }
        if ($orderSource !== null && ($inputs['type'] !== 'invoice' || !empty($inputs['dispatch_id']))) {
            throw new \DomainException('El pedido debe convertirse directamente en una factura.');
        }
        $this->actions = $inputs['actions'] ?? [];
        $this->type = $inputs['type'];
        $reservation = (new \App\Services\Fiscal\FiscalCommercialService($this->company->getConnection()))->register(
            $profileId, $operationKey, $fingerprint, (int) $inputs['establishment_id'], $channel,
            function (array $identifiers, int $reservationId) use ($inputs, $operationKey, $orderSource): int {
                $this->newFiscalRegistration = true;
                $this->fiscalReservationId = $reservationId;
                try {
                    if ($orderSource !== null) {
                        return \App\Services\Fiscal\FiscalOrderConversion::register(
                            $this->company->getConnection(), array_replace($orderSource, ['establishment_id' => (int) $inputs['establishment_id']]), $operationKey,
                            function (object $order) use ($inputs, $identifiers): int {
                                $this->save(\App\Services\Fiscal\FiscalOrderStockReservation::applyWarehouses(array_replace($inputs, $identifiers), $order));
                                return (int) $this->document->id;
                            }
                        );
                    }
                    if (in_array($inputs['type'], ['credit', 'debit'], true)) {
                        $db = $this->company->getConnection();
                        $actor = auth()->user();
                        if (!$actor instanceof \App\Models\Tenant\User) {
                            throw new \DomainException('La nota requiere un usuario tenant autorizado.');
                        }
                        $reference = \App\Services\Fiscal\FiscalNoteReference::capture($db, $inputs['note'], array_replace($inputs, $identifiers), $actor);
                        $reservation = $db->table('fiscal_number_reservations')->find($reservationId);
                        $snapshot = json_decode($reservation->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
                        $snapshot['affected_document'] = $reference;
                        $db->table('fiscal_number_reservations')->where('id', $reservationId)->update(['fiscal_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
                    }
                    if (!empty($inputs['technical_service_id']) && $inputs['type'] === 'invoice') {
                        $actor = auth()->user();
                        if (!$actor instanceof \App\Models\Tenant\User) throw new \DomainException('La conversión requiere un usuario tenant.');
                        \App\Services\Fiscal\FiscalTechnicalServiceConversion::assertAvailable(
                            $this->company->getConnection(), array_replace($inputs, $identifiers), $actor);
                    }
                    if (!empty($inputs['dispatch_id']) && $inputs['type'] === 'invoice') {
                        $actor = auth()->user();
                        if (!$actor instanceof \App\Models\Tenant\User) {
                            throw new \DomainException('La conversión requiere un usuario tenant autorizado.');
                        }
                        return \App\Services\Fiscal\FiscalDispatchConversion::register(
                            $this->company->getConnection(), array_replace($inputs, $identifiers), $actor,
                            function () use ($inputs, $identifiers): int {
                                $this->save(array_replace($inputs, $identifiers));
                                return (int) $this->document->id;
                            }
                        );
                    }
                    if ((!empty($inputs['sale_note_id']) || !empty($inputs['sale_notes_relateds'])) && $inputs['type'] === 'invoice') {
                        $db = $this->company->getConnection();
                        $actor = auth()->user();
                        if (!$actor instanceof \App\Models\Tenant\User) throw new \DomainException('La conversión requiere un usuario tenant.');
                        $sourceIds = \App\Services\Fiscal\FiscalSaleNoteConversion::assertAvailable($db, array_replace($inputs, $identifiers), $actor);
                        $inputs = \App\Services\Fiscal\FiscalSaleNoteEconomics::apply($inputs,
                            \App\Services\Fiscal\FiscalSaleNoteEconomics::capture($db->table('sale_notes')->whereIn('id', $sourceIds)->orderBy('id')->get()));
                        $this->save(array_replace($inputs, $identifiers, ['payments' => []]));
                        \App\Services\Fiscal\FiscalSaleNotePaymentAllocation::applyMany($db, $sourceIds, (int) $this->document->id);
                        $this->savePayments($this->document, $inputs['payments'] ?? []);
                        foreach ($this->document->payments()->whereNull('source_sale_note_payment_id')
                            ->whereHas('global_payment', fn ($query) => $query->where('destination_type', \App\Models\Tenant\Cash::class))->get() as $payment) {
                            $this->createCashDocumentPayment($payment, true);
                        }
                        $paid = $db->table('document_payments')->where('document_id', $this->document->id)->sum('payment');
                        $this->document->update(['total_canceled' => bccomp((string) $paid, (string) $this->document->total, 2) >= 0]);
                        $db->table('sale_notes')->whereIn('id', $sourceIds)->update(['document_id' => $this->document->id, 'changed' => true]);
                        $this->document = $this->document->fresh();
                        return (int) $this->document->id;
                    }
                    $this->save(array_replace($inputs, $identifiers));
                    return (int) $this->document->id;
                } finally {
                    $this->fiscalReservationId = null;
                }
            }, $deviceGroupId, function (object $reservation) use ($inputs): void {
                $snapshot = json_decode($reservation->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
                if ($snapshot['mode'] === 'free_form') {
                    // Validate the exact linked draft while its identifiers and effects are still uncommitted.
                    \App\Services\Fiscal\FiscalPdfData::assertItemCapacity($snapshot, count($inputs['items'] ?? []));
                    $this->createPdf(null, null, null, 'validate');
                }
            }
        );
        $this->document = $this->type === 'dispatch' ? Dispatch::findOrFail($reservation->dispatch_id) : Document::findOrFail($reservation->document_id);
        return $this;
    }

    private function createDocument(array $inputs): Document
    {
        $document = new Document($inputs);
        if ($this->fiscalReservationId !== null) {
            $document->useFiscalReservation($this->fiscalReservationId);
        }
        $document->save();
        return $document;
    }
    // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########

    public function __construct()
    {
        // ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
        $this->configuration = Configuration::first();
        $this->company = Company::active();
        $this->actions = [];
        $this->response = LocalFiscalDocumentPolicy::registeredResponse();
        // ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
    }

    public function setDocument($document)
    {
        $this->document = $document;
    }

    public function getDocument()
    {
        return $this->document;
    }

    public function getActions()
    {
        return $this->actions;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function save($inputs)
    {
        $this->actions = array_key_exists('actions', $inputs)?$inputs['actions']:[];
        $this->type = $inputs['type'];

        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        if ($this->type === 'invoice') {
            SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed($inputs['document_type_id'] ?? null);
        }
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

        // ######## INICIO POLITICA IDENTIDAD ACTIVA EN VENTAS ########
        if (in_array($this->type, ['invoice', 'credit', 'debit'], true)) {
            SalesCustomerIdentityPolicy::assertCustomerAllowed($inputs['customer_id'] ?? null);
        }
        // ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########

        switch ($this->type) {
            case 'debit':
            case 'credit':
                $document = $this->createDocument($inputs);
                $document->note()->create($inputs['note']);
                foreach ($inputs['items'] as $row) {
                    $document->items()->create($row);
                }
                if($this->type === 'credit') $this->saveFee($document, $inputs['fee']);
                $this->document = Document::find($document->id);
                break;
            case 'invoice':
                $document = $this->createDocument($inputs);
                $this->savePayments($document, $inputs['payments']);
                $this->saveFee($document, $inputs['fee']);
                foreach ($inputs['items'] as $row) {
//                    $purchase_unit_price = $row['purchase_unit_price'];
//                    $row['item']['purchase_unit_price'] = $purchase_unit_price;
                    $document->items()->create($row);
                    // $row['document_id']=  $document->id;
                    // $item = new DocumentItem($row);
                    // $item->push();
                }
                $this->updatePrepaymentDocuments($inputs);
                if($inputs['hotel']) $document->hotel()->create($inputs['hotel']);
                if($inputs['transport']) $document->transport()->create($inputs['transport']);
                $document->invoice()->create($inputs['invoice']);
                $this->document = Document::find($document->id);
                break;
            case 'voided':
                $document = Voided::create($inputs);
                foreach ($inputs['documents'] as $row) {
                    $document->documents()->create($row);
                }
                $this->document = Voided::find($document->id);
                break;
            case 'retention':
                $document = Retention::create($inputs);
                foreach ($inputs['documents'] as $row) {
                    $document->documents()->create($row);
                }
                $this->document = Retention::find($document->id);
                break;
            case 'perception':
                $document = Perception::create($inputs);
                foreach ($inputs['documents'] as $row) {
                    $document->documents()->create($row);
                }
                $this->document = Perception::find($document->id);
                break;
            case 'purchase_settlement':
                $document = PurchaseSettlement::create($inputs);
                foreach ($inputs['items'] as $row) {
                    $document->items()->create($row);
                }
                $this->document = PurchaseSettlement::find($document->id);
                break;
            default:
                // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
                if ($this->fiscalReservationId !== null) {
                    $document = new Dispatch($inputs);
                    $document->useFiscalReservation($this->fiscalReservationId);
                    $document->save();
                } else {
                    if (!empty($inputs['id']) && $this->company->getConnection()->table('fiscal_number_reservations')->where('dispatch_id', $inputs['id'])->exists()) {
                        throw new \DomainException('La orden ya tiene una reserva fiscal y no puede reescribirse.');
                    }
                    DispatchItem::query()->where('dispatch_id', $inputs['id'])->delete();
                    $document = Dispatch::query()->updateOrCreate(['id' => $inputs['id']], $inputs);
                }
                // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
                foreach ($inputs['items'] as $row) {
                    $document->items()->create($row);
                }
                $this->document = Dispatch::find($document->id);
                break;
        }
        return $this;
    }

    public function sendEmail()
    {
        $send_email = ($this->actions['send_email'] === true) ? true : false;

        if($send_email){

            $company = $this->company;
            $document = $this->document;
            $email = ($this->document->customer) ? $this->document->customer->email : $this->document->supplier->email;
            $mailable =new DocumentEmail($company, $document);
            $id =  $document->id;
            $model = __FILE__.";;".__LINE__;
            $sendIt = EmailController::SendMail($email, $mailable, $id, $model);
            /*
            Configuration::setConfigSmtpMail();
            $array_email = explode(',', $email);
            if (count($array_email) > 1) {
                foreach ($array_email as $email_to) {
                    $email_to = trim($email_to);
                if(!empty($email_to)) {
                        Mail::to($email_to)->send(new DocumentEmail($company, $document));
                    }
                }
            } else {
                Mail::to($email)->send(new DocumentEmail($company, $document));
            }
            */

        }
    }

    public function updateState($state_type_id)
    {
        // ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
        $this->document->update(['state_type_id' => $state_type_id]);
        // ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
    }

    private function renderMpdfSafely(callable $callback)
    {
        $previous = set_error_handler(function ($severity, $message, $file = '', $line = 0) use (&$previous) {
            if ($severity === E_WARNING && strpos($message, 'Trying to access array offset on') !== false) {
                return true;
            }

            return $previous ? ($previous)($severity, $message, $file, $line) : false;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    public function createPdf($document = null, $type = null, $format = null, $output = 'pdf') {
        ini_set("pcre.backtrack_limit", "5000000");
        $template = new Template();
        $pdf = new Mpdf();

        $format_pdf = $this->actions['format_pdf'] ?? null;

        $this->document = ($document != null) ? $document : $this->document;
        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        $fiscalPdfData = \App\Services\Fiscal\FiscalPdfData::forDocument($this->document);
        $this->company = \App\Services\Fiscal\FiscalPdfData::issuerForPrint($this->company, $fiscalPdfData);
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
        $format_pdf = ($format != null) ? $format : $format_pdf;
        $this->type = ($type != null) ? $type : $this->type;

        // Usa el logo del establecimiento cuando no esté configurado el logo de la empresa, para que los PDFs de las facturas siempre muestren el logo de la sucursal.
        if (empty($this->company->logo) && $this->document && $this->document->establishment && !empty($this->document->establishment->logo)) {
            $this->company->logo = $this->document->establishment->logo;
        }

        $height_logo = HelperFacturalo::logo_heigth($this->document->establishment_id, $this->company);
        $heightQr = 95;
        if($this->document->document_type_id === '09') {
            if($this->document->qr_url) {
                $qrCode = new QrCodeGenerate();
                $this->document->qr = $qrCode->displayPNGBase64($this->document->qr_url);
                $heightQr = $qrCode->getHeightQr();
            }
        }


        $base_pdf_template = Establishment::find($this->document->establishment_id)->template_pdf;
        if (($format_pdf === 'ticket') OR
            ($format_pdf === 'ticket_58'))
        {
            $base_pdf_template = Establishment::find($this->document->establishment_id)->template_ticket_pdf;
        }

        $pdf_margin_top = 15;
        $pdf_margin_right = 15;
        $pdf_margin_bottom = 15;
        $pdf_margin_left = 15;

        if (in_array($base_pdf_template, ['full_height', 'default3_new','rounded'])) {
            $pdf_margin_top = 5;
            $pdf_margin_right = 5;
            $pdf_margin_bottom = 5;
            $pdf_margin_left = 5;
        }
        if ($base_pdf_template === 'blank' && in_array($this->document->document_type_id, ['09'])) {
            $pdf_margin_top = 15;
            $pdf_margin_right = 5;
            $pdf_margin_bottom = 15;
            $pdf_margin_left = 14;
        }
        if (substr($base_pdf_template, 0, 7) === 'facnova') {
            $pdf_margin_top = 10;
            $pdf_margin_right = 4;
            $pdf_margin_bottom = 5;
            $pdf_margin_left = 15;
        }

        $optional_configuration = [
            'enabled_price_items_dispatch' => $this->configuration->enabled_price_items_dispatch,
            'is_preview' => false,
        ];

        $html = $template->pdf($base_pdf_template, $this->type, $this->company, $this->document, $format_pdf, $optional_configuration);

        if (($format_pdf === 'ticket') OR
            ($format_pdf === 'ticket_58'))
        {
            $base_pdf_template = Establishment::find($this->document->establishment_id)->template_ticket_pdf;

            $width = ($format_pdf === 'ticket_58') ? 56 : 72 ;
            if(config('tenant.enabled_template_ticket_80')) $width = 76;
            if(config('tenant.enabled_template_ticket_70')) $width = 70;

            $company_name      = (strlen($this->company->name) / 20) * 10;
            $company_address   = (strlen($this->document->establishment->address) / 30) * 10;
            $company_number    = $this->document->establishment->telephone != '' ? '10' : '0';
            $customer_name = 0;
            $customer_address = 0;
            $customer_department_id = 0;
            if($this->document->customer) {
                $customer_name     = strlen($this->document->customer->name) > '25' ? '10' : '0';
                $customer_address  = (strlen($this->document->customer->address) / 200) * 10;
                $customer_department_id  = ($this->document->customer->department_id == 16) ? 20:0;
            }
            $p_order           = $this->document->purchase_order != '' ? '10' : '0';

            $total_prepayment = $this->document->total_prepayment != '' ? '10' : '0';
            $total_discount = $this->document->total_discount != '' ? '10' : '0';
            $was_deducted_prepayment = $this->document->was_deducted_prepayment ? '10' : '0';

            $total_exportation = $this->document->total_exportation != '' ? '10' : '0';
            $total_free        = $this->document->total_free != '' ? '10' : '0';
            $total_unaffected  = $this->document->total_unaffected != '' ? '10' : '0';
            $total_exonerated  = $this->document->total_exonerated != '' ? '10' : '0';
            $total_taxed       = $this->document->total_taxed != '' ? '10' : '0';
            $perception       = $this->document->perception != '' ? '10' : '0';
            $quantity_rows     = count($this->document->items) + $was_deducted_prepayment;
            $document_payments     = count($this->document->payments ?? []);
            $document_transport     = ($this->document->transport) ? 30 : 0;
            $document_retention     = ($this->document->retention) ? 10 : 0;

            $extra_by_item_additional_information = 0;
            $extra_by_item_description = 0;
            $discount_global = 0;
            foreach ($this->document->items as $it) {
                if(strlen($it->item->description)>100){
                    $extra_by_item_description +=24;
                }
                if ($it->discounts) {
                    $discount_global = $discount_global + 1;
                }
                if($it->additional_information){
                    $extra_by_item_additional_information += count($it->additional_information) * 5;
                }
            }
            $legends = $this->document->legends != '' ? '10' : '0';

            $quotation_id = ($this->document->quotation_id) ? 15:0;

            // Calcular el height sobre terminos y condiciones
            $terms_condition = preg_replace('/<\/[a-zA-Z0-9]+>/', "\n", $this->document->terms_condition);
            $terms_condition = preg_replace('/<[^\/][^>]*>/', '', $terms_condition);
            $terms_condition = html_entity_decode($terms_condition);
            $terms_condition = preg_replace("/[\r\n]+/", "\n", $terms_condition);
            $terms_condition = trim($terms_condition);
            $linesTerms = explode("\n", $terms_condition);
            $totalLinesTerms = 0;

            foreach ($linesTerms as $line) {
                $line = trim($line);
                if (strlen($line) > 0) {
                    $wrapped = ceil(strlen($line) / 35);
                    $totalLinesTerms += $wrapped;
                }
            }
            $height_terms = $totalLinesTerms * 2;
            $height_legend = 0;

            $append_height = 0;

            if($this->type === 'dispatch')
            {
                $append_height = 15;
                $this->appendHeightFromDispatch($append_height, $format, $this->document);
            }
            $heightQr = ($heightQr - 95);
            if ($format_pdf === 'ticket_58') $heightQr += 10; // evita una pagina en blanco cuando esta el qr

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [
                    $width,
                    80 +
                    $height_terms +
                     $heightQr +
                     $height_logo +
                    (($quantity_rows * 8) + $extra_by_item_description) +
                    ($document_payments * 8) +
                    ($discount_global * 8) +
                    $company_name +
                    $company_address +
                    $company_number +
                    $customer_name +
                    $customer_address +
                    $p_order +
                    $legends +
                    $total_exportation +
                    $total_free +
                    $total_unaffected +
                    $total_exonerated +
                    $perception +
                    $total_taxed+
                    $total_prepayment +
                    $total_discount +
                    $was_deducted_prepayment +
                    $customer_department_id+
                    $quotation_id+
                    $extra_by_item_additional_information+
                    $height_legend+
                    $document_transport+
                    $append_height+
                    $document_retention
                ],
                'margin_top' => 0,
                'margin_right' => 1,
                'margin_bottom' => 0,
                'margin_left' => 1,
                'default_font' => 'monospace'
            ]);
        }else if($format_pdf === 'a5'){

            $company_name      = (strlen($this->company->name) / 20) * 10;
            $company_address   = (strlen($this->document->establishment->address) / 30) * 10;
            $company_number    = $this->document->establishment->telephone != '' ? '10' : '0';
            $customer_name     = strlen($this->document->customer->name) > '25' ? '10' : '0';
            $customer_address  = (strlen($this->document->customer->address) / 200) * 10;
            $p_order           = $this->document->purchase_order != '' ? '10' : '0';

            $total_exportation = $this->document->total_exportation != '' ? '10' : '0';
            $total_free        = $this->document->total_free != '' ? '10' : '0';
            $total_unaffected  = $this->document->total_unaffected != '' ? '10' : '0';
            $total_exonerated  = $this->document->total_exonerated != '' ? '10' : '0';
            $total_taxed       = $this->document->total_taxed != '' ? '10' : '0';
            $quantity_rows     = count($this->document->items);

            $extra_by_item_description = 0;
            $discount_global = 0;
            foreach ($this->document->items as $it) {
                if(strlen($it->item->description)>100){
                    $extra_by_item_description +=24;
                }
                if ($it->discounts) {
                    $discount_global = $discount_global + 1;
                }
            }
            $legends = $this->document->legends != '' ? '10' : '0';


            $height = ($quantity_rows * 8) +
                    ($discount_global * 3) +
                    $company_name +
                    $company_address +
                    $company_number +
                    $customer_name +
                    $customer_address +
                    $p_order +
                    $legends +
                    $total_exportation +
                    $total_free +
                    $total_unaffected +
                    $total_exonerated +
                    $total_taxed;
            $diferencia = 148 - (float)$height;

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [
                    210,
                    $diferencia + $height
                    ],
                'margin_top' => 2,
                'margin_right' => 5,
                'margin_bottom' => 20,
                'margin_left' => 5,
                'default_font' => 'arial'
            ]);


        } else {

            if ($base_pdf_template === 'brand') {
                $pdf_margin_top = 93.7;
                $pdf_margin_bottom = 74;
            }
            if ($base_pdf_template === 'blank' && in_array($this->document->document_type_id, ['09'])) {
                $pdf_margin_top = 110;
                $pdf_margin_bottom = 125;
            }

            $pdf_font_regular = config('tenant.pdf_name_regular');
            $pdf_font_bold = config('tenant.pdf_name_bold');

            $templateFontDir = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf'.
                                             DIRECTORY_SEPARATOR.$base_pdf_template.
                                             DIRECTORY_SEPARATOR.'font');

            $regularTtf = $pdf_font_regular
                ? $templateFontDir.DIRECTORY_SEPARATOR.$pdf_font_regular.'.ttf'
                : null;
            $boldTtf = $pdf_font_bold
                ? $templateFontDir.DIRECTORY_SEPARATOR.$pdf_font_bold.'.ttf'
                : null;

            $hasRegularFont = $regularTtf && file_exists($regularTtf);
            $hasBoldFont = $boldTtf && file_exists($boldTtf);

            if ($hasRegularFont) {
                $defaultConfig = (new ConfigVariables())->getDefaults();
                $fontDirs = $defaultConfig['fontDir'];

                $defaultFontConfig = (new FontVariables())->getDefaults();
                $fontData = $defaultFontConfig['fontdata'];

                $customFontData = [
                    'custom_regular' => [
                        'R' => $pdf_font_regular.'.ttf',
                    ],
                    'custom_bold' => [
                        'R' => ($hasBoldFont ? $pdf_font_bold : $pdf_font_regular).'.ttf',
                    ],
                ];

                $pdf = new Mpdf([
                    'fontDir' => array_merge($fontDirs, [$templateFontDir]),
                    'fontdata' => $fontData + $customFontData,
                    'margin_top' => $pdf_margin_top,
                    'margin_right' => $pdf_margin_right,
                    'margin_bottom' => $pdf_margin_bottom,
                    'margin_left' => $pdf_margin_left,
                    'default_font' => 'custom_regular'
                ]);

            } else {
                $pdf = new Mpdf([
                    'margin_top' => $pdf_margin_top,
                    'margin_right' => $pdf_margin_right,
                    'margin_bottom' => $pdf_margin_bottom,
                    'margin_left' => $pdf_margin_left,
                    'default_font' => 'arial'
                ]);
            }
        }

        $path_css = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf'.
                                             DIRECTORY_SEPARATOR.$base_pdf_template.
                                             DIRECTORY_SEPARATOR.'style.css');

        $stylesheet = file_get_contents($path_css);


        // if (($format_pdf != 'ticket') AND ($format_pdf != 'ticket_58')) {
            // dd($base_pdf_template);// = config(['tenant.pdf_template'=> $configuration]);
        if(config('tenant.pdf_template_footer')) {
            $html_footer = '';
            if (($format_pdf != 'ticket') AND ($format_pdf != 'ticket_58')) {
                $html_footer = $template->pdfFooter($base_pdf_template, $this->document);
                $html_footer_legend = "";

                // if(isset($this->configuration->pdf_footer_images)) {
                //     $footer_images = is_string($this->configuration->pdf_footer_images)
                //         ? json_decode($this->configuration->pdf_footer_images, true)
                //         : $this->configuration->pdf_footer_images;

                //     if(is_object($footer_images)) {
                //         $footer_images = json_decode(json_encode($footer_images), true);
                //     }

                //     if(!empty($footer_images)) {
                //         $images_html = '<div style="text-align: center; margin-top: 10px;">';
                //         foreach((array)$footer_images as $image) {
                //             $filename = is_array($image) ? ($image['filename'] ?? null) : ($image->filename ?? null);
                //             if($filename) {
                //                 $image_path = asset('storage/uploads/pdf_footer_images/'.$filename);
                //                 $images_html .= '<img src="'.$image_path.'" style="max-height: 50px; margin: 0 5px;">';
                //             }
                //         }
                //         $images_html .= '</div>';
                //         $html_footer .= $images_html;
                //     }
                // }
            }
            $pdf->SetHTMLFooter($html_footer);
        }
//            $html_footer = $template->pdfFooter();
//            $pdf->SetHTMLFooter($html_footer);
        // }

        if ($base_pdf_template === 'brand') {

            $html_header = $template->pdfHeader($base_pdf_template, $this->company, in_array($this->document->document_type_id, ['09']) ? null : $this->document);
            $pdf->SetHTMLHeader($html_header);

            if (($format_pdf === 'ticket') || ($format_pdf === 'ticket_58') || ($format_pdf === 'a5')) {
                $pdf->SetHTMLHeader("");
                $pdf->SetHTMLFooter("");
            }
        }

        if ($base_pdf_template === 'blank' && in_array($this->document->document_type_id, ['09'])) {

            $html_header = $template->pdfHeader($base_pdf_template, $this->company, $this->document);
            $pdf->SetHTMLHeader($html_header);

            $html_footer_blank = $template->pdfFooterBlank($base_pdf_template, $this->document);
            $pdf->SetHTMLFooter($html_footer_blank);
        }

        if ($base_pdf_template === 'default3_929' && in_array($this->document->document_type_id, ['01'])) {
            // Encabezado específico de Factura para la plantilla #929.
            $html_header = $template->pdfHeader($base_pdf_template, $this->company, $this->document);
            $pdf->SetHTMLHeader($html_header);
            $html_footer = $template->pdfFooter($base_pdf_template, $this->document);
            $pdf->SetHTMLFooter($html_footer);
        }

        if ($base_pdf_template === 'distpatch_pharmacy' && in_array($this->document->document_type_id, ['09'])) {
            // Solo para orden de entrega #1192
            $pdf->setAutoTopMargin = 'stretch'; //margen autommatico
            $pdf->autoMarginPadding  = 0;
            $pdf->setAutoBottomMargin = 'stretch';
            $html_header = $template->pdfHeader($base_pdf_template, $this->company, $this->document);
            $pdf->SetHTMLHeader($html_header);
            $html_footer = $template->pdfFooterDispatch($base_pdf_template, $this->document);
            $pdf->SetHTMLFooter($html_footer);
        }

        // para impresion automatica se requiere el resultado en html ya que es lo que se envia a las funciones de impresión
        if($output == 'html') {
            $path_html = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf'.
                                             DIRECTORY_SEPARATOR.'ticket_html.css');
            $ticket_html = file_get_contents($path_html);
            $pdf->WriteHTML($ticket_html, HTMLParserMode::HEADER_CSS);
            $this->renderMpdfSafely(function () use ($pdf, $html) {
                return $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
            });
            // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
            \App\Services\Fiscal\FiscalPdfData::assertPageCount($fiscalPdfData, $pdf->page);
            // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
            return "<style>".$ticket_html.$stylesheet."</style>".$html;
        }
        else {
            $pdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
            $this->renderMpdfSafely(function () use ($pdf, $html) {
                return $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
            });

            $helper_facturalo = new HelperFacturalo();

            if($helper_facturalo->isAllowedAddDispatchTicketIndividual($format_pdf, $this->type))
            {
                $helper_facturalo->addDocumentDispatchTicket($pdf, $this->company, $this->document, [
                    $template,
                    $base_pdf_template,
                    $width,
                    ($quantity_rows * 8) + $extra_by_item_description +200,
                ], true);
            }

            if($helper_facturalo->isAllowedAddDispatchTicket($format_pdf, $this->type, $this->document))
            {
                $height = ($quantity_rows * 8) + $extra_by_item_description +200;

                $helper_facturalo->addDocumentDispatchTicket($pdf, $this->company, $this->document, [
                    $template,
                    $base_pdf_template,
                    $width,
                    $height
                ]);
            }
        }

        // echo $html_header.$html.$html_footer; exit();
        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        \App\Services\Fiscal\FiscalPdfData::assertPageCount($fiscalPdfData, $pdf->page);
        if ($output === 'validate') {
            return $pdf->page;
        }
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
        $this->uploadFile($this->renderMpdfSafely(function () use ($pdf) {
            return $pdf->output('', 'S');
        }), 'pdf');
        return $this;
    }


    /**
     * Genera una orden de impresión server-side para la app mozo, evitando que
     * el cliente tenga que descargar el PDF, convertirlo a base64 y consumir
     * /print-orders en un segundo roundtrip.
     *
     * Se invoca tras createPdf y antes del envío a SUNAT: el comprobante se
     * imprime sin esperar la respuesta de la administración tributaria.
     *
     * Reutiliza el PDF que createPdf ya generó (formato definido por el cliente
     * en actions.format_pdf); no regenera nada.
     *
     * El PrintOrderObserver publica la orden en Redis al persistir.
     *
     * Activación: actions.auto_print === true (lo envía la app en el payload).
     *
     * @return array{auto_printed: bool, print_order_id: int|null, reason: string|null}
     */
    public function generatePrintOrder()
    {
        $result = ['auto_printed' => false, 'print_order_id' => null, 'reason' => null];

        // Defensa: si createPdf generó un ticket con despacho individual, deja
        // atributos temporales (is_individual / itemChunk) sobre el modelo que
        // NO son columnas. Se limpian para que el envío a SUNAT no intente
        // persistirlos. No afecta el flujo estable (no existen como columna).
        unset($this->document->is_individual, $this->document->itemChunk);

        if (empty($this->actions['auto_print'])) {
            $result['reason'] = 'disabled';
            return $result;
        }

        // Validación de impresión local: misma regla que PrintOrderController@store.
        // Un fallo aquí NO interrumpe el documento: degrada al fallback del mozo.
        $config = \Modules\Restaurant\Models\RestaurantConfiguration::first();
        if ($config && $config->print_local_enabled && $config->printer_public_ip) {
            $clientIp = $this->actions['client_public_ip'] ?? null;
            if ($clientIp !== $config->printer_public_ip) {
                $result['reason'] = 'ip_mismatch';
                return $result;
            }
        }

        // Resolver impresora: la enviada o la predeterminada
        $printerName = $this->actions['name_printer'] ?? null;
        if (empty($printerName)) {
            $default = \Modules\Restaurant\Models\Printer::where('active', true)
                ->where('is_default', true)
                ->first();
            if (!$default) {
                $result['reason'] = 'no_printer';
                return $result;
            }
            $printerName = $default->name;
        }

        try {
            // El PDF ya fue generado por createPdf en el formato que indica el
            // payload (actions.format_pdf). Se reutiliza tal cual, sin regenerar:
            // el formato a imprimir lo decide el cliente desde el JSON.
            $pdf_b64 = base64_encode($this->getStorage($this->document->filename, 'pdf'));

            $order = \Modules\Restaurant\Models\PrintOrder::create([
                'name_printer' => $printerName,
                'status'       => 0,
                'pdf_b64'      => $pdf_b64,
            ]);

            $result['auto_printed']   = true;
            $result['print_order_id'] = $order->id;
        } catch (\Throwable $e) {
            Log::error("generatePrintOrder: error generando orden de impresión — {$e->getMessage()}");
            $result['reason'] = 'error';
        }

        return $result;
    }



    /**
     *
     * Agregar altura para ticket de orden de entrega
     *
     * @param  float $append_height
     * @param  $document
     * @return void
     */
    private function appendHeightFromDispatch(&$append_height, $format, $document)
    {
        $base_height = 0;
        $observations = 0;
        $data_affected_document = 0;
        $transfer_reason_type = 0;
        $transport_mode_type = 0;
        $driver = 0;
        $license_plate = 0;
        $secondary_license_plates = 0;

        if($format == 'ticket_58')
        {
            $base_height = 80;
            if($document->data_affected_document) $data_affected_document = 25;
        }
        else
        {
            $base_height = 50;
            if($document->data_affected_document) $data_affected_document = 20;
        }

        if($document->observations) $observations = 30;
        if($document->transfer_reason_type) $transfer_reason_type = 6;
        if($document->transport_mode_type) $transport_mode_type = 6;
        if($document->license_plate) $license_plate = 5;
        if($document->secondary_license_plates) $secondary_license_plates = 5;

        if($document->driver)
        {
            if($document->driver->number)  $driver += 5;
            if($document->driver->license)  $driver += 5;
        }

        $append_height += $base_height + $observations + $data_affected_document + $transfer_reason_type + $transport_mode_type + $driver
                            + $license_plate + $secondary_license_plates;

    }


    public function uploadFile($file_content, $file_type)
    {
        $this->uploadStorage($this->document->filename, $file_content, $file_type);
    }





    private function updatePrepaymentDocuments($inputs){
        // dd($inputs);

        if(isset($inputs['prepayments'])) {

            foreach ($inputs['prepayments'] as $row) {

                $fullnumber = explode('-', $row['number']);
                $series = $fullnumber[0];
                $number = $fullnumber[1];

                $doc = Document::where([['series',$series],['number',$number]])->first();

                if($doc){

                    $total = $row['total'];
                    $balance = $doc->pending_amount_prepayment - $total;
                    $doc->pending_amount_prepayment = $balance;

                    if($balance <= 0){
                        $doc->was_deducted_prepayment = true;
                    }

                    $doc->save();

                }
            }
        }
    }

    private function savePayments($document, $payments, $isUpdate = false)
    {
        $total = $document->total;
        $balance = $total - collect($payments)->sum('payment');

        $search_cash = ($balance < 0) ? collect($payments)->firstWhere('payment_method_type_id', '01') : null;
        $this->apply_change = false;

        if($balance < 0 && $search_cash){

            $payments = collect($payments)->map(function($row) use($balance){

                $change = null;
                $payment = $row['payment'];

                if($row['payment_method_type_id'] == '01' && !$this->apply_change){
                    $change = abs($balance);
                    $payment = $row['payment'] - abs($balance);
                    $this->apply_change = true;

                }

                return [
                    "id" => null,
                    "document_id" => null,
                    "sale_note_id" => null,
                    "date_of_payment" => $row['date_of_payment'],
                    "payment_method_type_id" => $row['payment_method_type_id'],
                    "reference" => $row['reference'],
                    "payment_destination_id" => isset($row['payment_destination_id']) ? $row['payment_destination_id'] : null,
                    "change" => $change,
                    "payment" => $payment,
                    "payment_received" => isset($row['payment_received']) ? $row['payment_received'] : null,
                ];

            });
        }

        foreach ($payments as $row) {
            if($balance < 0 && !$this->apply_change){
                $row['change'] = abs($balance);
                $row['payment'] = $row['payment'] - abs($balance);
                $this->apply_change = true;
            }

            $record = $document->payments()->create($row);

            // para carga de voucher
            $this->saveFilesFromPayments($row, $record, 'documents');

            //considerar la creacion de una caja chica cuando recien se crea el cliente
            if(isset($row['payment_destination_id'])){
                $this->createGlobalPayment($record, $row);
                if($isUpdate){
                    $this->createCashDocumentPayment($record,true);
                }
            }

        }
    }

    /**
     * @param array $inputs
     * @param int   $id
     */
    public function update($inputs,$id)
    {
        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        if ($this->company->getConnection()->table('fiscal_number_reservations')->where('document_id', $id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'document' => 'El comprobante ya tiene una reserva fiscal y su contenido no puede reescribirse. Use el procedimiento de corrección correspondiente.',
            ]);
        }
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########

        $this->actions = array_key_exists('actions', $inputs)?$inputs['actions']:[];
        $this->type = @$inputs['type'];
        // dd($inputs);
        switch ($this->type) {
            case 'invoice':
                $document = Document::find($id);
                // si cambia la serie
                if($inputs['series'] !== $document->series){
                    // se consulta el ultimo numero de la nueva serie
                    $last_number = Document::getLastNumberBySerie($inputs['series']);
                    // se actualiza el numero actual en $imputs
                    $inputs['number'] = $last_number + 1;
                    // cambiamos el filename
                    $inputs['filename'] = Functions::filename(Company::active(), $inputs['document_type_id'], $inputs['series'], $inputs['number']);
                }
                $document->fill($inputs);
                $document->save();

                $document->payments()->each(function ($payment) {
                    if (method_exists($payment, 'cashDocumentPayments')) {
                        $payment->cashDocumentPayments()->delete();
                    }
                });
                $document->payments()->delete();
                $this->savePayments($document, $inputs['payments'],true);

                $document->fee()->delete();
                $this->saveFee($document, $inputs['fee']);

                $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();

                foreach ($document->items as $it) {
                    //se usa el evento deleted del modelo - InventoryKardexServiceProvider document_item_delete
                    $it->delete();
                    // $this->restoreStockInWarehpuse($it->item_id, $warehouse->id, $it->quantity);
                }

                // Al editar el item, borra los registros anteriores
                // foreach ($document->items()->get() as $item) {
                //     /** @var \App\Models\Tenant\DocumentItem $item */
                //     DocumentItem::UpdateItemWarehous($item,'deleted');
                //     $item->delete();
                // }
                // $document->items()->delete();

                foreach ($inputs['items'] as $row) {
                    $document->items()->create($row);
                }

                $this->updatePrepaymentDocuments($inputs);

                if($inputs['hotel']){
                    $document->hotel()->update($inputs['hotel']);
                }

                $document->invoice()->update($inputs['invoice']);
                $this->document = Document::find($document->id);
                break;
        }
    }

    /**
     * @param array $actions
     *
     * @return $this
     */
    public function setActions($actions = []){
        $this->actions = $actions;;
        return $this;
    }
    /**
     * Carga los elementos segun corresponda.
     *
     * @todo Falta determinar Document para credit e invoice
     *
     * @param      $id
     * @param null $type
     *
     * @return \App\CoreFacturalo\Facturalo
     */
    public function loadDocument($id, $type = null){
        $this->type = $type;
        switch ($this->type) {
            case 'debit':
            case 'credit':
                $this->document = Document::find($id);
                break;
            case 'invoice':
                $this->document = Document::find($id);
                break;
            case 'voided':
                $this->document = Voided::find($id);
                break;
            case 'retention':
                $this->document = Retention::find($id);
                break;
            case 'perception':
                $this->document = Perception::find($id);
                break;
            default:
                $this->document = Dispatch::find($id);
                break;
        }
        return $this;
    }

    private function saveFee($document, $fee)
    {
        foreach ($fee as $row) {
            $document->fee()->create($row);
        }
    }

    public function previewPdf($document = null, $type = null, $format = null, $output = 'pdf') {
        ini_set("pcre.backtrack_limit", "5000000");
        $template = new Template();
        $pdf = new Mpdf();

        $format_pdf = $this->actions['format_pdf'] ?? null;

        $this->document = ($document != null) ? $document : $this->document;
        $format_pdf = ($format != null) ? $format : $format_pdf;
        $this->type = ($type != null) ? $type : $this->type;

        if($this->document->document_type_id === '09') {
            if($this->document->qr_url) {
                $qrCode = new QrCodeGenerate();
                $this->document->qr = $qrCode->displayPNGBase64($this->document->qr_url);
            }
        }

        $base_pdf_template = Establishment::find($this->document->establishment_id)->template_pdf;
        if (($format_pdf === 'ticket') OR
            ($format_pdf === 'ticket_58'))
        {
            $base_pdf_template = Establishment::find($this->document->establishment_id)->template_ticket_pdf;
        }

        $pdf_margin_top = 15;
        $pdf_margin_right = 15;
        $pdf_margin_bottom = 15;
        $pdf_margin_left = 15;

        if (in_array($base_pdf_template, ['full_height', 'default3_new','rounded'])) {
            $pdf_margin_top = 5;
            $pdf_margin_right = 5;
            $pdf_margin_bottom = 5;
            $pdf_margin_left = 5;
        }
        if ($base_pdf_template === 'blank' && in_array($this->document->document_type_id, ['09'])) {
            $pdf_margin_top = 15;
            $pdf_margin_right = 5;
            $pdf_margin_bottom = 15;
            $pdf_margin_left = 14;
        }
        if (substr($base_pdf_template, 0, 7) === 'facnova') {
            $pdf_margin_top = 10;
            $pdf_margin_right = 4;
            $pdf_margin_bottom = 5;
            $pdf_margin_left = 15;
        }

        $preview_configuration = [
            'is_preview' => true,
        ];
        $html = $template->pdf($base_pdf_template, $this->type, $this->company, $this->document, $format_pdf, $preview_configuration);

        if (($format_pdf === 'ticket') OR
            ($format_pdf === 'ticket_58'))
        {
            $base_pdf_template = Establishment::find($this->document->establishment_id)->template_ticket_pdf;

            $width = ($format_pdf === 'ticket_58') ? 56 : 78 ;
            if(config('tenant.enabled_template_ticket_80')) $width = 76;
            if(config('tenant.enabled_template_ticket_70')) $width = 70;

            $company_name      = (strlen($this->company->name) / 20) * 10;
            $company_address   = (strlen($this->document->establishment->address) / 30) * 10;
            $company_number    = $this->document->establishment->telephone != '' ? '10' : '0';
            $customer_name     = strlen($this->document->customer->name) > '25' ? '10' : '0';
            $customer_address  = (strlen($this->document->customer->address) / 200) * 10;
            $customer_department_id  = ($this->document->customer->department_id == 16) ? 20:0;
            $p_order           = $this->document->purchase_order != '' ? '10' : '0';

            $total_prepayment = (object)$this->document->total_prepayment != '' ? '10' : '0';
            $total_discount = (object)$this->document->total_discount != '' ? '10' : '0';
            $was_deducted_prepayment = $this->document->was_deducted_prepayment ? '10' : '0';

            $total_exportation = (object)$this->document->total_exportation != '' ? '10' : '0';
            $total_free        = (object)$this->document->total_free != '' ? '10' : '0';
            $total_unaffected  = (object)$this->document->total_unaffected != '' ? '10' : '0';
            $total_exonerated  = (object)$this->document->total_exonerated != '' ? '10' : '0';
            $total_taxed       = (object)$this->document->total_taxed != '' ? '10' : '0';
            $perception       = $this->document->perception != '' ? '10' : '0';
            $quantity_rows     = count($this->document->items) + $was_deducted_prepayment;
            $document_payments     = count($this->document->payments ?? []);
            $document_transport     = ($this->document->transport) ? 30 : 0;
            $document_retention     = ($this->document->retention) ? 10 : 0;

            // Calcular el height sobre terminos y condiciones
            $terms_condition = preg_replace('/<\/[a-zA-Z0-9]+>/', "\n", $this->document->terms_condition);
            $terms_condition = preg_replace('/<[^\/][^>]*>/', '', $terms_condition);
            $terms_condition = html_entity_decode($terms_condition);
            $terms_condition = preg_replace("/[\r\n]+/", "\n", $terms_condition);
            $terms_condition = trim($terms_condition);
            $linesTerms = explode("\n", $terms_condition);
            $totalLinesTerms = 0;

            foreach ($linesTerms as $line) {
                $line = trim($line);
                if (strlen($line) > 0) {
                    $wrapped = ceil(strlen($line) / 35);
                    $totalLinesTerms += $wrapped;
                }
            }
            $height_terms = $totalLinesTerms * 2;

            $extra_by_item_additional_information = 0;
            $extra_by_item_description = 0;
            $discount_global = 0;
            foreach ($this->document->items as $it) {
                if(strlen($it->item->description)>100){
                    $extra_by_item_description +=24;
                }
                if ($it->discounts) {
                    $discount_global = $discount_global + 1;
                }
                if($it->additional_information){
                    $extra_by_item_additional_information += count($it->additional_information) * 5;
                }
            }
            $legends = $this->document->legends != '' ? '10' : '0';

            $quotation_id = ($this->document->quotation_id) ? 15:0;

            $height_legend = 0;

            $append_height = 0;

            if($this->type === 'dispatch')
            {
                $append_height = 15;
                $this->appendHeightFromDispatch($append_height, $format, $this->document);
            }

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [
                    $width,
                    80 +
                    $height_terms +
                    (($quantity_rows * 8) + $extra_by_item_description) +
                    ($document_payments * 8) +
                    ($discount_global * 8) +
                    $company_name +
                    $company_address +
                    $company_number +
                    $customer_name +
                    $customer_address +
                    $p_order +
                    $legends +
                    $total_exportation +
                    $total_free +
                    $total_unaffected +
                    $total_exonerated +
                    $perception +
                    $total_taxed+
                    $total_prepayment +
                    $total_discount +
                    $was_deducted_prepayment +
                    $customer_department_id+
                    $quotation_id+
                    $extra_by_item_additional_information+
                    $height_legend+
                    $document_transport+
                    $append_height+
                    $document_retention
                ],
                'margin_top' => 0,
                'margin_right' => 1,
                'margin_bottom' => 0,
                'margin_left' => 1,
                'default_font' => 'monospace'
            ]);
        }else if($format_pdf === 'a5'){

            $company_name      = (strlen($this->company->name) / 20) * 10;
            $company_address   = (strlen($this->document->establishment->address) / 30) * 10;
            $company_number    = $this->document->establishment->telephone != '' ? '10' : '0';
            $customer_name     = strlen($this->document->customer->name) > '25' ? '10' : '0';
            $customer_address  = (strlen($this->document->customer->address) / 200) * 10;
            $p_order           = $this->document->purchase_order != '' ? '10' : '0';

            $total_exportation = (object)$this->document->total_exportation != '' ? '10' : '0';
            $total_free        = (object)$this->document->total_free != '' ? '10' : '0';
            $total_unaffected  = (object)$this->document->total_unaffected != '' ? '10' : '0';
            $total_exonerated  = (object)$this->document->total_exonerated != '' ? '10' : '0';
            $total_taxed       = (object)$this->document->total_taxed != '' ? '10' : '0';
            $quantity_rows     = count($this->document->items);

            $extra_by_item_description = 0;
            $discount_global = 0;
            foreach ($this->document->items as $it) {
                if(strlen($it->item->description)>100){
                    $extra_by_item_description +=24;
                }
                if ($it->discounts) {
                    $discount_global = $discount_global + 1;
                }
            }
            $legends = $this->document->legends != '' ? '10' : '0';


            $height = ($quantity_rows * 8) +
                    ($discount_global * 3) +
                    $company_name +
                    $company_address +
                    $company_number +
                    $customer_name +
                    $customer_address +
                    $p_order +
                    $legends +
                    $total_exportation +
                    $total_free +
                    $total_unaffected +
                    $total_exonerated +
                    $total_taxed;
            $diferencia = 148 - (float)$height;

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [
                    210,
                    $diferencia + $height
                    ],
                'margin_top' => 2,
                'margin_right' => 5,
                'margin_bottom' => 0,
                'margin_left' => 5,
                'default_font' => 'arial'
            ]);


        } else {

            if ($base_pdf_template === 'brand') {
                $pdf_margin_top = 93.7;
                $pdf_margin_bottom = 74;
            }
            if ($base_pdf_template === 'blank' && in_array($this->document->document_type_id, ['09'])) {
                $pdf_margin_top = 110;
                $pdf_margin_bottom = 125;
            }

            $pdf_font_regular = config('tenant.pdf_name_regular');
            $pdf_font_bold = config('tenant.pdf_name_bold');

            $templateFontDir = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf'.
                                             DIRECTORY_SEPARATOR.$base_pdf_template.
                                             DIRECTORY_SEPARATOR.'font');

            $regularTtf = $pdf_font_regular
                ? $templateFontDir.DIRECTORY_SEPARATOR.$pdf_font_regular.'.ttf'
                : null;
            $boldTtf = $pdf_font_bold
                ? $templateFontDir.DIRECTORY_SEPARATOR.$pdf_font_bold.'.ttf'
                : null;

            $hasRegularFont = $regularTtf && file_exists($regularTtf);
            $hasBoldFont = $boldTtf && file_exists($boldTtf);

            if ($hasRegularFont) {
                $defaultConfig = (new ConfigVariables())->getDefaults();
                $fontDirs = $defaultConfig['fontDir'];

                $defaultFontConfig = (new FontVariables())->getDefaults();
                $fontData = $defaultFontConfig['fontdata'];

                $customFontData = [
                    'custom_regular' => [
                        'R' => $pdf_font_regular.'.ttf',
                    ],
                    'custom_bold' => [
                        'R' => ($hasBoldFont ? $pdf_font_bold : $pdf_font_regular).'.ttf',
                    ],
                ];

                $pdf = new Mpdf([
                    'fontDir' => array_merge($fontDirs, [$templateFontDir]),
                    'fontdata' => $fontData + $customFontData,
                    'margin_top' => $pdf_margin_top,
                    'margin_right' => $pdf_margin_right,
                    'margin_bottom' => $pdf_margin_bottom,
                    'margin_left' => $pdf_margin_left,
                    'default_font' => 'custom_regular'
                ]);

            } else {
                $pdf = new Mpdf([
                    'margin_top' => $pdf_margin_top,
                    'margin_right' => $pdf_margin_right,
                    'margin_bottom' => $pdf_margin_bottom,
                    'margin_left' => $pdf_margin_left,
                    'default_font' => 'arial'
                ]);
            }
        }

        $path_css = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf'.
                                             DIRECTORY_SEPARATOR.$base_pdf_template.
                                             DIRECTORY_SEPARATOR.'style.css');

        $stylesheet = file_get_contents($path_css);

        if(config('tenant.pdf_template_footer')) {
            $html_footer = '';
            if (($format_pdf != 'ticket') AND ($format_pdf != 'ticket_58')) {
                $html_footer = $template->pdfFooter($base_pdf_template, in_array($this->document->document_type_id, ['09']) ? null : $this->document);
            }
            $pdf->SetHTMLFooter($html_footer);
        }

        if ($base_pdf_template === 'brand') {

            $html_header = $template->pdfHeader($base_pdf_template, $this->company, in_array($this->document->document_type_id, ['09']) ? null : $this->document);
            $pdf->SetHTMLHeader($html_header);

            if (($format_pdf === 'ticket') || ($format_pdf === 'ticket_58') || ($format_pdf === 'a5')) {
                $pdf->SetHTMLHeader("");
                $pdf->SetHTMLFooter("");
            }
        }

        if ($base_pdf_template === 'blank' && in_array($this->document->document_type_id, ['09'])) {

            $html_header = $template->pdfHeader($base_pdf_template, $this->company, $this->document);
            $pdf->SetHTMLHeader($html_header);

            $html_footer_blank = $template->pdfFooterBlank($base_pdf_template, $this->document);
            $pdf->SetHTMLFooter($html_footer_blank);
        }

        if ($base_pdf_template === 'default3_929' && in_array($this->document->document_type_id, ['01'])) {
            // Encabezado específico de Factura para la plantilla #929.
            $html_header = $template->pdfHeader($base_pdf_template, $this->company, $this->document);
            $pdf->SetHTMLHeader($html_header);
            $html_footer = $template->pdfFooter($base_pdf_template, $this->document);
            $pdf->SetHTMLFooter($html_footer);
        }

        if ($base_pdf_template === 'distpatch_pharmacy' && in_array($this->document->document_type_id, ['09'])) {
            // Solo para orden de entrega #1192
            $pdf->setAutoTopMargin = 'stretch'; //margen autommatico
            $pdf->autoMarginPadding  = 0;
            $pdf->setAutoBottomMargin = 'stretch';
            $html_header = $template->pdfHeader($base_pdf_template, $this->company, $this->document);
            $pdf->SetHTMLHeader($html_header);
            $html_footer = $template->pdfFooterDispatch($base_pdf_template, $this->document);
            $pdf->SetHTMLFooter($html_footer);
        }

        // para impresion automatica se requiere el resultado en html ya que es lo que se envia a las funciones de impresión
        if($output == 'html') {
            $path_html = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf'.
                                             DIRECTORY_SEPARATOR.'ticket_html.css');
            $ticket_html = file_get_contents($path_html);
            $pdf->WriteHTML($ticket_html, HTMLParserMode::HEADER_CSS);
            $this->renderMpdfSafely(function () use ($pdf, $html) {
                return $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
            });
            return "<style>".$ticket_html.$stylesheet."</style>".$html;
        }
        else {
            $pdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
            $this->renderMpdfSafely(function () use ($pdf, $html) {
                return $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
            });

            $helper_facturalo = new HelperFacturalo();

            if($helper_facturalo->isAllowedAddDispatchTicket($format_pdf, $this->type, $this->document))
            {
                $helper_facturalo->addDocumentDispatchTicket($pdf, $this->company, $this->document, [
                    $template,
                    $base_pdf_template,
                    $width,
                    ($quantity_rows * 8) + $extra_by_item_description
                ]);
            }
        }

        // echo $html_header.$html.$html_footer; exit();
        // $this->uploadFile($pdf->output('', 'S'), 'pdf');
        // return $this;
        $this->renderMpdfSafely(function () use ($pdf) {
            return $pdf->output('test_'.now()->format('Y_m_d').'.pdf', 'I');
        });
    }

    public function setPaymentsPreview($document, $payments)
    {
        $total = $document->total;
        $balance = $total - collect($payments)->sum('payment');

        $search_cash = ($balance < 0) ? collect($payments)->firstWhere('payment_method_type_id', '01') : null;
        $this->apply_change = false;

        if ($balance < 0 && $search_cash) {

            $payments = collect($payments)->map(function ($row) use ($balance) {

                $change = null;
                $payment = $row['payment'];

                if ($row['payment_method_type_id'] == '01' && !$this->apply_change) {
                    $change = abs($balance);
                    $payment = $row['payment'] - abs($balance);
                    $this->apply_change = true;
                }

                return [
                    "id" => null,
                    "document_id" => null,
                    "sale_note_id" => null,
                    "date_of_payment" => $row['date_of_payment'],
                    "payment_method_type_id" => $row['payment_method_type_id'],
                    "reference" => $row['reference'],
                    "payment_destination_id" => isset($row['payment_destination_id']) ? $row['payment_destination_id'] : null,
                    "change" => $change,
                    "payment" => $payment,
                    "payment_received" => isset($row['payment_received']) ? $row['payment_received'] : null,
                ];
            });
        }

        foreach ($payments as $row) {
            if ($balance < 0 && !$this->apply_change) {
                $row['change'] = abs($balance);
                $row['payment'] = $row['payment'] - abs($balance);
                $this->apply_change = true;
            }

            $payment = new \App\Models\Tenant\DocumentPayment($row);
            $document->payments[] = $payment;
        }
    }

    public function SetFeePreview($document, $fee)
    {
        foreach ($fee as $row) {
            $fee = new \App\Models\Tenant\DocumentFee($row);
            $document->fee[] = $fee;
        }
    }

}
