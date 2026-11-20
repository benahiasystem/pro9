<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExactPresentationProofTest extends TestCase
{
    /** @dataProvider boundaries */
    public function test_only_resolved_exact_formatters_at_final_output_are_proven(string $source, bool $expected): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        $nodes=(new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse('<?php '.$source);
        $traverser=new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver(null,['replaceNodes'=>false]));
        $traverser->addVisitor(new \PhpParser\NodeVisitor\ParentConnectingVisitor());
        $nodes=$traverser->traverse($nodes);
        $formats=(new \PhpParser\NodeFinder())->find($nodes,fn($node)=>$node instanceof \PhpParser\Node\Expr\StaticCall);
        self::assertCount(1,$formats);
        self::assertSame($expected ? 'rendered_exact_decimal' : null,\exactAuditGuardProof(reset($formats)));
    }

    public static function boundaries(): array
    {
        return [
            ['echo \\App\\Support\\Exact\\Decimal::format($fraction);',true],
            ['echo e(\\App\\Support\\Exact\\Decimal::format($fraction,2));',true],
            ['use App\\Support\\Exact\\Decimal as D; echo D::format($fraction,12,".",",");',true],
            ['echo \\App\\Support\\Exact\\Decimal::format($fraction,0);',true],
            ['echo \\App\\Support\\Exact\\Decimal::represent($fraction);',true],
            ['use App\\Support\\Exact\\Decimal as D; echo e(D::represent($fraction,12,true));',true],
            ['echo \\App\\Support\\Exact\\Decimal::represent($fraction,0,false);',true],
            ['$rounded=\\App\\Support\\Exact\\Decimal::represent($fraction,2);',false],
            ['echo \\App\\Support\\Exact\\Decimal::represent($fraction,2) + $amount;',false],
            ['echo \\App\\Support\\Exact\\Decimal::represent($fraction,2,$fixed);',false],
            ['echo \\App\\Support\\Exact\\Decimal::represent($fraction,2,"true");',false],
            ['echo \\App\\Support\\Exact\\Decimal::represent($fraction,13);',false],
            ['echo \\App\\Support\\Exact\\Decimal::represent(...$arguments);',false],
            ['echo \\Other\\Decimal::represent($fraction,2);',false],
            ['$rounded=\\App\\Support\\Exact\\Decimal::format($fraction,2);',false],
            ['echo \\App\\Support\\Exact\\Decimal::format($fraction,2) + $other;',false],
            ['echo \\App\\Support\\Exact\\Decimal::format($fraction,2) / 3;',false],
            ['echo e(\\App\\Support\\Exact\\Decimal::format($fraction,$precision));',false],
            ['echo \\App\\Support\\Exact\\Decimal::format($fraction,13);',false],
            ['echo \\App\\Support\\Exact\\Decimal::format($fraction,-1);',false],
            ['echo \\App\\Support\\Exact\\Decimal::format($fraction,2,$decimal);',false],
            ['echo \\App\\Support\\Exact\\Decimal::format(...$arguments);',false],
            ['echo \\App\\Support\\Exact\\Decimal::format(value:$fraction);',false],
            ['echo \\Other\\Decimal::format($fraction,2);',false],
            ['namespace Unknown; echo Decimal::format($fraction,2);',false],
            ['namespace Unknown; echo e(\\App\\Support\\Exact\\Decimal::format($fraction,2));',false],
            ['echo unknown(\\App\\Support\\Exact\\Decimal::format($fraction,2));',false],
        ];
    }

    public function test_final_render_does_not_approve_nested_lossy_arithmetic(): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        $nodes=(new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse(
            '<?php echo e(\\App\\Support\\Exact\\Decimal::format(round($amount / 3, 2),2));'
        );
        $traverser=new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver(null,['replaceNodes'=>false]));
        $traverser->addVisitor(new \PhpParser\NodeVisitor\ParentConnectingVisitor());
        $nodes=$traverser->traverse($nodes);
        $finder=new \PhpParser\NodeFinder();
        $format=$finder->findFirstInstanceOf($nodes,\PhpParser\Node\Expr\StaticCall::class);
        self::assertSame('rendered_exact_decimal',\exactAuditGuardProof($format));
        self::assertNull(\exactAuditGuardProof($format->args[0]->value));
        self::assertNull(\exactAuditGuardProof($format->args[0]->value->args[0]->value));
        self::assertSame('0.33',\App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::of(1)->dividedBy(3)));
        self::assertSame('123456789012345678.90',\App\Support\Exact\Decimal::format('123456789012345678.9',2,'.',''));
    }
}
