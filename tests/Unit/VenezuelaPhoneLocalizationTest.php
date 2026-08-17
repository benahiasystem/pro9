<?php

namespace Tests\Unit;

use App\Support\Venezuela\Localization;
use Tests\TestCase;

// ########### INICIO CAMBIO TELEFONÍA VENEZUELA
class VenezuelaPhoneLocalizationTest extends TestCase
{
    /**
     * @test
     * @dataProvider phoneProvider
     */
    public function it_normalizes_venezuelan_phone_numbers(
        ?string $input,
        ?string $expected
    ): void {
        self::assertSame($expected, Localization::normalizePhone($input));
    }

    /** @test */
    public function it_returns_a_whatsapp_number_without_the_plus_sign(): void
    {
        self::assertSame('584121234567', Localization::whatsappNumber('+51 412 1234567'));
        self::assertNull(Localization::whatsappNumber(''));
    }

    public function phoneProvider(): array
    {
        return [
            'local' => ['0412-123.45.67', '+584121234567'],
            'already venezuelan' => ['+58 412 1234567', '+584121234567'],
            'historical peru prefix' => ['+51 412 1234567', '+584121234567'],
            'formatted' => ['(0412) 123-45-67', '+584121234567'],
            'empty' => ['   ', null],
            'null' => [null, null],
        ];
    }
}
// ########### FIN CAMBIO TELEFONÍA VENEZUELA
