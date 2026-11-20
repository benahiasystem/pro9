<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ######## INICIO AUDITORIA CALCULOS EXACTOS SAB ########
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
            ['$id > 0', null], ['$quantity === ""', 'strict_empty_string_guard'], ['$value > "VES"', null],
            ['$amount == ""', null], ['$quantity !== ""', 'strict_empty_string_guard'],
            ['$kind === \\App\\Models\\Tenant\\Document::class', 'class_name_equality'],
            ['$kind == Document::class', 'class_name_equality'],
            ['$amount > Document::class', null], ['$amount === Document::AMOUNT', null],
            ['$amount === $class::class', null],
        ];
    }

    public function test_strict_empty_checks_do_not_coerce_zero_or_decimal_text(): void
    {
        self::assertTrue('' === '');
        foreach ([null,false,0,0.0,'0','0.000000000001','1/3',[]] as $value) self::assertFalse($value === '');
    }
}
// ######## FIN AUDITORIA CALCULOS EXACTOS SAB ########
