<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal, PdfQuantities, QuotationPdfAmounts};
use PHPUnit\Framework\TestCase;

final class QuotationPdfAmountsTest extends TestCase
{
    public function test_exact_balance_and_rounding_adjustment_use_applied_receipts(): void
    {
        $quotation=(object)['total'=>'9007199254740993.01', 'payments'=>collect([
            (object)['payment'=>'9007199254740993.00', 'original_amount'=>'1', 'currency_type_id'=>'USD'],
        ])];
        self::assertSame(['balance'=>'0.01','rounding'=>'0.00','has_rounding'=>false],QuotationPdfAmounts::values($quotation));
        $quotation->total='1';
        $quotation->payments=collect(array_map(fn()=> (object)[
            'payment'=>'0.333333333333','calculation_snapshot'=>Decimal::snapshot(['payment'=>['n'=>'1','d'=>'3']]),
        ],[1,2,3]));
        self::assertSame(['balance'=>'0.00','rounding'=>'-0.01','has_rounding'=>true],QuotationPdfAmounts::values($quotation));
    }

    public function test_quantities_never_pass_through_floating_point(): void
    {
        self::assertSame('9,007,199,254,740,993',PdfQuantities::line((object)['quantity'=>'9007199254740993']));
        $third=(object)['quantity'=>'0.333333333333','calculation_snapshot'=>Decimal::snapshot(['quantity'=>['n'=>'1','d'=>'3']])];
        self::assertSame('0.333333333333',PdfQuantities::line($third));
        $tiny=(object)['quantity'=>'0','calculation_snapshot'=>Decimal::snapshot(['quantity'=>['n'=>'1','d'=>'3000000000000']])];
        self::assertSame('<0.000000000001',PdfQuantities::line($tiny));
    }

    public function test_weight_uses_only_gross_weight_and_requires_complete_valid_data(): void
    {
        $line=(object)['quantity'=>'0.333333333333',
            'calculation_snapshot'=>Decimal::snapshot(['quantity'=>['n'=>'1','d'=>'3']]),
            'attributes'=>'[{"attribute_type_id":"9999","value":"999"},{"attribute_type_id":"5031","value":0.3}]'];
        self::assertSame(['has_weight'=>true,'display'=>'0.10 Kg'],QuotationPdfAmounts::weight([$line]));
        $other=(object)['quantity'=>'1','attributes'=>'[{"attribute_type_id":"9999","value":"3"}]'];
        self::assertSame(['has_weight'=>false,'display'=>'No disponible'],QuotationPdfAmounts::weight([$other]));
        self::assertSame(['has_weight'=>true,'display'=>'No disponible'],QuotationPdfAmounts::weight([$line,$other]));
        $bad=(object)['quantity'=>'1','attributes'=>'[{"attribute_type_id":"5031","value":"sin dato"}]'];
        self::assertSame(['has_weight'=>true,'display'=>'No disponible'],QuotationPdfAmounts::weight([$bad]));
        $duplicate=(object)['quantity'=>'1','attributes'=>'[{"attribute_type_id":"5031","value":"1"},{"attribute_type_id":"5031","value":"2"}]'];
        self::assertSame(['has_weight'=>true,'display'=>'No disponible'],QuotationPdfAmounts::weight([$duplicate]));
    }

    public function test_every_quotation_template_renders_exact_balance_and_rounding(): void
    {
        $blade=new \Illuminate\View\Compilers\BladeCompiler(new \Illuminate\Filesystem\Filesystem(),sys_get_temp_dir());
        $paths=glob(dirname(__DIR__,2).'/app/CoreFacturalo/Templates/pdf/*/quotation*.blade.php');
        self::assertCount(12,$paths);
        $document=(object)['total'=>'1','currency_type'=>(object)['symbol'=>'Bs.'], 'payments'=>collect(array_map(fn()=> (object)[
            'payment'=>'0.333333333333','calculation_snapshot'=>Decimal::snapshot(['payment'=>['n'=>'1','d'=>'3']]),
        ],[1,2,3]))];
        foreach ($paths as $path) {
            $source=file_get_contents($path);
            self::assertSame(1,preg_match('/(@php\s+\$quotationAmounts\s*=.*?@endphp.*?SALDO:.*?<\/td>)/s',$source,$match),$path);
            $fragment=preg_replace('/@foreach\(\$document->payments as \$row\).*?@endforeach/s','',$match[1]);
            ob_start();
            try { eval('?>'.$blade->compileString($fragment)); $html=ob_get_contents(); }
            finally { ob_end_clean(); }
            $text=preg_replace('/\s+/',' ',strip_tags($html));
            self::assertStringContainsString('Ajuste por redondeo del saldo: Bs. -0.01',$text,$path);
            self::assertStringContainsString('SALDO: Bs. 0.00',$text,$path);
        }
    }
}
