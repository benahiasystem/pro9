<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TestDocumentsDeletionContractTest extends TestCase
{
    // ######## INICIO PC-17 PRUEBAS DE ELIMINACIÓN DE DOCUMENTOS DE PRUEBA ########
    /** @test */
    public function the_purge_requires_an_admin_and_an_explicit_confirmation(): void
    {
        $request = $this->source('app/Http/Requests/Tenant/DeleteTestDocumentsRequest.php');
        $form = $this->source('resources/js/views/tenant/options/form.vue');

        self::assertStringContainsString("optional(\$this->user())->isAdmin()", $request);
        self::assertStringContainsString("'confirmation' => ['required', 'string', 'in:ELIMINAR']", $request);
        self::assertStringContainsString('this.$prompt(', $form);
        self::assertStringContainsString("confirmation: 'ELIMINAR'", $form);
    }

    /** @test */
    public function the_purge_deletes_test_document_relations_transactionally_before_the_documents(): void
    {
        $controller = $this->source('app/Http/Controllers/Tenant/OptionController.php');

        self::assertStringContainsString("DB::connection('tenant')->transaction", $controller);
        self::assertStringContainsString("Document::where('soap_type_id', '01')->get()", $controller);
        self::assertStringContainsString('$document->items()->delete();', $controller);
        self::assertStringContainsString('$document->payments()->each', $controller);
        self::assertStringContainsString('$document->items()->delete();', $controller);
        self::assertStringContainsString('$document->inventory_kardex()->delete();', $controller);
        self::assertLessThan(
            strpos($controller, '$document->inventory_kardex()->delete();'),
            strpos($controller, '$document->items()->delete();')
        );
        self::assertLessThan(
            strpos($controller, '$saleNote->inventory_kardex()->delete();'),
            strpos($controller, '$saleNote->items()->delete();')
        );
        self::assertStringContainsString('$payment->cashDocumentPayments()->delete();', $controller);
        self::assertStringContainsString('CashDocument::whereIn(\'document_id\', $documentIds)->delete();', $controller);
        self::assertStringContainsString('Document::whereIn(\'id\', $documentIds)->delete();', $controller);
        self::assertStringContainsString('SaleNote::whereIn(\'id\', $saleNoteIds)->delete();', $controller);
    }

    /** @test */
    public function the_advanced_settings_only_expose_the_action_to_administrators(): void
    {
        $view = $this->source('resources/views/tenant/advanced/index.blade.php');
        $configuration = $this->source('resources/js/views/tenant/configurations/form.vue');
        $form = $this->source('resources/js/views/tenant/options/form.vue');

        self::assertStringContainsString('auth()->user()->isAdmin()', $view);
        self::assertStringContainsString(':can-delete-test-documents="canDeleteTestDocuments"', $configuration);
        self::assertStringContainsString('v-if="canDeleteTestDocuments"', $form);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertNotFalse($source, $path);

        return $source;
    }
    // ######## FIN PC-17 PRUEBAS DE ELIMINACIÓN DE DOCUMENTOS DE PRUEBA ########
}
