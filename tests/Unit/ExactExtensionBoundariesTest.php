<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal;
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactExtensionBoundariesTest extends TestCase
{
    public function test_excel_rejects_a_fraction_that_would_be_silently_truncated(): void
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $record = ['id'=>1, 'amount'=>'0', 'calculation_snapshot'=>[
            'version'=>1, 'inputs'=>[], 'values'=>['amount'=>['n'=>'1','d'=>str_repeat('9',33000)]],
        ]];
        $this->expectException(\LengthException::class);
        \App\Support\Exact\FractionSheet::append($book->getActiveSheet(), [$record], ['amount']);
    }

    public function test_decimal_rule_preserves_tiny_amounts_and_rejects_precision_and_range_overflow(): void
    {
        $rule=new \App\Rules\ExactDecimal('0',true);
        self::assertTrue($rule->passes('amount','0.000000000001'));
        foreach (['0','-1','0.0000000000001','1000000000000000000','NaN',true,[]] as $invalid) self::assertFalse($rule->passes('amount',$invalid));
        self::assertTrue((new \App\Rules\ExactDecimal('0',true,20))->passes('sales_limit','99999999999999999999.999999999999'));
    }

    public function test_massive_invoice_workbook_keeps_decimal_text_and_fraction_details(): void
    {
        $third=Decimal::of(1)->dividedBy(3);
        $row=new \Illuminate\Support\Fluent(['id'=>7,'ruc_emisor'=>'J123456789 - =1+1','ruc'=>'V12345678 - CLIENTE',
            'tipo_comprobante'=>'01','serie_comprobante'=>'F1-7','total_gravado'=>'0.333333333333','total_igv'=>'0','total_venta'=>'0.333333333333',
            'calculation_snapshot'=>Decimal::snapshot(['cantidad'=>'1','precio'=>$third,'total_gravado'=>$third,'total_igv'=>'0','total_venta'=>$third])]);
        $export=(new \App\Exports\MassiveInvoiceExport())->records(collect([$row]));
        $bytes=\Maatwebsite\Excel\Facades\Excel::raw($export,\Maatwebsite\Excel\Excel::XLSX);
        $path=tempnam(sys_get_temp_dir(),'pro9-exact-export-');
        try {
            file_put_contents($path,$bytes);
            $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            self::assertSame(2,$book->getSheetCount());
            self::assertSame('s',$book->getSheet(0)->getCell('L2')->getDataType());
            self::assertSame('0.333333333333',$book->getSheet(0)->getCell('L2')->getValue());
            self::assertSame('s',$book->getSheet(0)->getCell('D2')->getDataType());
            self::assertSame('=1+1',$book->getSheet(0)->getCell('D2')->getValue());
            $details=$book->getSheetByName('Fracciones exactas');
            self::assertSame('1',$details->getCell('D3')->getValue());
            self::assertSame('3',$details->getCell('E3')->getValue());
            self::assertSame('s',$details->getCell('D3')->getDataType());
            $book->disconnectWorksheets();
        } finally {unlink($path);}
    }

    public function test_massive_invoice_math_keeps_the_inclusive_price_and_periodic_base(): void
    {
        $one=\App\Services\MassiveInvoiceService::calculateAmounts('500','1','10',true);
        $hundred=\App\Services\MassiveInvoiceService::calculateAmounts('500','100','10',true);
        self::assertSame('500',$one['total']);
        self::assertSame('50000',$hundred['total']);
        self::assertSame('500',Decimal::represent(Decimal::read($one,'valorUnitario')->multipliedBy('1.16')));
        self::assertTrue(Decimal::read($one,'total')->multipliedBy('100')->isEqualTo(Decimal::read($hundred,'total')));
        $net=\App\Services\MassiveInvoiceService::calculateAmounts('0.123456789012','3','10',false);
        self::assertSame('0.42962962576176',(string)Decimal::read($net,'total')->toBigDecimal());
    }

    public function test_payment_adaptation_retains_the_exact_requested_fraction(): void
    {
        $payment=new \App\Models\Tenant\SaleNotePayment(['payment'=>Decimal::of(1)->dividedBy(3)]);
        \App\Support\Exact\Settlement::prepare($payment);
        self::assertSame('0.33',$payment->payment);
        self::assertSame('1',Decimal::represent($payment->exactValue('requested_payment')->multipliedBy(3)));
        self::assertSame(['n'=>'-1','d'=>'300'],Decimal::fraction($payment->exactValue('cash_adjustment')));
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
