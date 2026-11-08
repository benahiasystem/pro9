<?php

namespace App\Mail\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class CulqiEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $document;

    public function __construct($document)
    {
        $this->document = $document;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $paid = ($this->document->payment_confirmed ?? false) === true;
        return $this->subject($paid ? 'Constancia - Nuevo pago desde Ecommerce' : 'Confirmación de pedido')
                    ->from(config('mail.username'), $paid ? 'Constancia de Pago' : 'Confirmación de pedido')
                    ->view('tenant.templates.email.culqui_payment');
    }
}
