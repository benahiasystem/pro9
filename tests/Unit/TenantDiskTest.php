<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace Tests\Unit;

use App\Services\Storage\TenantDisk;
use PHPUnit\Framework\TestCase;

final class TenantDiskTest extends TestCase
{
    private function backend()
    {
        return new class {
            public array $objects = [];
            public array $calls = [];
            public bool $failPut = false;
            public function put($key,$bytes,$options) { $this->calls[]=$key; if ($this->failPut) return false; $this->objects[$key]=is_resource($bytes)?stream_get_contents($bytes):$bytes; return true; }
            public function get($key) { if (!isset($this->objects[$key])) throw new \RuntimeException('Missing'); return $this->objects[$key]; }
            public function exists($key) { return isset($this->objects[$key]); }
            public function delete($keys) { foreach((array)$keys as $key) unset($this->objects[$key]); return true; }
            public function copy($from,$to) { $this->objects[$to]=$this->get($from); return true; }
            public function files($prefix,$recursive) { return array_values(array_filter(array_keys($this->objects),fn($k)=>str_starts_with($k,$prefix.'/'))); }
            public function readStream($key) { $s=fopen('php://temp','w+b');fwrite($s,$this->get($key));rewind($s);return $s; }
            public function temporaryUrl($key,$expiry,$options) { return 'https://private.example/'.rawurlencode($key).'?expires='.$expiry->getTimestamp(); }
        };
    }

    public function test_two_tenants_with_same_names_are_isolated_and_legacy_prefix_is_normalized_once(): void
    {
        $backend=$this->backend();$a=new TenantDisk($backend,'tenant_a');$b=new TenantDisk($backend,'tenant_b');
        $a->put('public/uploads/items/same.jpg','a');$b->put('uploads/items/same.jpg','b');
        self::assertSame('a',$a->get('storage/uploads/items/same.jpg'));
        self::assertSame('b',$b->get('uploads/items/same.jpg'));
        self::assertSame(['uploads/items/same.jpg'],$a->allFiles('uploads'));
        $a->delete('uploads/items/same.jpg');self::assertTrue($b->exists('uploads/items/same.jpg'));
    }

    /** @dataProvider invalidPaths */
    public function test_rejects_untrusted_keys_before_backend_calls(string $path): void
    {
        $backend=$this->backend();$disk=new TenantDisk($backend,'tenant_a');
        try { $disk->put($path,'secret');self::fail('Invalid path accepted'); }
        catch (\InvalidArgumentException $expected) { self::assertSame([],$backend->calls); }
    }

    public static function invalidPaths(): array
    {
        return array_map(fn($p)=>[$p],['','/pdf/a.pdf','../a','a/../b','a//b','a\\b','https://outside.test/a','%2e%2e/a',"a\0b"]);
    }

    public function test_failed_write_is_not_reported_as_success(): void
    {
        $backend=$this->backend();$backend->failPut=true;
        $this->expectException(\RuntimeException::class);
        (new TenantDisk($backend,'tenant_a'))->put('pdf/a.pdf','bytes');
    }

    public function test_local_materialization_is_private_and_removed_even_when_callback_fails(): void
    {
        $backend=$this->backend();$disk=new TenantDisk($backend,'tenant_a');$disk->put('pdf/a.pdf','pdf-bytes');$path=null;
        try {
            $disk->withTemporaryFile('pdf/a.pdf',function($temporary)use(&$path){$path=$temporary;self::assertSame('pdf-bytes',file_get_contents($path));self::assertSame(0600,fileperms($path)&0777);throw new \RuntimeException('consumer');});
        } catch (\RuntimeException $expected) { self::assertSame('consumer',$expected->getMessage()); }
        self::assertNotNull($path);self::assertFileDoesNotExist($path);
        self::assertFalse(method_exists($disk,'path'));
    }

    public function test_signed_urls_contain_the_correct_namespace(): void
    {
        $disk=new TenantDisk($this->backend(),'tenant_a');
        $expiry=new \DateTimeImmutable('+120 seconds');
        $url=$disk->temporaryUrl('uploads/items/a.jpg',$expiry);
        self::assertStringContainsString('tenant_a%2Fuploads%2Fitems%2Fa.jpg',$url);
        self::assertStringContainsString('expires='.$expiry->getTimestamp(),$url);
    }

    public function test_moving_alias_to_itself_preserves_the_original(): void
    {
        $disk=new TenantDisk($this->backend(),'tenant_a');$disk->put('uploads/items/a.jpg','bytes');
        $disk->move('public/uploads/items/a.jpg','uploads/items/a.jpg');
        self::assertSame('bytes',$disk->get('uploads/items/a.jpg'));
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
