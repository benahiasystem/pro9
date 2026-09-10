<?php

namespace Tests\Unit;

use App\Services\LocalFiscalDocumentPolicy;
use Tests\TestCase;

// ########## INICIO CAMBIO SIN XML CDR SUNAT
class LocalFiscalDocumentPolicyTest extends TestCase
{
    public function test_retired_transport_cannot_be_reenabled_by_legacy_configuration(): void
    {
        config()->set('venezuela.local_document_emission.enabled', false);
        self::assertTrue(LocalFiscalDocumentPolicy::enabled());
        self::assertFalse(LocalFiscalDocumentPolicy::registeredResponse()['sent']);
    }

    public function test_invoice_email_reads_and_attaches_only_the_pdf(): void
    {
        config()->set('tenant.template_document_mail', 'default');
        config()->set('mail.username', 'fiscal-test@example.test');
        $mail = new class((object) [], (object) ['filename' => 'invoice-test']) extends \App\Mail\Tenant\DocumentEmail {
            public array $reads = [];
            public function getStorage($filename, $file_type, $root = null)
            {
                $this->reads[] = [$filename, $file_type];
                return '%PDF-test';
            }
        };
        $mail->build();
        self::assertSame([['invoice-test', 'pdf']], $mail->reads);
        self::assertCount(1, $mail->rawAttachments);
        self::assertSame('invoice-test.pdf', $mail->rawAttachments[0]['name']);
    }

    /** @test */
    public function local_registration_is_successful_without_claiming_transmission_or_acceptance(): void
    {
        config()->set('venezuela.local_document_emission.enabled', true);
        config()->set('venezuela.local_document_emission.status_code', 'LOCAL_REGISTERED');

        $response = LocalFiscalDocumentPolicy::registeredResponse();

        self::assertTrue(LocalFiscalDocumentPolicy::enabled());
        self::assertTrue($response['success']);
        self::assertFalse($response['sent']);
        self::assertTrue($response['local']);
        self::assertSame('LOCAL_REGISTERED', $response['code']);
        self::assertNull($response['xml_signed']);
        self::assertNull($response['hash']);
        self::assertSame([], $response['notes']);
    }

    /** @test */
    public function isc_and_ubl_attributes_are_hidden_by_default(): void
    {
        config()->set('venezuela.visible_fiscal_features.isc', false);
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        config()->set('venezuela.visible_fiscal_features.ubl_attributes', false);
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

        self::assertFalse(LocalFiscalDocumentPolicy::showIsc());
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        self::assertFalse(LocalFiscalDocumentPolicy::showUblAttributes());
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
    }
}
// ######### FIN CAMBIO SIN XML CDR SUNAT
