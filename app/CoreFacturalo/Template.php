<?php

namespace App\CoreFacturalo;
use Illuminate\Support\Facades\Log;

class Template
{
    public function pdf($base_template, $template, $company, $document, $format_pdf, $configuration = null )
    {
        if($template === 'credit' || $template === 'debit') {
            $template = 'note';
        }
        if ($document instanceof \App\Models\Tenant\Document && \App\Services\Fiscal\HkaPdf::applies($document)
            && in_array($format_pdf, ['a4', 'a5'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['pdf' => 'Las facturas digitales A4/A5 utilizan exclusivamente el PDF HKA.']);
        }
        $path_template =  $this->validate_template($base_template, $template, $format_pdf);
        // Log::info($document);
        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        $fiscal = \App\Services\Fiscal\FiscalPdfData::forDocument($document);
        $company = \App\Services\Fiscal\FiscalPdfData::issuerForPrint($company, $fiscal);
        $html = self::render($path_template, $company, $document, $configuration);
        if ($fiscal) {
            $identity = view('pdf.partials.fiscal_identity', compact('fiscal'))->render();
            if (preg_match('/<body\b[^>]*>/i', $html)) {
                $html = preg_replace_callback('/<body\b[^>]*>/i', fn ($match) => $match[0] . $identity, $html, 1);
            } else {
                $html = $identity . $html;
            }
        }
        return $html;
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
    }

    public function preprintedpdf($base_template, $template, $company, $format_pdf)
    {
        if($template === 'credit' || $template === 'debit') {
            $template = 'note';
        }

        $path_template =  $this->validate_preprinted_template($base_template, $template, $format_pdf);

        return self::preprintedrender($path_template, $company);
    }

    public function xml($template, $company, $document)
    {
        return self::render('xml.'.$template, $company, $document, null);
    }

    private function render($view, $company, $document, $configuration = null)
    {
        view()->addLocation(__DIR__.'/Templates');
        // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
        if ($document instanceof \App\Models\Tenant\Document && $document->issuer) {
            $company = clone $company;
            foreach ($document->issuer as $key => $value) $company->$key = $value;
        }
        // A submitted amount-in-words legend can coexist with the server-generated one.
        // Print the last (server-generated) value once without changing stored snapshots.
        if ($document->legends) {
            $document = clone $document;
            $legends = array_reverse((array) $document->legends);
            $amountPrinted = false;
            $legends = array_filter($legends, function ($legend) use (&$amountPrinted) {
                if ((string) data_get($legend, 'code') !== '1000') return true;
                if ($amountPrinted) return false;
                return $amountPrinted = true;
            });
            $document->legends = array_reverse(array_values($legends));
        }
        $html = view($view, compact('company', 'document', 'configuration'))->render();
        if ($document instanceof \App\Models\Tenant\Document) {
            $summary = view('pdf.partials.document_fiscal_totals', compact('document'))->render();
            $html = str_contains($html, '</body>') ? str_replace('</body>', $summary.'</body>', $html) : $html.$summary;
            if (!empty($configuration['hka_ticket_qr'])) {
                $qr = view('pdf.partials.hka_ticket_qr', ['qr' => $configuration['hka_ticket_qr']])->render();
                // Ticket templates reserve a cell below the amount in words, beside payments.
                if (str_contains($html, '<!-- HKA_TICKET_QR -->')) {
                    $html = preg_replace('/<!-- HKA_TICKET_QR -->/', $qr, $html, 1);
                } else {
                    $bodyEnd = strripos($html, '</body>');
                    $html = $bodyEnd === false ? $html.$qr : substr_replace($html, $qr, $bodyEnd, 0);
                }
            }
        }
        return $html;
        // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
    }

    private function preprintedrender($view, $company)
    {
        view()->addLocation(__DIR__.'/Templates');

        return view($view, compact('company'))->render();
    }

    public function pdfFooter($base_template, $document = null)
    {
        view()->addLocation(__DIR__.'/Templates');

        return view('pdf.'.$base_template.'.partials.footer', compact('document'))->render();
    }

    public function pdfHeader($base_template, $company, $document = null)
    {
        view()->addLocation(__DIR__.'/Templates');

        if ($document instanceof \App\Models\Tenant\Document && $document->issuer) {
            $company = clone $company;
            foreach ($document->issuer as $key => $value) $company->$key = $value;
        }
        return view('pdf.'.$base_template.'.partials.header', compact('company', 'document'))->render();
    }

    public function validate_template($base_template, $template, $format_pdf)
    {
        $path_app_template = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates');
        $path_template_default = 'pdf'.DIRECTORY_SEPARATOR.'default'.DIRECTORY_SEPARATOR.$template.'_'.$format_pdf;
        $path_template = 'pdf'.DIRECTORY_SEPARATOR.$base_template.DIRECTORY_SEPARATOR.$template.'_'.$format_pdf;



        if(file_exists($path_app_template.DIRECTORY_SEPARATOR.$path_template.'.blade.php')) {
            return str_replace(DIRECTORY_SEPARATOR, '.', $path_template);
        }

        return str_replace(DIRECTORY_SEPARATOR, '.', $path_template_default);
    }

    public function validate_preprinted_template($base_template, $template, $format_pdf)
    {
        $path_app_template = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates');
        $path_template_default = 'preprinted_pdf'.DIRECTORY_SEPARATOR.'default'.DIRECTORY_SEPARATOR.$template.'_'.$format_pdf;
        $path_template = 'preprinted_pdf'.DIRECTORY_SEPARATOR.$base_template.DIRECTORY_SEPARATOR.$template.'_'.$format_pdf;



        if(file_exists($path_app_template.DIRECTORY_SEPARATOR.$path_template.'.blade.php')) {
            return str_replace(DIRECTORY_SEPARATOR, '.', $path_template);
        }

        return str_replace(DIRECTORY_SEPARATOR, '.', $path_template_default);
    }


    public function pdfFooterTermCondition($base_template, $document)
    {
        view()->addLocation(__DIR__.'/Templates');

        return view('pdf.'.$base_template.'.partials.footer_term_condition', compact('document'))->render();
    }


    public function pdfFooterBlank($base_template, $document)
    {
        view()->addLocation(__DIR__.'/Templates');

        return view('pdf.'.$base_template.'.partials.footer_blank', compact('document'))->render();
    }

    public function pdfFooterDispatch($base_template, $document)
    {
        view()->addLocation(__DIR__.'/Templates');

        return view('pdf.'.$base_template.'.partials.footer_dispatch', compact('document'))->render();
    }


    /**
     *
     * Renderizar pdf por nombre sin considerar formato
     *
     * @param  string $base_template
     * @param  string $template
     * @param  mixed $company
     * @param  mixed $document
     * @return mixed
     */
    public function pdfWithoutFormat($base_template, $template, $company, $document)
    {
        $path_template =  $this->validateTemplateWithoutFormat($base_template, $template);
        return self::render($path_template, $company, $document);
    }


    /**
     *
     * Validar si existe el template
     *
     * @param  string $base_template
     * @param  string $template
     * @return string
     */
    public function validateTemplateWithoutFormat($base_template, $template)
    {
        $path_app_template = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates');
        $path_template_default = 'pdf'.DIRECTORY_SEPARATOR.'default'.DIRECTORY_SEPARATOR.$template;
        $path_template = 'pdf'.DIRECTORY_SEPARATOR.$base_template.DIRECTORY_SEPARATOR.$template;

        if(file_exists($path_app_template.DIRECTORY_SEPARATOR.$path_template.'.blade.php')) return str_replace(DIRECTORY_SEPARATOR, '.', $path_template);

        return str_replace(DIRECTORY_SEPARATOR, '.', $path_template_default);
    }


    /**
     * Imagenes en footer pdf
     *
     * Disponible para cotizacion a4, en template default/default3
     *
     * @param  string $base_template
     * @return string
     */
    public function pdfFooterImages($base_template, $images)
    {
        view()->addLocation(__DIR__.'/Templates');

        return view('pdf.'.$base_template.'.partials.footer_images', compact('images'))->render();
    }

}
