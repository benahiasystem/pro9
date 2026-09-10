<?php

namespace App\Mail\Tenant;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Models\Tenant\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DocumentEmail extends Mailable
{
    use Queueable;
    use SerializesModels;
    use StorageDocument;

    public $company;
    public $document;

    public function __construct($company, $document)
    {
        $this->company = $company;
        $this->document = $document;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $pdf = $this->getStorage($this->document->filename, 'pdf');
        // ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
        $template_document_mail = config('tenant.template_document_mail');
        if($template_document_mail === 'default') {
            $template_document_mail_view = 'tenant.templates.email.document';
            $subject = 'Envio de Comprobante de Pago Electrónico';
        } else {
            $template_document_mail_view = 'tenant.templates.email.'.$template_document_mail;
            $subject = 'Folio '.$this->document->folio;
        }

        $email = $this->subject($subject)
                    ->from(config('mail.username'), 'Comprobante electrónico')
                    ->view($template_document_mail_view)
                    ->attachData($pdf, $this->document->filename.'.pdf');

        // ######## FIN MODALIDAD DE EMISIÓN FISCAL ########

        return $email;
    }

}
