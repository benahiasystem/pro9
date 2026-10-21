<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
class ExactDateFormatProofTest extends TestCase
{
    /** @dataProvider cases */
    public function test_only_resolved_date_receivers_are_approved(string $source, ?string $expected): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        $nodes = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse('<?php '.$source);
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver(null,['replaceNodes'=>false]));
        $nodes = $traverser->traverse($nodes);
        $statement = (new \PhpParser\NodeFinder())->findFirstInstanceOf($nodes,\PhpParser\Node\Stmt\Expression::class);
        self::assertSame($expected,\exactAuditGuardProof($statement->expr));
    }

    public static function cases(): array
    {
        return [
            ['\\Carbon\\Carbon::parse($date)->format("Y-m-d");','known_date_format'],
            ['namespace A; use Carbon\\Carbon as Clock; Clock::now()->endOfMonth()->format("Y-m-d");','known_date_format'],
            ['(new \\DateTimeImmutable($date))->setTimezone($tz)->format("c");','known_date_format'],
            ['namespace A; use Money\\Carbon as Clock; Clock::now()->format(2);',null],
            ['namespace A; Carbon::now()->format(2);',null],
            ['$amount->format(2);',null],
            ['\\App\\Support\\Exact\\Decimal::format($amount,2);',null],
            ['\\Carbon\\Carbon::now()->unknownMacro()->format("Y-m-d");',null],
            ['\\DateTimeImmutable::now()->format("Y-m-d");',null],
            ['(new \\DateTimeImmutable())->copy()->format("Y-m-d");',null],
            ['\\Carbon\\Carbon::now()->diffInDays($date)->format(2);',null],
            ['$date?->format("Y-m-d");',null],
        ];
    }
}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
