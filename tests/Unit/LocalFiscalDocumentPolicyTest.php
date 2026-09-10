<?php

namespace Tests\Unit;

use App\Services\LocalFiscalDocumentPolicy;
use App\CoreFacturalo\Requests\Inputs\Common\ActionInput;
use App\CoreFacturalo\Requests\Api\Transform\Common\ActionTransform;
use Tests\TestCase;

// ########## INICIO CAMBIO SIN XML CDR SUNAT
class LocalFiscalDocumentPolicyTest extends TestCase
{
    public function test_document_actions_preserve_email_pdf_and_printing_only(): void
    {
        $actions = ActionTransform::transform(['acciones' => [
            'enviar_email' => true,
            'formato_pdf' => 'ticket',
            'auto_print' => true,
            'name_printer' => 'Caja',
            'client_public_ip' => '192.0.2.10',
            'enviar_xml_firmado' => true,
        ]]);

        self::assertSame([
            'send_email' => true,
            'format_pdf' => 'ticket',
            'auto_print' => true,
            'name_printer' => 'Caja',
            'client_public_ip' => '192.0.2.10',
        ], ActionInput::set(['actions' => $actions]));
        self::assertArrayNotHasKey('send_xml_signed', $actions);
        self::assertSame([
            'send_email' => false,
            'format_pdf' => 'a4',
            'auto_print' => false,
            'name_printer' => null,
            'client_public_ip' => null,
        ], ActionInput::set([]));
    }

    public function test_retired_transport_cannot_be_reenabled_by_legacy_configuration(): void
    {
        config()->set('venezuela.local_document_emission.enabled', false);
        self::assertTrue(LocalFiscalDocumentPolicy::enabled());
        self::assertArrayNotHasKey('sent', LocalFiscalDocumentPolicy::registeredResponse());
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
        self::assertTrue($response['local']);
        self::assertSame('LOCAL_REGISTERED', $response['code']);
        self::assertArrayNotHasKey('xml_signed', $response);
        self::assertArrayNotHasKey('hash', $response);
        self::assertSame([], $response['notes']);
    }

    /** @test */
    public function isc_has_no_visibility_switch_and_ubl_attributes_are_hidden(): void
    {
        self::assertArrayNotHasKey('isc', config('venezuela.visible_fiscal_features'));
        self::assertFalse(method_exists(LocalFiscalDocumentPolicy::class, 'showIsc'));
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        config()->set('venezuela.visible_fiscal_features.ubl_attributes', false);
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        self::assertFalse(LocalFiscalDocumentPolicy::showUblAttributes());
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
    }

    /** @test */
    public function massive_invoicing_remains_available_with_local_emission_statuses_and_pdf_only(): void
    {
        $migration = file_get_contents(database_path('migrations/2025_06_10_092834_create_massive_invoices_table.php'));
        $controller = file_get_contents(app_path('Http/Controllers/System/MassiveInvoiceController.php'));
        $model = file_get_contents(app_path('Models/System/MassiveInvoice.php'));
        $export = file_get_contents(app_path('Exports/MassiveInvoiceExport.php'));
        $view = file_get_contents(resource_path('js/views/system/massive_invoice/index.vue'));
        $routes = file_get_contents(base_path('routes/web.php'));

        self::assertStringContainsString("Route::get('massive-invoice'", $routes);
        self::assertStringContainsString("Route::post('massive-invoice/process'", $routes);
        self::assertStringContainsString("Route::get('massive-invoice/export'", $routes);
        self::assertStringNotContainsString("massive-invoice/config", $routes);

        foreach ([$migration, $controller, $model, $export, $view] as $source) {
            self::assertStringNotContainsString('estado_sunat', $source);
            self::assertStringNotContainsString('mensaje_sunat', $source);
        }

        self::assertStringContainsString('estado_emision', $migration);
        self::assertStringContainsString('mensaje_emision', $migration);
        self::assertStringContainsString("'Registrado localmente'", $controller);
        self::assertStringContainsString("if (\$type !== 'pdf')", $controller);
        self::assertStringContainsString("downloadFile(record.id, 'pdf')", $view);
    }

    /** @test */
    public function document_tables_keep_the_current_offline_client_flag_available(): void
    {
        self::assertTrue(method_exists(
            \App\Http\Controllers\Tenant\DocumentController::class,
            'getIsClient'
        ));
    }
}
// ######### FIN CAMBIO SIN XML CDR SUNAT
