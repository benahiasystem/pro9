<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
class ExactSourceCoverageTest extends TestCase
{
    public function test_new_production_sources_cannot_disappear_from_coverage(): void
    {
        require_once dirname(__DIR__,2).'/scripts/exact_source_coverage.php';
        $root = sys_get_temp_dir().'/pro9-source-'.bin2hex(random_bytes(8));
        mkdir($root.'/routes',0777,true); mkdir($root.'/public/build',0777,true);
        try {
            file_put_contents($root.'/routes/consumer.php','<?php $total = round($price, 2);');
            file_put_contents($root.'/public/new-consumer.js','const total = Number(price)');
            file_put_contents($root.'/public/build/generated.js','const total = Number(price)');
            $policy = ['roots'=>['routes','public'], 'excluded_directory_names'=>[], 'exclusions'=>[['path'=>'public/build','reason'=>'Sources are audited separately.']]];
            $php = \exactAuditSourceFiles($root,$policy,'php');
            $js = \exactAuditSourceFiles($root,$policy,'javascript');
            self::assertSame(['routes/consumer.php'],$php);
            self::assertSame(['public/new-consumer.js'],$js);
            self::assertSame(['public/new-consumer.js'],\exactAuditMissingSources($js,[]));
            self::assertSame([],\exactAuditMissingSources($js,$js));
        } finally {
            unlink($root.'/routes/consumer.php'); unlink($root.'/public/new-consumer.js'); unlink($root.'/public/build/generated.js');
            rmdir($root.'/routes'); rmdir($root.'/public/build'); rmdir($root.'/public'); rmdir($root);
        }
    }
}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
