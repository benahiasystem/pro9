<?php
namespace Tests\Unit;

final class ExactAuditScopeTest extends \PHPUnit\Framework\TestCase
{
    public function test_billing_acceptance_preserves_global_findings_and_unknown_diagnostics(): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_audit_scope.php';
        $rows=[['id'=>'billing','file'=>'billing/Invoice.php','symbol'=>'Invoice::save','status'=>'pending'],
            ['id'=>'method','file'=>'billing/Invoice.php','symbol'=>'Invoice::catalog','status'=>'pending'],
            ['id'=>'outside','file'=>'purchases/Purchase.php','status'=>'pending'],
            ['id'=>'valid','file'=>'billing/nested/Pdf.php','status'=>'justified']];
        $errors=[['reason'=>'stale','source'=>['file'=>'billing/Invoice.php','symbol'=>'Invoice::catalog']],
            ['reason'=>'orphaned_review','source'=>['file'=>'purchases/Removed.php']],['reason'=>'missing_source']];
        $before=$rows;
        $policy=['patterns'=>['billing/**'],'excluded_files'=>['billing/nested/Pdf.php'],
            'excluded_methods'=>['billing/Invoice.php'=>['Invoice::catalog']],'decision'=>'Facturación explícita'];
        $result=\exactAuditScopeSummary($rows,$errors,['billing/Invoice.php','billing/nested/Pdf.php','purchases/Purchase.php'],$policy);
        self::assertSame($before,$rows);self::assertSame(1,$result['candidates']);self::assertSame(1,$result['pending']);
        self::assertSame(['billing/Invoice.php'],$result['scanned_files']);
        self::assertSame(2,$result['pending_outside_scope']);self::assertSame([$errors[0],$errors[2]],$result['registry_errors']);
        self::assertSame(1,$result['registry_errors_outside_scope']);
        self::assertFalse(\exactAuditScopeContains('billingSibling/Invoice.php',$policy));
        self::assertFalse(\exactAuditScopeContains('billing/nested/Pdf.php',$policy));
        self::assertFalse(\exactAuditScopeContains('billing/deep/Pdf.php',['patterns'=>['billing/*.php']]));
        self::assertSame(null,\exactAuditScopeArgument(['audit','--check']));
        self::assertSame('billing',\exactAuditScopeArgument(['--check','--scope=billing']));
    }

    /** @dataProvider invalidScopes */
    public function test_unknown_or_duplicate_scopes_fail_closed(array $arguments): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_audit_scope.php';
        $this->expectException(\InvalidArgumentException::class);\exactAuditScopeArgument($arguments);
    }
    public static function invalidScopes(): array { return [[['--scope=other']],[['--scope=billing','--scope=billing']]]; }

    public function test_missing_or_excluded_required_sources_cannot_approve_billing(): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_audit_scope.php';
        $root=sys_get_temp_dir().'/pro9-scope-test-'.bin2hex(random_bytes(6));mkdir($root.'/config',0700,true);
        try {
            file_put_contents($root.'/Invoice.php','<?php');
            foreach ([['Missing.php'],['Invoice.php']] as $required) {
                file_put_contents($root.'/config/exact_audit_scopes.json',json_encode(['version'=>1,'billing'=>[
                    'patterns'=>['different/**'],'required_files'=>$required,'decision'=>'Facturación explícita']]));
                try {\exactAuditScopePolicy($root,'billing');self::fail('Una fuente obligatoria ausente o excluida no acredita cobertura.');}
                catch (\RuntimeException $error) {self::assertStringContainsString('fuente obligatoria',$error->getMessage());}
            }
            $this->expectException(\RuntimeException::class);
            \exactAuditScopeSummary([],[],['outside.php'],['patterns'=>['billing/**'],'decision'=>'Facturación']);
        } finally {
            unlink($root.'/Invoice.php');unlink($root.'/config/exact_audit_scopes.json');rmdir($root.'/config');rmdir($root);
        }
    }
}
