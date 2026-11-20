<?php
namespace Tests\Unit;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\TestCase;
final class PdfInvoiceQuantityA5Test extends TestCase
{
    public static function cases(): array
    {
        $cases=[];foreach(['default','modern-2027','Plantilla_personalizable','marca_de_agua'] as $style){
            $cases[$style.' large']=[$style,'9007199254740993.01',['n'=>'900719925474099301','d'=>'100'],'9007199254740993.01'];
            $cases[$style.' tiny']=[$style,'0',['n'=>'1','d'=>'3000000000000'],'<0.000000000001'];
        }return $cases;
    }
    /** @dataProvider cases */
    public function test_invoice_a4_renders_original_quantity(string $style,string $scalar,array $original,string $expected): void
    {
        $root=dirname(__DIR__,2);$path=$style==='marca_de_agua/ticket_50'?'marca_de_agua/invoice_ticket_50.blade.php':$style.'/invoice_a5.blade.php';$source=file_get_contents($root.'/app/CoreFacturalo/Templates/pdf/'.$path);
        $pattern='~<td\b[^>]*>\s*(@if\(\(\(int\)\$row->quantity != \$row->quantity\)\).*?@endif|\{\{\s*\\\\App\\\\Support\\\\Exact\\\\PdfQuantities::line\(\$row\)\s*\}\})\s*</td>~s';
        self::assertSame(1,preg_match($pattern,$source,$m),$style);
        $compiled=(new BladeCompiler(new Filesystem(),sys_get_temp_dir()))->compileString($m[1]);
        $row=(object)['quantity'=>$scalar,'calculation_snapshot'=>['version'=>1,'values'=>['quantity'=>$original]]];
        ob_start();try{eval('?>'.$compiled);$actual=html_entity_decode(trim(ob_get_contents()),ENT_QUOTES|ENT_HTML5,'UTF-8');}finally{ob_end_clean();}
        self::assertSame($expected,str_replace(',', '', $actual));
    }
}
