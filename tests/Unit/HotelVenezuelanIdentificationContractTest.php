<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HotelVenezuelanIdentificationContractTest extends TestCase
{
    /** @test */
    public function hotel_surfaces_use_venezuelan_identification_terms_and_do_not_call_peruvian_document_services(): void
    {
        $root = dirname(__DIR__, 2);
        $guestForm = file_get_contents($root.'/modules/Hotel/Resources/assets/js/views/rooms/partials/QuantityPersons.vue');
        $checkout = file_get_contents($root.'/modules/Hotel/Resources/assets/js/views/rooms/Checkout.vue');
        $report = file_get_contents($root.'/modules/Hotel/Resources/views/rent/report_excel.blade.php');

        self::assertStringContainsString('Identificación (Cédula / RIF / Extranjero)', $guestForm);
        self::assertStringContainsString('Cédula, RIF o documento extranjero', $guestForm);
        self::assertStringNotContainsString('DNI/RUC', $guestForm);
        self::assertStringNotContainsString('/service/${type}/', $guestForm);
        self::assertStringContainsString('Identificación (Cédula, RIF o Extranjero)', $checkout);
        self::assertStringNotContainsString('DNI/RUC/CE', $checkout);
        self::assertStringContainsString('<strong>RIF: </strong>', $report);
        self::assertStringNotContainsString('Ruc:', $report);
    }

    /** @test */
    public function hotel_customer_creation_identifies_cedula_rif_and_foreign_documents(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/modules/Hotel/Resources/assets/js/views/rooms/Rent.vue'
        );

        self::assertStringContainsString('/^\\d{6,8}$/.test(number)', $source);
        self::assertStringContainsString('/^[VEJGP]\\d{9}$/.test(number)', $source);
        self::assertStringContainsString('return "4";', $source);
    }
}
