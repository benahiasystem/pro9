<?php

namespace App\Services;

// ########## INICIO CAMBIO SIN XML CDR SUNAT
final class LocalFiscalDocumentPolicy
{
    public static function enabled(): bool
    {
        return (bool) config('venezuela.local_document_emission.enabled', true);
    }

    public static function registeredResponse(): array
    {
        return [
            'success' => true,
            'sent' => false,
            'local' => true,
            'code' => (string) config('venezuela.local_document_emission.status_code', 'LOCAL_REGISTERED'),
            'description' => (string) config(
                'venezuela.local_document_emission.status_description',
                'Documento registrado localmente sin transmisión fiscal.'
            ),
            'notes' => [],
            'xml_signed' => null,
            'hash' => null,
        ];
    }

    // ########## INICIO SIN DETRACCIONES E ISC
    public static function showIsc(): bool
    {
        return (bool) config('venezuela.visible_fiscal_features.isc', false);
    }


    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    public static function showUblAttributes(): bool
    {
        return (bool) config('venezuela.visible_fiscal_features.ubl_attributes', false);
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
    // ######### FIN SIN DETRACCIONES E ISC
}
// ######### FIN CAMBIO SIN XML CDR SUNAT
