<?php

namespace Tests\Unit;

use App\Services\LocalFiscalDocumentPolicy;
use Tests\TestCase;

// ########## INICIO CAMBIO SIN XML CDR SUNAT
class LocalFiscalDocumentPolicyTest extends TestCase
{
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
    public function isc_and_detractions_are_hidden_by_default(): void
    {
        config()->set('venezuela.visible_fiscal_features.isc', false);
        config()->set('venezuela.visible_fiscal_features.detractions', false);

        self::assertFalse(LocalFiscalDocumentPolicy::showIsc());
        self::assertFalse(LocalFiscalDocumentPolicy::showDetractions());
    }
}
// ######### FIN CAMBIO SIN XML CDR SUNAT
