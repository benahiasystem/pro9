<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactAuditRegistryTest extends TestCase
{
    public function test_unknown_operations_and_missing_evidence_stay_pending(): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_audit_registry.php';
        $root=sys_get_temp_dir().'/pro9-exact-audit-'.bin2hex(random_bytes(8));
        mkdir($root.'/config',0777,true);
        try {
            $row=['file'=>'source.php','symbol'=>'Price::format','operation'=>'represent','expression'=>'D::represent($value, 2)','line'=>3];
            $unknown=\exactAuditClassify([$row],$root)[0];
            self::assertSame('pending',$unknown['status']);
            $review=['category'=>'presentation','justification'=>'Formato final de precio sin reutilización.','evidence'=>['proof.php'],
                'source'=>['file'=>'source.php','symbol'=>'Price::format','operation'=>'represent','operation_language'=>'php']];
            file_put_contents($root.'/config/exact_rounding_registry.json',json_encode([$unknown['id']=>$review]));
            self::assertSame('pending',\exactAuditClassify([$row],$root)[0]['status']);
            file_put_contents($root.'/proof.php','<?php // test evidence');
            $review['evidence_sha256']=['proof.php'=>hash_file('sha256',$root.'/proof.php')];
            file_put_contents($root.'/config/exact_rounding_registry.json',json_encode([$unknown['id']=>$review]));
            self::assertSame('justified',\exactAuditClassify([$row],$root)[0]['status']);
            $malformed = $review; unset($malformed['source']['symbol']);
            self::assertFalse(\exactAuditValidReview($malformed,$root));
            $row['expression']='D::represent($value, 3)';
            self::assertSame('pending',\exactAuditClassify([$row],$root)[0]['status']);
            $review['evidence']=['../'.basename($root).'/proof.php'];
            $review['evidence_sha256'][$review['evidence'][0]]=hash_file('sha256',$root.'/proof.php');
            self::assertTrue(\exactAuditValidReview($review,$root));
            $review['evidence']=['proof.php#missing_test_symbol'];
            self::assertFalse(\exactAuditValidReview($review,$root));
            $review['evidence']=['proof.php'];
            file_put_contents($root.'/proof.php','<?php // changed evidence');
            self::assertFalse(\exactAuditValidReview($review,$root));
            $review['source']=['file'=>'source.php','operation_language'=>'php'];
            file_put_contents($root.'/config/exact_rounding_registry.json',json_encode([$unknown['id']=>$review]));
            self::assertSame('orphaned_review',\exactAuditRegistryDiagnostics([],$root,'php')[0]['reason']);
            self::assertSame([],\exactAuditRegistryDiagnostics([$unknown],$root,'php'));
            $review['evidence']=['/etc/passwd'];
            self::assertFalse(\exactAuditValidReview($review,$root));
        } finally {
            @unlink($root.'/proof.php');unlink($root.'/config/exact_rounding_registry.json');rmdir($root.'/config');rmdir($root);
        }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
