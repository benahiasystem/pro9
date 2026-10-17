<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
class ExactGuardProofTest extends TestCase
{
    /** @dataProvider cases */
    public function test_only_type_or_nonnumeric_string_guards_are_proven(string $expression, ?string $proof): void
    {
        require_once dirname(__DIR__, 2).'/scripts/exact_guard_proof.php';
        $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
        $node = $parser->parse('<?php '.$expression.';')[0]->expr;
        self::assertSame($proof, \exactAuditGuardProof($node));
    }

    public static function cases(): array
    {
        return [
            ['$currency === "VES"', 'nonnumeric_string_equality'],
            ['$amount === null', 'strict_type_guard'], ['$flag !== false', 'strict_type_guard'],
            ['$amount > 0', null], ['$amount == "1e-12"', null], ['$amount === "0"', null],
            ['$amount == 0', null], ['$amount == null', null], ['$amount / 3', null],
            ['$id > 0', null], ['$quantity === ""', null], ['$value > "VES"', null],
        ];
    }
}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
