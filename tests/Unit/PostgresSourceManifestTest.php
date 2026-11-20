<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PostgresSourceManifestTest extends TestCase
{
    public function test_fixed_reference_inputs_are_hashed_without_including_generated_results(): void
    {
        require_once dirname(__DIR__,2).'/scripts/postgresql_source_manifest.php';
        $root=sys_get_temp_dir().'/pro9-manifest-'.bin2hex(random_bytes(8));
        $directory='informes/evidencias/postgresql_17_implementacion/';
        $inputs=['app/source.php'=>'<?php return true;'];
        foreach (['esquema.json','referencia_mysql_temporal.json','referencia_rendimiento_mysql.json','casts_exactos_efectivos.json'] as $name) $inputs[$directory.$name]='{"fixed":"'.$name.'"}';
        $inputs[$directory.'resultados/generated.json']='{"result":true}';
        try {
            foreach ($inputs as $path=>$bytes) {
                if (!is_dir(dirname($root.'/'.$path))) mkdir(dirname($root.'/'.$path),0700,true);
                file_put_contents($root.'/'.$path,$bytes);
            }
            $files=\postgresqlSourceFiles($root,array_keys($inputs));
            self::assertCount(5,$files);
            foreach (array_slice($inputs,0,5,true) as $path=>$bytes) self::assertSame(hash('sha256',$bytes),$files[$path]);
            self::assertArrayNotHasKey($directory.'resultados/generated.json',$files);
            file_put_contents($root.'/'.$directory.'esquema.json','{"changed":true}');
            $after=\postgresqlSourceFiles($root,array_keys($inputs));
            self::assertNotSame($files[$directory.'esquema.json'],$after[$directory.'esquema.json']);
        } finally {
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($root);
        }
    }

    public function test_newly_captured_reference_does_not_approve_other_changed_inputs(): void
    {
        require_once dirname(__DIR__,2).'/scripts/postgresql_source_manifest.php';
        $reference='informes/evidencias/postgresql_17_implementacion/referencia_rendimiento_mysql.json';
        $before=['branch'=>'test','revision'=>'test','files'=>['app/code.php'=>'a',$reference=>'before']];
        $after=$before;$after['files'][$reference]='after';
        self::assertSame($after,\postgresqlSourceAfterReference($before,$after,$reference));
        $after['files']['app/code.php']='changed';
        $this->expectException(\RuntimeException::class);
        \postgresqlSourceAfterReference($before,$after,$reference);
    }

    public function test_browser_source_build_configuration_and_actual_assets_are_immutable_test_inputs(): void
    {
        require_once dirname(__DIR__,2).'/scripts/postgresql_source_manifest.php';
        $root=sys_get_temp_dir().'/pro9-manifest-'.bin2hex(random_bytes(8));
        $paths=['.gitattributes','vite.config.js','scripts/verify_dashboard_bundle.py','public/porto-ecommerce/assets/js/cart.js','public/porto-ecommerce/assets/js/main.js',
            'public/build/manifest.json','public/build/assets/storefront-amounts-example.js'];
        try {
            foreach ($paths as $path) {
                if (!is_dir(dirname($root.'/'.$path))) mkdir(dirname($root.'/'.$path),0700,true);
                file_put_contents($root.'/'.$path,'before');
            }
            $before=\postgresqlSourceFiles($root,$paths);
            self::assertCount(count($paths),$before);
            foreach ($paths as $path) {
                file_put_contents($root.'/'.$path,'after');
                $after=\postgresqlSourceFiles($root,$paths);
                self::assertNotSame($before[$path],$after[$path],$path);
                $this->assertCount(1,array_diff_assoc($after,$before));
                file_put_contents($root.'/'.$path,'before');
            }
        } finally {(new \Illuminate\Filesystem\Filesystem())->deleteDirectory($root);}
    }

    public function test_container_provenance_rejects_empty_missing_tampered_and_escaping_source_manifests(): void
    {
        $root=sys_get_temp_dir().'/pro9-source-provenance-'.bin2hex(random_bytes(8));
        $previous=['PRO9_POSTGRES_TESTS'=>getenv('PRO9_POSTGRES_TESTS'),'PRO9_POSTGRES_RUN_ID'=>getenv('PRO9_POSTGRES_RUN_ID')];
        $runId='pro9-pgtest-1234567890ab';
        $path=$root.'/informes/evidencias/postgresql_17_implementacion/resultados/'.$runId.'-fuentes.json';
        try {
            mkdir(dirname($path),0700,true);
            putenv('PRO9_POSTGRES_TESTS=1');putenv('PRO9_POSTGRES_RUN_ID='.$runId);
            $files=['app/example.php'=>hash('sha256','synthetic source')];
            $valid=['revision'=>str_repeat('a',40),'files'=>$files,'sha256'=>hash('sha256',json_encode($files,JSON_THROW_ON_ERROR))];
            file_put_contents($path,json_encode($valid,JSON_THROW_ON_ERROR));
            self::assertSame($valid,\Tests\Support\PostgresTestEnvironment::source($root));
            $cases=[];
            $case=$valid;$case['files']=[];$case['sha256']=hash('sha256','[]');$cases['empty']=$case;
            $case=$valid;$case['sha256']=str_repeat('0',64);$cases['tampered']=$case;
            $case=$valid;unset($case['revision']);$cases['revision_missing']=$case;
            foreach (['/outside/source.php','../outside/source.php','app/../../outside.php'] as $file) {
                $case=$valid;$case['files']=[$file=>$files['app/example.php']];
                $case['sha256']=hash('sha256',json_encode($case['files'],JSON_THROW_ON_ERROR));$cases[$file]=$case;
            }
            $case=$valid;$case['files']=['app/example.php'=>'not-a-hash'];
            $case['sha256']=hash('sha256',json_encode($case['files'],JSON_THROW_ON_ERROR));$cases['invalid_hash']=$case;
            foreach ($cases as $name=>$case) {
                file_put_contents($path,json_encode($case,JSON_THROW_ON_ERROR));$caught=null;
                try {\Tests\Support\PostgresTestEnvironment::source($root);}
                catch (\RuntimeException $error) {$caught=$error;}
                self::assertNotNull($caught,$name);
            }
            unlink($path);$caught=null;
            try {\Tests\Support\PostgresTestEnvironment::source($root);}
            catch (\RuntimeException $error) {$caught=$error;}
            self::assertNotNull($caught,'missing file');
            putenv('PRO9_POSTGRES_RUN_ID=../another-run');$caught=null;
            try {\Tests\Support\PostgresTestEnvironment::source($root);}
            catch (\LogicException $error) {$caught=$error;}
            self::assertNotNull($caught,'invalid run identifier');
        } finally {
            foreach ($previous as $name=>$value) putenv($value===false ? $name : $name.'='.$value);
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($root);
        }
    }
}
