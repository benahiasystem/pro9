<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalInventoryPolicy;
use PHPUnit\Framework\TestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalInventoryPolicyTest extends TestCase
{
    /** @dataProvider documentCases */
    public function test_stock_effect_matches_the_document_purpose(string $type, ?string $reason, bool $expected): void
    {
        self::assertSame($expected, FiscalInventoryPolicy::affectsStock($type, $reason));
    }

    public static function documentCases(): array
    {
        return [['01', null, true], ['07', '01', true], ['07', '07', true], ['07', '04', false], ['07', '09', false], ['07', '13', false], ['07', null, false], ['08', null, false], ['80', null, true]];
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
