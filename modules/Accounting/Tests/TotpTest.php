<?php
namespace Modules\Accounting\Tests;
use PHPUnit\Framework\TestCase;
use Modules\Accounting\Domain\Totp;
final class TotpTest extends TestCase {
 public function test_rfc_6238_sha1_vectors_including_post_2038():void {
  $totp=new Totp();$key='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
  foreach([59=>'94287082',1111111109=>'07081804',1111111111=>'14050471',1234567890=>'89005924',2000000000=>'69279037',20000000000=>'65353130'] as $time=>$expected)$this->assertSame($expected,$totp->code($key,$time,8));
 }
 public function test_replay_and_invalid_codes_are_rejected():void {
  $t=new Totp();$secret=$t->secret();$code=$t->code($secret,1234567890);$step=intdiv(1234567890,30);
  $this->assertSame($step,$t->counter($secret,$code,1234567890));$this->assertNull($t->counter($secret,$code,1234567890,$step));$this->assertNull($t->counter($secret,'invalid',1234567890));
 }
}
