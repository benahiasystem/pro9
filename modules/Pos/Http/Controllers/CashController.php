<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Tenant\EmailController;
use App\Models\Tenant\Cash;
use App\Models\Tenant\Company;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\PaymentMethodType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Modules\Pos\Mail\CashEmail;
use Carbon\Carbon;
use Illuminate\Support\Collection;


//Inicio: Deyvis: pendiente revisar para que funke
use App\Http\Requests\Tenant\CashRequest;
use App\Http\Resources\Tenant\CashCollection;
use App\Http\Resources\Tenant\CashResource;
use App\Models\Tenant\CashDocument;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\Document;
use App\Models\Tenant\User;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Traits\FinanceTrait;
use Modules\Pos\Models\CashTransaction;
use App\Models\Tenant\CashDocumentCredit;
use Modules\Finance\Models\Income;
use App\CoreFacturalo\Helpers\Template\ReportHelper;
use Modules\CashReport\Services\Builders\CashSummaryBuilder;
use Modules\CashReport\Services\CashReportRegistry;
use Modules\CashReport\Services\CashReportRenderer;
use Modules\CashReport\Services\HeaderDataBuilder;
use App\Models\Tenant\CashDocumentPayment;
use Illuminate\Support\Facades\Auth;

// use Carbon\Carbon;
//Fin - Deyvis: pendiente revisar para que funke


class CashController extends Controller
{
    private const PAYMENT_METHOD_TYPE_CASH = '01';

    /**
     *
     * Usado en:
     * CashController - App
     *
     * @param  Request $request
     * @return array
     *
     */
    public function email(Request $request) {
        $request->validate(
            ['email' => 'required']
        );

        $company = Company::active();
        $email = $request->input('email');

        $mailable = new CashEmail($company, $this->getPdf($request->cash_id));
        $model = Cash::find($request->cash_id);
        $id = (int) $model->id;
        $sendIt = EmailController::SendMail($email, $mailable, $id, $model);
        /*
        Configuration::setConfigSmtpMail();
        $array_email = explode(',', $email);
        if (count($array_email) > 1) {
            foreach ($array_email as $email_to) {
                $email_to = trim($email_to);
                if(!empty($email_to)) {
                    Mail::to($email_to)->send(new CashEmail($company, $this->getPdf($request->cash_id)));
                }
            }
        } else {
            Mail::to($email)->send(new CashEmail($company, $this->getPdf($request->cash_id)));
        }*/

        return [
            'success' => true
        ];
    }

    /**
     * Obtiene el string del metodo de pago (compatibilidad, lógica en CashSummaryBuilder)
     */
    public static function getStringPaymentMethod($payment_id) {
        return CashSummaryBuilder::getStringPaymentMethod($payment_id);
    }

    /**
     * Genera un formato de numero para las operaciones del reporte (compatibilidad)
     */
    public static function FormatNumber($number = 0, $decimal = 2, $decimal_separador = '.', $miles_separador = '') {
        return CashSummaryBuilder::FormatNumber($number, $decimal, $decimal_separador, $miles_separador);
    }

    /**
     * Data del reporte de caja (compatibilidad, lógica en CashSummaryBuilder)
     */
    public function setDataToReport($cash_id = 0, $summary = 0) {
        return app(CashSummaryBuilder::class)->setDataToReport($cash_id, $summary);
    }

    public static function CalculeTotalOfCurency(...$args) {
        return CashSummaryBuilder::CalculeTotalOfCurency(...$args);
    }

    public static function getStateTypeId() {
        return CashSummaryBuilder::getStateTypeId();
    }

    /**
     * Contenido PDF del reporte de caja (usado por email y por los métodos legacy de la app).
     *
     * @param  int $cash
     * @param  string $format ticket|a4|simple_a4
     * @param  int|null $mm
     * @param  int $summary
     * @return string
     */
    private function getPdf($cash, $format = 'ticket', $mm = null, $summary = 0)
    {
        $cash = Cash::findOrFail($cash);

        if ($format === 'simple_a4') {
            return app(CashReportRenderer::class)->pdfContent('cash_simple', $cash);
        }

        $paper = CashReportRegistry::PAPER_A4;
        if ($format === 'ticket') {
            $paper = ((int) $mm === 58) ? CashReportRegistry::PAPER_TICKET_58 : CashReportRegistry::PAPER_TICKET_80;
        }

        return app(CashReportRenderer::class)->pdfContent('cash_summary', $cash, [
            'paper' => $paper,
            'summary' => $summary,
        ]);
    }

    /**
     * Legacy (app móvil): ticket inline.
     */
    public function reportTicket($cash, $mm, $summary = 0) {
        return $this->legacyInlinePdf($this->getPdf($cash, 'ticket', $mm, $summary), 'cash_pdf_ticket_'.$mm);
    }

    /**
     * Legacy (app móvil): A4 inline.
     */
    public function reportA4Api($cash)
    {
        return $this->legacyInlinePdf($this->getPdf($cash, 'a4'), 'cash_pdf_a4');
    }

    /**
     * Legacy web: A4 inline con validación de permiso.
     *
     * @deprecated Usar cash-reports/generate/cash_summary/{cash}
     */
    public function reportA4($cash) {

        $typeUser = Auth::user()->type;
        $is_configuration_seller = Configuration::AvailableReportSeller()->available_cash_report_seller;

        $validation = $typeUser === 'admin' ? true : ($typeUser === 'seller' && $is_configuration_seller ? true : false);

        if (!$validation) {
            abort(401);
        }

        return $this->legacyInlinePdf($this->getPdf($cash, 'a4'), 'cash_pdf_a4');
    }

    /**
     * Legacy web: simple A4 inline.
     *
     * @deprecated Usar cash-reports/generate/cash_simple/{cash}
     */
    public function reportSimpleA4($cash) {
        return $this->legacyInlinePdf($this->getPdf($cash, 'simple_a4'), 'cash_pdf_a4');
    }

    /**
     * Legacy web: excel del reporte de caja.
     *
     * @deprecated Usar cash-reports/generate/cash_summary/{cash}?format=excel
     */
    public function reportExcel($cash) {
        $cash = Cash::findOrFail($cash);
        $renderer = app(CashReportRenderer::class);

        return $renderer->excelExport('cash_summary', $cash)->download($renderer->filename('cash_summary', $cash).'.xlsx');
    }

    /**
     * Datos de cabecera comunes (compatibilidad).
     *
     * @param  Cash $cash
     * @return array
     */
    public function getHeaderCommonDataToReport($cash)
    {
        return HeaderDataBuilder::build($cash);
    }

    /**
     * Legacy (app móvil y web): ingresos y egresos en efectivo, inline.
     */
    public function reportCashIncomeEgress($cash)
    {
        $cash = Cash::findOrFail($cash);

        return $this->legacyInlinePdf(app(CashReportRenderer::class)->pdfContent('income_egress', $cash), 'cash_report_income_egress_pdf');
    }

    /**
     * Respuesta inline idéntica a la que entregaban los métodos originales.
     */
    protected function legacyInlinePdf($content, $temp_prefix)
    {
        $temp = tempnam(sys_get_temp_dir(), $temp_prefix);
        file_put_contents($temp, $content);

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Reporte"'
        ];

        return response()->file($temp, $headers);
    }
}
