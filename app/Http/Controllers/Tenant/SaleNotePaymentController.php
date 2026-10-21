<?php
namespace App\Http\Controllers\Tenant;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\CoreFacturalo\Template;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaleNotePaymentRequest;
use App\Http\Resources\Tenant\SaleNotePaymentCollection;
use App\Models\Tenant\Company;
use App\Models\Tenant\PaymentMethodType;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\SaleNotePayment;
use FontLib\Table\Type\loca;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Modules\Finance\Traits\FinanceTrait;
use Modules\Finance\Traits\FilePaymentTrait;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\CashDocumentCredit;
use App\Models\Tenant\Cash;
use App\Support\Exact\Decimal as D;

class SaleNotePaymentController extends Controller
{
    use StorageDocument, FinanceTrait, FilePaymentTrait;

    public function records($sale_note_id)
    {
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        $records = SaleNotePayment::with(['global_payment.destination'=>fn($relation)=>$relation->morphWith([
            \App\Models\Tenant\BankAccount::class=>['bank'],
        ]),'payment_file'])
            ->where('sale_note_id', $sale_note_id)->get();
        // ######## FIN CALCULOS EXACTOS PRO9 ########

        return new SaleNotePaymentCollection($records);
    }

    public function tables()
    {
        return [
            'payment_method_types' => PaymentMethodType::active()->get(),
            'payment_destinations' => $this->getPaymentDestinations()
        ];
    }

    public function document($sale_note_id)
    {
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        return \App\Support\Exact\ReadSnapshot::run(DB::connection('tenant'), function () use ($sale_note_id) {
        $sale_note = SaleNote::withoutEagerLoads()->findOrFail($sale_note_id);
        $values = \App\Support\Exact\SaleNoteBalance::values($sale_note);
        $totals = \App\Support\Exact\SaleNoteBalance::transport($values);

        return [
            'identifier' => $sale_note->identifier,
            'full_number' => $sale_note->getNumberFullAttribute(),
            'number_full' => $sale_note->getNumberFullAttribute(),
            'total_paid' => $totals['total_paid'],
            'total' => $totals['total'],
            'total_difference' => $totals['total_difference'],
            'document_total' => $totals['document_total'],
            'settlement_adjustment' => $totals['settlement_adjustment'],
            'calculation_snapshot' => $totals['calculation_snapshot'],
            'paid' => $values['total_difference']->isZero(),
            'external_id' => $sale_note->external_id,
        ];
        });
        // ######## FIN CALCULOS EXACTOS PRO9 ########
    }

    public function store(SaleNotePaymentRequest $request)
    {
        $id = $request->input('id');

        DB::connection('tenant')->transaction(function () use ($id, $request) {
            // ######## INICIO CALCULOS EXACTOS PRO9 ########
            $sale_note = SaleNote::withoutEagerLoads()->lockForUpdate()->findOrFail($request->sale_note_id);
            $record = $id ? $sale_note->payments()->whereKey($id)->firstOrFail() : $sale_note->payments()->make();
            // ######## FIN CALCULOS EXACTOS PRO9 ########
            // ######## INICIO CALCULOS EXACTOS PRO9 ########
            $record->fill($request->only(['date_of_payment','payment_method_type_id','reference','payment','has_card','card_brand_id']));
            if ($record->exists && isset($record->calculation_snapshot['inputs']['tendered_payment'])
                && $record->isDirty(['payment','payment_method_type_id'])) {
                \App\Services\Fiscal\FiscalAmounts::error('payment','Revierta el cobro inicial antes de registrar otro importe o medio de pago.');
            }
            // ######## FIN CALCULOS EXACTOS PRO9 ########
            $record->save();
            $this->createGlobalPayment($record, $request->all());
            $this->saveFiles($record, $request, 'sale_notes');
            $this->createCashDocumentPayment($record, false);

            $sale_note = SaleNote::lockForUpdate()->findOrFail($request->sale_note_id);

            // ######## INICIO CALCULOS EXACTOS PRO9 ########
            $values = \App\Support\Exact\SaleNoteBalance::values($sale_note);
            $sale_note->total_canceled = $values['total_difference']->isZero();
            // ######## FIN CALCULOS EXACTOS PRO9 ########
            $sale_note->save();

            if ($sale_note->total_canceled) {
                $credit = CashDocumentCredit::where([
                    ['status', 'PENDING'],
                    ['sale_note_id', $sale_note->id]
                ])->first();

                if ($credit) {
                    $cash = Cash::where([
                        ['user_id', auth()->user()->id],
                        ['state', true],
                    ])->first();

                    if ($cash) {
                        $credit->status = 'PROCESSED';
                        $credit->cash_id_processed = $cash->id;
                        $credit->save();
                    }
                }
            }
        });

        $this->createPdf($request->input('sale_note_id'));

        return [
            'success' => true,
            'message' => ($id) ? 'Pago editado con éxito' : 'Pago registrado con éxito'
        ];
    }

    public function destroy($id)
    {
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        $sale_note_id = DB::connection('tenant')->transaction(function () use ($id) {
            $ownerId = SaleNotePayment::withoutEagerLoads()->findOrFail($id)->sale_note_id;
            $sale_note = SaleNote::withoutEagerLoads()->lockForUpdate()->findOrFail($ownerId);
            $item = $sale_note->payments()->whereKey($id)->lockForUpdate()->firstOrFail();
            $item->cashDocumentPayments()->delete();
            $item->delete();
            $values = \App\Support\Exact\SaleNoteBalance::values($sale_note);
            $sale_note->total_canceled = $values['total_difference']->isZero();
            $sale_note->save();
            return $sale_note->id;
        });
        // ######## FIN CALCULOS EXACTOS PRO9 ########

        $this->createPdf($sale_note_id);

        return [
            'success' => true,
            'message' => 'Pago eliminado con éxito'
        ];
    }

    public function createPdf($sale_note_id, $format = null)
    {
        $sale_note = SaleNote::find($sale_note_id);
        $values = \App\Support\Exact\SaleNoteBalance::values($sale_note);
        $totals = \App\Support\Exact\SaleNoteBalance::transport($values);

        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        $sale_note->total_canceled = $values['total_difference']->isZero();
        // ######## FIN CALCULOS EXACTOS PRO9 ########
        $sale_note->save();


        $company = Company::first();

        $template = new Template();
        $pdf = null;
        if($format == 'a5')
        {
              $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [
                    78,
                    220
                    ],
                'margin_top' => 2,
                'margin_right' => 5,
                'margin_bottom' => 0,
                'margin_left' => 5
            ]);

        }
        else{
           $pdf = new Mpdf();
        }

        $document = SaleNote::find($sale_note_id);

        $base_template = config('tenant.pdf_template');

        $html = $template->pdf($base_template, "sale_note", $company, $document,"a4");

        $pdf_font_regular = config('tenant.pdf_name_regular');
        $pdf_font_bold = config('tenant.pdf_name_bold');

        if ($pdf_font_regular != false) {
            $defaultConfig = (new ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $pdf = new Mpdf([
                'fontDir' => array_merge($fontDirs, [
                    app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                        DIRECTORY_SEPARATOR.'pdf'.
                        DIRECTORY_SEPARATOR.$base_template.
                        DIRECTORY_SEPARATOR.'font')
                ]),
                'fontdata' => $fontData + [
                        'custom_bold' => [
                            'R' => $pdf_font_bold.'.ttf',
                        ],
                        'custom_regular' => [
                            'R' => $pdf_font_regular.'.ttf',
                        ],
                    ]
            ]);
        }

        $path_css = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
            DIRECTORY_SEPARATOR.'pdf'.
            DIRECTORY_SEPARATOR.$base_template.
            DIRECTORY_SEPARATOR.'style.css');

        $stylesheet = file_get_contents($path_css);

        $pdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        if(config('tenant.pdf_template_footer')) {
            $html_footer = $template->pdfFooter($base_template,$document);
            $pdf->SetHTMLFooter($html_footer);
        }

        $this->uploadStorage($document->filename, $pdf->output('', 'S'), 'sale_note');
        return $document->filename;
//        $this->uploadFile($pdf->output('', 'S'), 'sale_note');
    }

    public function toPrint($sale_note_id, $format)
    {
        $filename = $this->createPdf($sale_note_id, $format);
        $temp = tempnam(sys_get_temp_dir(), 'sale_note');
        file_put_contents($temp, $this->getStorage($filename, 'sale_note'));

        /*
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'.pdf'.'"'
        ];
        */

        return response()->file($temp, $this->generalPdfResponseFileHeaders($filename));
    }


}
