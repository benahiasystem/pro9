<?php

namespace Tests\Unit;

final class ExactLiteralArithmeticProofTest extends \PHPUnit\Framework\TestCase
{
    /** @dataProvider literalCases */
    public function test_only_literal_integer_arithmetic_is_proven(string $source, bool $proven): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        $nodes=(new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse('<?php '.$source.';');
        $node=$nodes[0]->expr;
        self::assertSame($proven ? 'literal_integer_arithmetic' : null, \exactAuditGuardProof($node));
    }

    public static function literalCases(): array
    {
        return [
            ['24 * 60 * 60 * 1000',true], ['(13 - 2) * 7',true], ['-(1 + 2)',true], ['+15',true],
            ['9007199254740991 - 1',true], ['(-5) % 2',true], ['(-6) / 3',true], ['144 / 12',true],
            ['9007199254740991 + 1',false], ['(9007199254740991 + 1) - 1',false],
            ['0.1 + 0.2',false], ['1.0 + 2',false], ['1 / 3',false], ['1 / 0',false], ['1 % 0',false],
            ['0 / (-1)',false], ['2 ** 4',false], ['$amount + 1',false], ['(1 + 2) * $amount',false],
            ['"12" + 1',false], ['(24 + 1) === 25',false], ['(int) 1.23',false],
        ];
    }

    public function test_proven_constants_match_native_php_without_losing_a_fraction(): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        $parser=(new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
        foreach ([-97,-3,-1,0,1,2,13,41] as $a) foreach ([-41,-2,-1,0,1,3,17] as $b) {
            foreach (['+'=>fn()=>$a+$b, '-'=>fn()=>$a-$b, '*'=>fn()=>$a*$b] as $operator=>$native) {
                $node=$parser->parse('<?php ('.$a.') '.$operator.' ('.$b.');')[0]->expr;
                self::assertSame('literal_integer_arithmetic',\exactAuditGuardProof($node));
                self::assertTrue(\App\Support\Exact\Decimal::of($native())->isEqualTo(\exactAuditLiteralIntegerValue($node)));
            }
        }
    }
}
