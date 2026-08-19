<?php

namespace Tests\Unit;

use App\Support\System\Rif;
use PHPUnit\Framework\TestCase;

// ########## INICIO CAMBIO RIF SUPER ADMIN
class SystemRifTest extends TestCase
{
    /** @test */
    public function it_normalizes_the_complete_rif(): void
    {
        self::assertSame('J123456789', Rif::normalize(' j-123.456 789 '));
    }

    /** @test */
    public function it_accepts_every_supported_prefix(): void
    {
        foreach (['V', 'E', 'J', 'P', 'G'] as $prefix) {
            self::assertTrue(Rif::isValid($prefix . '123456789'), $prefix);
        }
    }

    /** @test */
    public function it_rejects_invalid_prefixes_lengths_and_characters(): void
    {
        foreach (['R123456789', 'J12345678', 'J1234567890', 'J12345A789', '1234567890', ''] as $rif) {
            self::assertFalse(Rif::isValid($rif), $rif);
        }
    }
}
// ######### FIN CAMBIO RIF SUPER ADMIN
