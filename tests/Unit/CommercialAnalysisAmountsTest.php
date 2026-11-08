<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal as D;
use Carbon\Carbon;
use Modules\Report\Services\CommercialAnalysisAmounts;
use Tests\TestCase;

class CommercialAnalysisAmountsTest extends TestCase
{
    public function test_currency_and_frozen_units_keep_fractional_quantities_separate(): void
    {
        $line=fn ($fraction,$unit)=>(object)['quantity'=>D::represent($fraction),
            'calculation_snapshot'=>D::snapshot(['quantity'=>$fraction]),'item'=>(object)['unit_type_id'=>$unit],
            'relation_item'=>(object)['category'=>(object)['name'=>'Repuestos']]];
        $doc=fn ($id,$currency,$date,$quantity,$unit,$state='01')=>(object)['id'=>$id,'number_full'=>'F-'.$id,
            'document_type_id'=>'01','state_type_id'=>$state,'currency_type_id'=>$currency,'date_of_issue'=>Carbon::parse($date),
            'total'=>D::represent($quantity),'calculation_snapshot'=>D::snapshot(['total'=>$quantity]),'items'=>collect([$line($quantity,$unit)])];
        $customer=(object)['id'=>1,'number_full'=>'123','name'=>'Prueba','number'=>'123','identity_document_type_id'=>'1',
            'identity_document_type'=>(object)['description'=>'Venezolano'],'telephone'=>null,'country'=>null,'department'=>null,
            'province'=>null,'district'=>null,'documents'=>collect([
                $doc(2,'USD','2026-09-11',['n'=>'1','d'=>'3'],'KG'),
                $doc(1,'VES','2026-09-10',['n'=>'2','d'=>'3'],'UND'),
                $doc(3,'VES','2026-09-11',['n'=>'1','d'=>'3'],'UND'),
                $doc(4,'USD','2026-09-12',['n'=>'1','d'=>'1'],'KG','11'),
            ])];
        $row=CommercialAnalysisAmounts::row($customer,['Repuestos']);
        self::assertSame('1.00',$row['total_ves']); self::assertSame('0.33',$row['total_usd']);
        self::assertSame(['n'=>'1','d'=>'3'],$row['calculation_snapshot']['values']['total_usd']);
        self::assertSame('0.333333333333 KG; 1 UND',$row['REPUESTOS']);
        self::assertSame('0.50',$row['prom_difference_days']); self::assertSame('12-09-2026',$row['contact_date']);
        self::assertSame(3,$row['quantity_visit']);
    }
}
