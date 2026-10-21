<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExactArrayUnionProofTest extends TestCase
{
    /** @dataProvider cases */
    public function test_only_array_literal_unions_have_a_structural_proof(string $source, ?string $expected): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        $nodes = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse('<?php '.$source);
        self::assertSame($expected,\exactAuditGuardProof($nodes[0]->expr));
    }

    public static function cases(): array
    {
        return [['$row + ["value"=>$price];','array_union'],['["id"=>1] + $row;','array_union'],
            ['$row += ["id"=>1];','array_union'],['$row + $unknown;',null],['$price + 1;',null],
            ['$price += $delta;',null],['$price - ["id"=>1];',null],['array_merge($a,$b);',null],
            ['$a + ["price"=>round($price,2)];','array_union']];
    }

    public function test_union_proof_does_not_approve_nested_rounding(): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        $nodes = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse('<?php $a + ["price"=>round($price,2)];');
        $round = (new \PhpParser\NodeFinder())->findFirstInstanceOf($nodes,\PhpParser\Node\Expr\FuncCall::class);
        self::assertNull(\exactAuditGuardProof($round));
    }
}
