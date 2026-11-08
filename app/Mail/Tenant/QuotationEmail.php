<?php

namespace App\Mail\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuotationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $company;
    public $quotation;

    public function __construct($company, $quotation)
    {
        $this->company = $company;
        $this->quotation = $quotation;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $document = app(\App\Http\Controllers\Tenant\QuotationController::class)->emailPdf($this->quotation->external_id);
        $this->quotation = $document['quotation'];
        $pdf = $document['bytes'];
        return $this->subject('Envio de Cotización')
                    ->from(config('mail.username'), 'Cotización')
                    ->view('tenant.templates.email.quotation')
                    ->attachData($pdf, \App\Services\Fiscal\DocumentFileName::visible($this->quotation->filename).'.pdf');
    }
}
