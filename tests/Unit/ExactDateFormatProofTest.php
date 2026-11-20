<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ######## INICIO AUDITORIA CALCULOS EXACTOS SAB ########
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

    public function test_resolved_children_are_available_when_a_parent_expression_is_visited(): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        $parser=(new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
        $nodes=\exactAuditResolveNames($parser->parse('<?php use Carbon\\Carbon as Clock; use function count as nativeCount; Clock::parse($text)->format("Y-m-d"); nativeCount($rows) > 0;'));
        $visitor=new class extends \PhpParser\NodeVisitorAbstract {
            public array $proofs=[];
            public function enterNode(\PhpParser\Node $node) {
                if ($node instanceof \PhpParser\Node\Expr\MethodCall || $node instanceof \PhpParser\Node\Expr\BinaryOp) {
                    $this->proofs[]=\exactAuditGuardProof($node);
                }
            }
        };
        $traverser=new \PhpParser\NodeTraverser();$traverser->addVisitor($visitor);$traverser->traverse($nodes);
        self::assertSame(['known_date_format','native_counter_comparison'],$visitor->proofs);
    }
}
// ######## FIN AUDITORIA CALCULOS EXACTOS SAB ########
