<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ExactLocalDateProofTest extends TestCase
{
    private function proofs(string $source): array
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        $nodes=\exactAuditResolveNames((new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse('<?php '.$source));
        $traverser=new \PhpParser\NodeTraverser();$traverser->addVisitor(new \PhpParser\NodeVisitor\ParentConnectingVisitor());
        $nodes=$traverser->traverse($nodes);
        $matches=(new \PhpParser\NodeFinder())->find($nodes,static fn($node)=>($node instanceof \PhpParser\Node\Expr\MethodCall
            || $node instanceof \PhpParser\Node\Expr\NullsafeMethodCall) && $node->name instanceof \PhpParser\Node\Identifier
            && (string)$node->name==='format');
        return array_map(static fn($node)=>\exactAuditGuardProof($node),$matches);
    }
    /** @dataProvider bindings */
    public function test_only_a_unique_dominating_local_date_assignment_is_proven(string $source, ?string $expected): void
    {
        self::assertSame([$expected],$this->proofs($source));
    }
    public static function bindings(): array
    {
        $yes='known_date_format';
        return [
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); return $date->format("Y-m-d");}',$yes],
            ['namespace A; use Carbon\\Carbon as Clock; class R {function f($raw) {$date=Clock::parse($raw); return $date->format("c");}}',$yes],
            ['function f($raw) {$due=$raw ? \\Carbon\\Carbon::parse($raw) : null; return $due?->format("Y-m-d");}',$yes],
            ['function f($raw) {$due=$raw ? null : new \\DateTimeImmutable(); return $due?->format("c");}',$yes],
            ['function f($rows) {foreach ($rows as $row) {$date=\\Carbon\\Carbon::parse($row); echo $date->format("Y-m-d");}}',$yes],
            ['function f($raw) {if ($raw) {$date=\\Carbon\\Carbon::parse($raw); return $date->format("c");}}',$yes],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); if ($raw) {return $date->copy()->format("c");}}',$yes],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); $copy=$date; return $copy->format("c");}',$yes],
            ['function f($amount) {return $amount->format(2);}',null],
            ['function f($date) {$date=\\Carbon\\Carbon::now(); return $date->format("c");}',null],
            ['function f($raw) {if ($raw) {$date=\\Carbon\\Carbon::parse($raw);} return $date->format(2);}',null],
            ['function f() {$date=\\App\\Support\\Exact\\Decimal::of("1/3"); return $date->format(2);}',null],
            ['namespace A; use Money\\Carbon as Clock; function f() {$date=Clock::now(); return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); $date=$money; return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); echo $date->format("c"); $date=$money;}',null],
            ['function f($raw) {echo $date->format(2); $date=\\Carbon\\Carbon::parse($raw);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); unknown($date); return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); $alias=&$date; return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); $alias=[&$date]; return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); $fn=function() use (&$date) {$date=123;}; return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); unset($date); return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); foreach ($raw as $date) {} return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); extract($raw); return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); eval($raw); return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); $$name=$raw; return $date->format(2);}',null],
            ['function f($raw) {global $date; $date=\\Carbon\\Carbon::parse($raw); return $date->format(2);}',null],
            ['function f($raw) {goto target; $date=\\Carbon\\Carbon::parse($raw); target: return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); include $raw; return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); try {} catch (\\Exception $date) {} return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); $date++; return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); $date+=1; return $date->format(2);}',null],
            ['function f($raw) {$date=\\Carbon\\Carbon::parse($raw); $date->unknownMacro()->format(2);}',null],
            ['function f($raw) {$date=$other; $other=$date; return $other->format(2);}',null],
            ['function f($raw) {$fn=fn($date)=>$date->format(2);}',null],
        ];
    }
    public function test_date_proof_does_not_approve_nested_monetary_arithmetic(): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        $nodes=\exactAuditResolveNames((new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse('<?php function f($total) {$date=\\Carbon\\Carbon::parse($total*0.1); echo $date->format("c");}'));
        $traverser=new \PhpParser\NodeTraverser();$traverser->addVisitor(new \PhpParser\NodeVisitor\ParentConnectingVisitor());$nodes=$traverser->traverse($nodes);
        $finder=new \PhpParser\NodeFinder();
        self::assertNull(\exactAuditGuardProof($finder->findFirstInstanceOf($nodes,\PhpParser\Node\Expr\BinaryOp\Mul::class)));
        $format=$finder->findFirstInstanceOf($nodes,\PhpParser\Node\Expr\MethodCall::class);
        self::assertSame('known_date_format',\exactAuditGuardProof($format));
    }
    public function test_audit_parent_expression_sees_the_complete_receiver_ancestry(): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_guard_proof.php';
        $nodes=\exactAuditResolveNames((new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse('<?php function f() {$date=\\Carbon\\Carbon::now(); return $date->format("c");}'));
        $visitor=new class extends \PhpParser\NodeVisitorAbstract {
            public array $proofs=[];
            public function enterNode(\PhpParser\Node $node) {
                if ($node instanceof \PhpParser\Node\Expr\MethodCall && (string)$node->name==='format') $this->proofs[]=\exactAuditGuardProof($node);
            }
        };
        $traverser=new \PhpParser\NodeTraverser();$traverser->addVisitor($visitor);$traverser->traverse($nodes);
        self::assertSame(['known_date_format'],$visitor->proofs);
    }
    public function test_local_date_bindings_never_leak_between_functions(): void
    {
        self::assertSame(['known_date_format',null],$this->proofs('function f() {$date=\\Carbon\\Carbon::now(); return $date->format("c");} function g($date) {return $date->format(2);}'));
    }
}
