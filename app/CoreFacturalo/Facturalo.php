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
use App\Models\Tenant\PaymentMethodType;
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

    // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
    public function save($inputs)
    {
        return \Illuminate\Support\Facades\DB::connection('tenant')->transaction(fn () => $this->saveWithinTransaction($inputs));
    }
    // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
    private function saveWithinTransaction($inputs)
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
            if (($inputs['total_other_taxes'] ?? 0) > 0 && empty($inputs['taxes'])) \App\Services\Fiscal\FiscalAmounts::error('taxes','Los impuestos adicionales requieren un detalle identificable.');
        }
        // ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########

        switch ($this->type) {
            case 'debit':
            case 'credit':
                $document = Document::create($inputs);
                $document->note()->create($inputs['note']);
                foreach ($inputs['items'] as $row) {
                    $document->items()->create(in_array($this->type, ['invoice','credit','debit'], true) ? \App\Services\Fiscal\FiscalDocumentPersistence::line($row) : $row);
                }
                if($this->type === 'credit') $this->saveFee($document, $inputs['fee']);
                // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
                \App\Services\Fiscal\FiscalDocumentPersistence::fiscalData($document, $inputs['fiscal_data'] ?? []);
                \App\Services\Fiscal\FiscalDocumentPersistence::otherTaxes($document, $inputs['taxes'] ?? []);
                \App\Services\Fiscal\FiscalDocumentPersistence::summarize($document);
                \App\Services\Fiscal\FiscalDocumentPersistence::applySettlements($document, $inputs);
                // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
                $this->document = Document::find($document->id);
                break;
            case 'invoice':
                $document = Document::create($inputs);
                $this->saveFee($document, $inputs['fee']);
                foreach ($inputs['items'] as $row) {
//                    $purchase_unit_price = $row['purchase_unit_price'];
//                    $row['item']['purchase_unit_price'] = $purchase_unit_price;
                    $document->items()->create(in_array($this->type, ['invoice','credit','debit'], true) ? \App\Services\Fiscal\FiscalDocumentPersistence::line($row) : $row);
                    // $row['document_id']=  $document->id;
                    // $item = new DocumentItem($row);
                    // $item->push();
                }
                \App\Services\Fiscal\FiscalDocumentPersistence::otherTaxes($document, $inputs['taxes'] ?? []);
                \App\Services\Fiscal\FiscalDocumentPersistence::summarize($document);
                $this->savePayments($document, $inputs['payments']);
                $this->updatePrepaymentDocuments($inputs);
                if($inputs['hotel']) $document->hotel()->create($inputs['hotel']);
                if($inputs['transport']) $document->transport()->create($inputs['transport']);
                $document->invoice()->create($inputs['invoice']);
                // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
                \App\Services\Fiscal\FiscalDocumentPersistence::fiscalData($document, $inputs['fiscal_data'] ?? []);
                \App\Services\Fiscal\FiscalDocumentPersistence::otherTaxes($document, $inputs['taxes'] ?? []);
                \App\Services\Fiscal\FiscalDocumentPersistence::summarize($document);
                \App\Services\Fiscal\FiscalDocumentPersistence::applySettlements($document, $inputs);
                // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
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
                    $document->items()->create(in_array($this->type, ['invoice','credit','debit'], true) ? \App\Services\Fiscal\FiscalDocumentPersistence::line($row) : $row);
                }
                $this->document = PurchaseSettlement::find($document->id);
                break;
            default:
                DispatchItem::query()->where('dispatch_id', $inputs['id'])->delete();
                $document = Dispatch::query()->updateOrCreate([
                    'id' => $inputs['id']
                ], $inputs);
                foreach ($inputs['items'] as $row) {
                    $document->items()->create(in_array($this->type, ['invoice','credit','debit'], true) ? \App\Services\Fiscal\FiscalDocumentPersistence::line($row) : $row);
                }
                $this->document = Dispatch::find($document->id);
                break;
        }
        // ######## INICIO EMISION HKA DEMO ########
        if ($this->document instanceof Document) {
            app(\App\Services\Fiscal\HkaMail::class)->registerAutomatic($this->document,
                (bool) optional($this->configuration)->auto_send_pdf_email || ($this->actions['send_email'] ?? false) === true);
        }
        if ($this->type === 'invoice' && $this->document->fiscal_emission_mode === 'digital'
            && $this->document->fiscal_environment === 'demo') {
            $id = $this->document->id;
            $database = $this->document->getConnection()->getDatabaseName();
            $this->document->getConnection()->afterCommit(function () use ($id, $database) {
                try {
                    if ((new Document)->getConnection()->getDatabaseName() !== $database) return;
                    $document = Document::findOrFail($id);
                    $this->response['fiscal_emission'] = app(\App\Services\Fiscal\HkaEmission::class)->send($document);
                    $this->response['sale_saved'] = true;
                    $this->response['message'] = 'Venta guardada. '.$this->response['fiscal_emission']['description'];
                    $this->document = $document->fresh();
                } catch (\Throwable $exception) {
                    $this->response['sale_saved'] = true;
                    $this->response['message'] = 'Venta guardada. El resultado fiscal requiere consulta.';
                }
            });
        }
        // ######## FIN EMISION HKA DEMO ########
        return $this;
    }

    public function sendEmail()
    {
        // Digital invoices are sent once by HKA after their fiscal confirmation.
        if ($this->document instanceof Document && \App\Services\Fiscal\HkaMail::applies($this->document)) return;
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

    public function createPdf($document = null, $type = null, $format = null, $output = 'pdf', array $downloadOptions = []) {
        ini_set("pcre.backtrack_limit", "5000000");
        $format_pdf = $this->actions['format_pdf'] ?? 'a4';

        $this->document = ($document != null) ? $document : $this->document;
        $format_pdf = ($format != null) ? $format : $format_pdf;
        $this->type = ($type != null) ? $type : $this->type;

        // ######## INICIO PDF HKA PERSISTENTE ########
        if ($this->document instanceof Document && \App\Services\Fiscal\HkaPdf::applies($this->document)
            && in_array($format_pdf, ['a4', 'a5'], true) && $output === 'html') $format_pdf = 'ticket';
        if ($this->document instanceof Document && \App\Services\Fiscal\HkaPdf::applies($this->document)
            && in_array($format_pdf, ['a4', 'a5'], true)) {
            if ($this->document->getConnection()->transactionLevel()) {
                if ($output === 'string') throw \Illuminate\Validation\ValidationException::withMessages(['pdf' => 'El PDF HKA está pendiente de confirmación.']);
                return $this;
            }
            $service = app(\App\Services\Fiscal\HkaPdf::class);
            if ($output === 'string') return $service->download($this->document, $format_pdf);
            if ($format_pdf === 'a4') $service->storeConfirmed($this->document);
            else $service->download($this->document, $format_pdf);
            return $this;
        }
        // ######## FIN PDF HKA PERSISTENTE ########
        $template = new Template();
        $pdf = new Mpdf();

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
        // Only the dedicated 80MM download supplies this option; previews and printing do not.
        if ($format_pdf === 'ticket' && $output === 'string' && !empty($downloadOptions['hka_ticket_qr'])) {
            $optional_configuration['hka_ticket_qr'] = $downloadOptions['hka_ticket_qr'];
            $heightQr += 42;
        }

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
            $total_exonerated  = $this->document->total_exonerated != '' ? '10' : '0';
            $total_taxed       = $this->document->total_taxed != '' ? '10' : '0';

            $quantity_rows     = count($this->document->items) + $was_deducted_prepayment;
            $document_payments     = count($this->document->payments ?? []);
            $document_transport     = ($this->document->transport) ? 30 : 0;
            $document_retention     = ($this->document instanceof Document && $this->document->retention_amount > 0) ? 10 : 0;

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
                    $total_exonerated +

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
        $pdfBytes = $this->renderMpdfSafely(function () use ($pdf) {
            return $pdf->output('', 'S');
        });
        if ($output === 'string') return $pdfBytes;
        $this->uploadFile($pdfBytes, 'pdf');
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
            $format = $this->actions['format_pdf'] ?? 'a4';
            if (\App\Services\Fiscal\HkaPdf::applies($this->document) && in_array($format, ['a4', 'a5'], true)) {
                if ($this->document->getConnection()->transactionLevel()) {
                    $result['reason'] = 'pdf_pending';
                    return $result;
                }
                $pdf_b64 = base64_encode(app(\App\Services\Fiscal\HkaPdf::class)->download($this->document, $format));
            } else {
                $pdf_b64 = base64_encode($this->getStorage($this->document->filename, 'pdf'));
            }

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

        if($document->driver)
        {
            if($document->driver->number)  $driver += 5;
            if($document->driver->license)  $driver += 5;
        }

        $append_height += $base_height + $observations + $data_affected_document + $transfer_reason_type + $transport_mode_type + $driver
                            + $license_plate;

    }


    public function uploadFile($file_content, $file_type)
    {
        $this->uploadStorage($this->document->filename, $file_content, $file_type);
    }





    private function updatePrepaymentDocuments($inputs){
        // dd($inputs);

        if(isset($inputs['prepayments'])) {

            foreach ($inputs['prepayments'] as $row) {

                $fullnumber = \App\Services\Fiscal\FiscalIdentity::parseNumberFull((string) $row['number']);
                if ($fullnumber === null) continue;
                $series = $fullnumber[0];
                $number = $fullnumber[1];

                $doc = Document::where([['series',$series],['number',$number]])
                    ->where('establishment_id', $this->document->establishment_id)
                    ->where('fiscal_environment', $this->document->fiscal_environment)
                    ->where('document_type_id', ($row['document_type_id'] ?? '02') === '03' ? '03' : '01')->first();

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

    // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
    private function savePayments($document, $payments, $isUpdate = false)
    {
        $company = Company::firstOrFail();
        $normalized = [];
        foreach ($payments as $row) {
            $values = \App\Services\Fiscal\FiscalAmounts::payment($row, $document->currency_type_id, $document->exchange_rate_sale, (bool)$company->igtf_enabled, $company->igtf_rate);
            $normalized[] = array_merge($row, $values);
        }
        $excess = round(array_sum(array_column($normalized, 'payment')) - $document->total, 2);
        if ($excess > 0) {
            $index = 0;
            foreach ($normalized as $i => $row) if ($row['payment_method_type_id'] === '01') { $index = $i; break; }
            $row = $normalized[$index];
            if ($row['payment'] <= $excess) \App\Services\Fiscal\FiscalAmounts::error('payments','El exceso supera el pago seleccionado para vuelto.');
            $row['change'] = \App\Services\Fiscal\FiscalAmounts::convert($excess, $document->currency_type_id, $row['currency_type_id'], $row['exchange_rate']);
            $row['original_amount'] = round($row['original_amount'] - $row['change'], 2);
            $normalized[$index] = $row;
        }
        foreach ($normalized as $row) {
            $record = \App\Services\Fiscal\FiscalDocumentPersistence::payment($document, $row, true);
            $this->saveFilesFromPayments($row, $record, 'documents');
            if (isset($row['payment_destination_id']) && !$record->global_payment) {
                $this->createGlobalPayment($record, $row);
                if ($isUpdate) $this->createCashDocumentPayment($record, true);
            }
        }
    }
    // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########

    /**
     * @param array $inputs
     * @param int   $id
     */
    // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
    public function update($inputs,$id)
    {
        return \Illuminate\Support\Facades\DB::connection('tenant')->transaction(fn () => $this->updateWithinTransaction($inputs, $id));
    }
    // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
    private function updateWithinTransaction($inputs,$id)
    {

        $this->actions = array_key_exists('actions', $inputs)?$inputs['actions']:[];
        $this->type = @$inputs['type'];
        // dd($inputs);
        switch ($this->type) {
            case 'invoice':
                Company::query()->lockForUpdate()->firstOrFail();
                $document = Document::query()->lockForUpdate()->findOrFail($id);
                $emission = $document->emission()->lockForUpdate()->first();
                $document->setRelation('emission', $emission);
                \App\Services\Fiscal\DocumentEditPolicy::assertEditable($document);
                \App\Services\Fiscal\DocumentEditSettlements::validateInput($document, $inputs);
                \App\Services\Fiscal\DocumentEditPolicy::invalidate($document);
                $inputs['series'] = \App\Services\SeriesNumbering::normalizeCode($inputs['series'] ?? null);
                if ($inputs['series'] !== $document->series || $inputs['document_type_id'] !== $document->document_type_id || (isset($inputs['number']) && (string) $inputs['number'] !== (string) $document->number) || (isset($inputs['establishment_id']) && (int) $inputs['establishment_id'] !== (int) $document->establishment_id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['series' => 'No se puede cambiar la identidad de un documento registrado.']);
                }
                $this->document = $document;
                $inputs['external_id'] = $document->external_id;
                $inputs['issuer'] = $document->issuer;
                $inputs['establishment'] = (array)$document->establishment;
                SalesCustomerIdentityPolicy::assertCustomerAllowed($inputs['customer_id']);
                $addressId = $inputs['customer_address_id'] ?? ($inputs['customer']['address_id'] ?? null);
                if ($addressId && !\App\Models\Tenant\PersonAddress::where('person_id', $inputs['customer_id'])->where('id', $addressId)->exists()) {
                    \App\Services\Fiscal\FiscalAmounts::error('customer_address_id', 'La dirección no pertenece al cliente seleccionado.');
                }
                $inputs['customer'] = \App\Services\Fiscal\FiscalDocumentPersistence::withHkaIdentity(\App\CoreFacturalo\Requests\Inputs\Common\PersonInput::set($inputs['customer_id'], $addressId));
                foreach (['user_id', 'payment_condition_id', 'fiscal_environment', 'state_type_id', 'is_editable', 'quotation_id', 'sale_note_id', 'technical_service_id', 'source_module', 'receipt_parent_id'] as $field) {
                    if (array_key_exists($field, $document->getAttributes())) $inputs[$field] = $document->getAttributes()[$field];
                }
                $inputs['payments'] = [];
                $inputs['exchange_rate_sale'] = \App\Services\ExchangeRates\ExchangeRateMath::rate($inputs['exchange_rate_sale'], 'exchange_rate_sale');
                $document->fill($inputs);
                $document->save();

                // Existing collection schedules and receipt references remain intact.

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
                    $document->items()->create(in_array($this->type, ['invoice','credit','debit'], true) ? \App\Services\Fiscal\FiscalDocumentPersistence::line($row) : $row);
                }

                \App\Services\Fiscal\FiscalDocumentPersistence::otherTaxes($document, $inputs['taxes'] ?? []);
                \App\Services\Fiscal\FiscalDocumentPersistence::summarize($document);
                $this->updatePrepaymentDocuments($inputs);

                if($inputs['hotel']){
                    $document->hotel()->update($inputs['hotel']);
                }

                $document->invoice()->update($inputs['invoice']);
                // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
                \App\Services\Fiscal\FiscalDocumentPersistence::fiscalData($document, $inputs['fiscal_data'] ?? []);
                \App\Services\Fiscal\FiscalDocumentPersistence::otherTaxes($document, $inputs['taxes'] ?? []);
                \App\Services\Fiscal\FiscalDocumentPersistence::summarize($document);
                \App\Services\Fiscal\DocumentEditSettlements::validateTotals($document);
                // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
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
        if ($this->document instanceof Document && (string) $this->document->document_type_id === '01') {
            $mode = $this->document->exists ? $this->document->fiscal_emission_mode : $this->company->fiscal_emission_mode;
            $this->document->fiscal_emission_mode = $mode;
            if ($mode === 'digital') $format_pdf = 'ticket';
        }


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


            $total_exonerated  = (object)$this->document->total_exonerated != '' ? '10' : '0';
            $total_taxed       = (object)$this->document->total_taxed != '' ? '10' : '0';

            $quantity_rows     = count($this->document->items) + $was_deducted_prepayment;
            $document_payments     = count($this->document->payments ?? []);
            $document_transport     = ($this->document->transport) ? 30 : 0;
            $document_retention     = ($this->document instanceof Document && $this->document->retention_amount > 0) ? 10 : 0;

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
                    $total_exonerated +

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
        $bytes = $this->renderMpdfSafely(fn () => $pdf->output('', 'S'));
        return response($bytes, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa-80mm.pdf"', 'Cache-Control' => 'private, no-store']);
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

                return array_merge($row, [
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
                ]);
            });
        }

        foreach ($payments as $row) {
            if ($balance < 0 && !$this->apply_change) {
                $row['change'] = abs($balance);
                $row['payment'] = $row['payment'] - abs($balance);
                unset($row['original_amount']);
                $this->apply_change = true;
            }

            // Draft payments inherit the invoice currency/rate, like persisted payments.
            $row['currency_type_id'] = $row['currency_type_id'] ?? $document->currency_type_id;
            $row['exchange_rate'] = $row['exchange_rate'] ?? $document->exchange_rate_sale;
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
