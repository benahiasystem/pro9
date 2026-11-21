<?php
namespace Tests\Unit;
use App\Models\Tenant\DocumentItem;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\TestCase;
final class InvoiceLineAdjustmentLabelsTest extends TestCase
{
    public static function cases():array
    {
        $base=dirname(__DIR__,2).'/app/CoreFacturalo/Templates/pdf/';$cases=[];
        foreach(glob($base.'*/invoice*.blade.php') as $file){
            $s=preg_replace('/\{\{--.*?--\}\}/s','',file_get_contents($file));
            foreach(['discounts'=>'dtos','charges'=>'charge'] as $kind=>$name){
                if(preg_match('/@foreach\([^\n]* as \$'.$name.'\).*?@endforeach/s',$s))$cases[substr($file,strlen($base)).' '.$kind]=[substr($file,strlen($base)),$kind,$name];
            }
        }
        // The two NRUS invoice templates were retired.
        if(count($cases)!==32)throw new \RuntimeException('Expected 32 invoice adjustment cases; got '.count($cases));return $cases;
    }
    /** @dataProvider cases */
    public function test_real_invoice_line_labels_use_original_values(string $template,string $kind,string $name):void
    {
        $file=dirname(__DIR__,2).'/app/CoreFacturalo/Templates/pdf/'.$template;
        $s=preg_replace('/\{\{--.*?--\}\}/s','',file_get_contents($file));
        self::assertSame(1,preg_match('/@foreach\([^\n]* as \$'.$name.'\).*?@endforeach/s',$s,$match));
        $json=$kind==='discounts'?'[{"factor":0.9,"percentage":"1.000000000001","description":"visible"},{"factor":0.9,"percentage":"2","description":"oculto","from_global_distribution":true}]':'[{"factor":0.9,"percentage":"1.000000000001","amount":9007199254740993.01,"description":"visible"}]';
        $row=new DocumentItem();$row->setRawAttributes([$kind=>$json],true);$document=(object)['currency_type'=>(object)['symbol'=>'Bs.']];
        $__env=new class {public function addLoop($v){}public function incrementLoopIndices(){}public function getLastLoop(){return null;}public function popLoop(){}};
        $before=$row->getAttributes();$compiled=(new BladeCompiler(new Filesystem(),sys_get_temp_dir()))->compileString($match[0]);
        ob_start();try{eval('?>'.$compiled);$out=ob_get_contents();}finally{ob_end_clean();}
        self::assertStringContainsString('1.000000000001%',preg_replace('/\s+/','',$out),$template);
        if($kind==='discounts')self::assertStringNotContainsString('oculto',$out);
        else self::assertStringContainsString('9007199254740993.01',str_replace(',','',$out));
        self::assertSame($before,$row->getAttributes());
    }
    public static function discountCases():array
    {
        return array_filter(self::cases(),fn($case)=>$case[1]==='discounts');
    }
    /** @dataProvider discountCases */
    public function test_discount_legacy_factor_and_fixed_amount_labels_remain_valid(string $template,string $kind,string $name):void
    {
        $file=dirname(__DIR__,2).'/app/CoreFacturalo/Templates/pdf/'.$template;
        $s=preg_replace('/\{\{--.*?--\}\}/s','',file_get_contents($file));
        self::assertSame(1,preg_match('/@foreach\([^\n]* as \$'.$name.'\).*?@endforeach/s',$s,$match));
        $row=new DocumentItem();$row->setRawAttributes(['discounts'=>'[{"factor":0.125,"description":"porcentaje"},{"factor":0.9,"is_amount":true,"description":"importe fijo"}]'],true);
        $__env=new class {public function addLoop($v){}public function incrementLoopIndices(){}public function getLastLoop(){return null;}public function popLoop(){}};
        $compiled=(new BladeCompiler(new Filesystem(),sys_get_temp_dir()))->compileString($match[0]);ob_start();try{eval('?>'.$compiled);$out=ob_get_contents();}finally{ob_end_clean();}
        self::assertStringContainsString('12.5%',preg_replace('/\s+/','',$out));
        self::assertStringNotContainsString('90%',$out);self::assertStringContainsString('importe fijo',$out);
    }
}
