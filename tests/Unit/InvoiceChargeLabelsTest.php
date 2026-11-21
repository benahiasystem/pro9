<?php
namespace Tests\Unit;
use App\Support\Exact\PdfAmounts;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
final class InvoiceChargeLabelsTest extends TestCase
{
 /** @dataProvider templates */
 public function test_real_template_charge_label_sums_each_percentage_once(string $template, string $charges, string $expected):void
 {
  $file=dirname(__DIR__,2).'/app/CoreFacturalo/Templates/pdf/'.$template;
  $source=file_get_contents($file);
  self::assertMatchesRegularExpression('/(@php\s+\$total_factor\s*=.*?@endphp)/s',$source);
  preg_match('/(@php\s+\$total_factor\s*=.*?@endphp)/s',$source,$match);
  $document=new \App\Models\Tenant\Document();$document->setRawAttributes(['charges'=>$charges],true);
  $before=$document->getAttributes();
  $compiled=(new BladeCompiler(new Filesystem(),sys_get_temp_dir()))->compileString($match[1]."\n".'{{ $total_factor }}');
  ob_start();try{eval('?>'.$compiled);$result=ob_get_contents();}finally{ob_end_clean();}
  self::assertSame($expected,trim($result),$template);
  self::assertSame($before,$document->getAttributes());
 }
 public static function templates():array
 {
  $base=dirname(__DIR__,2).'/app/CoreFacturalo/Templates/pdf/';$rows=[];
  foreach(glob($base.'*/invoice*.blade.php') as $path){$source=file_get_contents($path);if(preg_match('/@php\s+\$total_factor\s*=/s',$source)){foreach(['[{"factor":0.02},{"factor":0.03}]'=>'5','[{"percentage":"1.000000000001","factor":0.9},{"factor":0.02}]'=>'3.000000000001','[{"factor":0.000000000000015}]'=>'0.000000000002'] as $input=>$expected)$rows[substr($path,strlen($base)).' '.$expected]=[substr($path,strlen($base)),$input,$expected];}}
  // The two NRUS invoice templates were retired.
  if(count($rows)!==48)throw new \RuntimeException('Expected 48 invoice-template cases; found '.count($rows));
  return $rows;
 }
}
