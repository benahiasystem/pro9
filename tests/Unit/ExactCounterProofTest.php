<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
class ExactCounterProofTest extends TestCase
{
    public function test_only_resolved_native_counters_compared_with_integer_literals_are_proven(): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        foreach ([
            ['count($rows) > 0',true], ['\\count($rows) === 1',true], ['0 < strlen($text)',true],
            ['sizeof($rows) != 0',true], ['count($rows) > $amount',false], ['count($rows) * $price',false],
            ['count($rows) > 0.1',false], ['$rows->count() > 0',false], ['fakeCount($rows) > 0',false],
            ['count([round($total / 3, 2)]) > 0',true],
        ] as [$expression,$expected]) {
            $parser=(new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
            $traverser=new \PhpParser\NodeTraverser();$traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver(null,['replaceNodes'=>false]));
            $nodes=$traverser->traverse($parser->parse('<?php '.$expression.';'));
            self::assertSame($expected ? 'native_counter_comparison' : null,\exactAuditGuardProof($nodes[0]->expr),$expression);
        }
        $parser=(new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
        $traverser=new \PhpParser\NodeTraverser();$traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver(null,['replaceNodes'=>false]));
        foreach (['namespace Unknown; count($rows) > 0;', 'namespace Unknown; use function Custom\\count; count($rows) > 0;'] as $source) {
            $nodes=$traverser->traverse($parser->parse('<?php '.$source));
            $finder=new \PhpParser\NodeFinder();$binary=$finder->findFirstInstanceOf($nodes,\PhpParser\Node\Expr\BinaryOp::class);
            self::assertNull(\exactAuditGuardProof($binary),$source);
        }
        $node=$parser->parse('<?php round($total / 3, 2);')[0]->expr;
        self::assertNull(\exactAuditGuardProof($node));
        self::assertNull(\exactAuditGuardProof($node->args[0]->value));
    }
}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
