<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal as D,DashboardSales};
use Modules\Dashboard\Widgets\{WidgetDataset,DefaultWidgetSources,WidgetContext};

final class WidgetAmountsTest extends \PHPUnit\Framework\TestCase
{
    public function test_variation_uses_original_values_that_both_display_as_zero(): void
    {
        $result=WidgetDataset::make()->totals('0.004','0.003')->toArray()['totals'];
        self::assertSame(33.3,$result['delta']);
        self::assertTrue(D::readRequired($result,'current')->isEqualTo('0.004'));
        self::assertTrue(D::readRequired($result,'delta')->isEqualTo('100/3'));
    }

    public function test_series_preserves_calendar_gaps_large_values_and_original_fractions(): void
    {
        $values=[['n'=>'1','d'=>'3'],null,'9007199254740993.005'];$before=serialize($values);
        $series=WidgetDataset::make()->serie(['A','B','C'],[['name'=>'Ventas','data'=>$values]])->toArray()['series'][0];
        self::assertNull($series['data'][1]);
        self::assertSame(['0.33',null,'9007199254740993.01'],$series['data_exact']);
        self::assertSame([['n'=>'1','d'=>'3'],null,['n'=>'1801439850948198601','d'=>'200']],$series['data_fractions']);
        self::assertSame($before,serialize($values));
    }

    public function test_unknown_totals_remain_unknown_and_zero_comparison_keeps_its_contract(): void
    {
        $unknown=WidgetDataset::make()->totals(null,null)->toArray()['totals'];
        self::assertNull($unknown['current']);self::assertNull($unknown['previous']);self::assertNull($unknown['delta']);
        self::assertSame([],$unknown['calculation_snapshot']['values']);
        self::assertSame(100.0,WidgetDataset::make()->totals('0.004','0')->toArray()['totals']['delta']);
        self::assertSame(0.0,WidgetDataset::make()->totals('0','0')->toArray()['totals']['delta']);
    }

    public function test_count_breakdown_keeps_fractional_stock_instead_of_fixing_it_to_cents(): void
    {
        $result=WidgetDataset::make(WidgetDataset::UNIT_COUNT)->breakdown(['Stock'],[['n'=>'1','d'=>'3']])->toArray()['breakdown'];
        self::assertSame(['0.333333333333'],$result['values_exact']);
        self::assertSame([['n'=>'1','d'=>'3']],$result['values_fractions']);
    }

    /** @dataProvider invalidOperands */
    public function test_binary_or_missing_operands_are_rejected(string $method, $value): void
    {
        $dataset=WidgetDataset::make();$this->expectException(\UnexpectedValueException::class);
        if ($method==='serie') $dataset->serie(['A'],[['name'=>'','data'=>[$value]]]);
        elseif ($method==='breakdown') $dataset->breakdown(['A'],[$value]);
        else $dataset->totals($value);
    }

    public static function invalidOperands(): array
    {
        $rows=[];
        foreach (['serie','breakdown','totals'] as $method) foreach (['binary'=>0.1+0.2,'boolean'=>true,'empty'=>''] as $key=>$value) {
            $rows[$method.' '.$key]=[$method,$value];
        }
        return $rows;
    }

    public function test_incomplete_calendar_vectors_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WidgetDataset::make()->serie(['A','B'],[['name'=>'Ventas','data'=>['1']]]);
    }

    /** @dataProvider defaultSources */
    public function test_each_default_source_consumes_the_original_dto(string $key, ?string $expected): void
    {
        $payload=self::payload();$before=serialize($payload);
        $context=new class($payload) extends WidgetContext {
            public function __construct(private array $payload) {parent::__construct();}
            public function data(){return $this->payload['data'];}
            public function globalData(){return $this->payload['global'];}
            public function cashFlow(){return $this->payload['cash'];}
            public function salesWeek(){return $this->payload['period'];}
            public function paymentMethods(){return $this->payload['payment'];}
            public function debtors(){return $this->payload['debtors'];}
            public function monthGoal(){return $this->payload['goal'];}
            public function lowStock(){return $this->payload['stock'];}
            public function utilities(array $options=[]){return $this->payload['utility'];}
            public function dataAditional(array $options=[]){return $this->payload['additional'];}
            public function monthlyComparison(){return $this->payload['comparison'];}
            public function salesGrowth(){return $this->payload['growth'];}
        };
        $sources=[];foreach (DefaultWidgetSources::all() as $source) $sources[$source->key()]=$source;
        self::assertCount(20,$sources);$actual=$sources[$key]->resolve($context,[])->toArray();
        if ($expected===null) self::assertNull($actual['totals']['current']);
        else self::assertTrue(D::readRequired($actual['totals'],'current')->isEqualTo($expected),$key.' used a rounded projection as its original total');
        if ($key==='ventas.ventas_totales') self::assertTrue(D::readRequired($actual['totals'],'delta')->isEqualTo('200/3'));
        if ($key==='ventas.periodo') self::assertNull($actual['series'][1]['data_fractions'][1]);
        if ($key==='clientes.top') self::assertSame(['n'=>'1','d'=>'200'],$actual['breakdown']['values_fractions'][0]);
        if ($key==='inventario.stock_bajo') self::assertSame(['n'=>'1','d'=>'3'],$actual['breakdown']['values_fractions'][0]);
        self::assertSame($before,serialize($payload));
    }

    public static function defaultSources(): array
    {
        return [
            'ventas'=>['ventas.ventas_totales','0.005'],'ticket'=>['ventas.ticket_promedio','0.005'],
            'notas'=>['ventas.notas_venta','0.008'],'comprobantes'=>['ventas.comprobantes','0.008'],
            'totales'=>['ventas.totales','0.008'],'periodo'=>['ventas.periodo','0.01'],
            'crecimiento'=>['ventas.crecimiento','0.01'],'comparativo'=>['ventas.comparativo','0.01'],
            'productos'=>['ventas.top_productos','0.009'],'por cobrar'=>['finanzas.por_cobrar','0.005'],
            'utilidad neta'=>['finanzas.utilidad_neta','0.005'],'caja'=>['finanzas.flujo_caja','0.005'],
            'medios'=>['finanzas.medios_pago','0.009'],'deudores'=>['finanzas.deudores','0.009'],
            'meta'=>['finanzas.meta_mes','100/3'],'utilidades'=>['finanzas.utilidades','0.005'],
            'balance'=>['finanzas.balance','0.008'],'compras'=>['compras.totales','0.012'],
            'clientes'=>['clientes.top',null],'stock'=>['inventario.stock_bajo','1'],
        ];
    }

    public function test_a_default_source_rejects_a_numeric_total_without_its_original_snapshot(): void
    {
        $context=new class extends WidgetContext {
            public function paymentMethods(){return ['labels'=>['A'],'values'=>[0.01],
                'values_fractions'=>[['n'=>'1','d'=>'200']],'total'=>0.01];}
        };
        $this->expectException(\UnexpectedValueException::class);
        foreach (DefaultWidgetSources::all() as $source) if ($source->key()==='finanzas.medios_pago') $source->resolve($context,[]);
    }

    public function test_a_default_source_rejects_a_binary_value_in_its_fraction_vector(): void
    {
        $context=new class extends WidgetContext {
            public function paymentMethods(){return DashboardSales::numericProjection(['total'=>'0.005'])
                + ['labels'=>['A'],'values'=>[0.01],'values_fractions'=>[0.005]];}
        };
        $this->expectException(\UnexpectedValueException::class);
        foreach (DefaultWidgetSources::all() as $source) if ($source->key()==='finanzas.medios_pago') $source->resolve($context,[]);
    }

    private static function row(array $values, array $metadata=[]): array
    {
        return $metadata+D::values($values)+['calculation_snapshot'=>D::snapshot($values)];
    }

    private static function payload(): array
    {
        $keys=['monthly_sales','average_ticket','accounts_receivable','net_utility'];
        $current=array_fill_keys($keys,'0.005');$previous=array_fill_keys($keys,'0.003');
        $global=DashboardSales::numericProjection($current)+['trend'=>['labels'=>['Anterior','Actual'],
            'calculation_snapshots'=>[D::snapshot($previous),D::snapshot($current)]]];
        foreach ($keys as $key) $global['trend'][$key]=[0.0,0.01];
        $totals=self::row(['total'=>'0.008','total_payment'=>'0.004','total_to_pay'=>'0.004']);
        $collection=['totals'=>$totals,'graph'=>['labels'=>['Pagado','Pendiente'],'datasets'=>[[
            'label'=>'Cobros','data'=>[0.0,0.0],'calculation_snapshot'=>D::snapshot(['paid'=>'0.004','pending'=>'0.004'])]]]];
        $general=['totals'=>self::row(['total'=>'0.008']),'graph'=>['labels'=>['A','B'],'datasets'=>[[
            'label'=>'Total','data'=>[0.0,0.0],'calculation_snapshot'=>D::snapshot(['A'=>'0.004','B'=>'0.004'])]]]];
        $balance=['totals'=>self::row(['all_totals'=>'0.008','all_totals_payment'=>'0.004']),
            'graph'=>['labels'=>['Total','Pagos'],'datasets'=>[['data'=>[0.01,0.0],
                'calculation_snapshot'=>D::snapshot(['total'=>'0.008','paid'=>'0.004'])]]]];
        $period=DashboardSales::numericProjection(['current_total'=>'0.01','previous_total'=>'0.003'])
            + ['labels'=>['A','B'],'current'=>[0.01,0.01],'previous'=>[0.0,null],
                'current_fractions'=>[D::fraction('0.005'),D::fraction('0.005')],'previous_fractions'=>[D::fraction('0.003'),null]];
        $growth=DashboardSales::numericProjection(['current_total'=>'0.01','previous_total'=>'0.006'])
            + ['categories'=>['A','B'],'current'=>[0.01,0.01],'previous'=>[0.0,0.0],
                'current_label'=>'2026','previous_label'=>'2025',
                'current_fractions'=>[D::fraction('0.005'),D::fraction('0.005')],'previous_fractions'=>[D::fraction('0.003'),D::fraction('0.003')]];
        $comparison=DashboardSales::numericProjection(['sales_total'=>'0.01','purchases_total'=>'0.004'])
            + ['categories'=>['A','B'],'sales'=>[0.01,0.01],'purchases'=>[0.0,0.0],
                'sales_fractions'=>[D::fraction('0.005'),D::fraction('0.005')],'purchases_fractions'=>[D::fraction('0.004'),D::fraction('0')]];
        $cash=DashboardSales::numericProjection(['income_total'=>'0.009','egress_total'=>'0.004','net_total'=>'0.005'])
            + ['labels'=>['A','B'],'income'=>[0.01,0.0],'egress'=>[0.0,0.0],
                'income_fractions'=>[D::fraction('0.005'),D::fraction('0.004')],'egress_fractions'=>[D::fraction('0.002'),D::fraction('0.002')]];
        $payment=DashboardSales::numericProjection(['total'=>'0.009'])+['labels'=>['A','B'],'values'=>[0.01,0.0],
            'values_fractions'=>[D::fraction('0.005'),D::fraction('0.004')]];
        $debtors=self::row(['total'=>'0.009'],['count'=>2,'items'=>[
            self::row(['total_to_pay'=>'0.005'],['customer'=>'A']),self::row(['total_to_pay'=>'0.004'],['customer'=>'B'])]]);
        $goal=DashboardSales::numericProjection(['percent'=>'100/3'])+['enabled'=>true];
        $utility=['utilities'=>['totals'=>self::row(['total_income'=>'0.009','total_egress'=>'0.004','utility'=>'0.005'])]];
        $purchase=['totals'=>self::row(['total'=>'0.012']), 'graph'=>['labels'=>['A','B'],'datasets'=>[]]];
        foreach ([['Total compras',['0.004','0.005']],['Total',['0.005','0.007']]] as [$label,$values]) {
            $purchase['graph']['datasets'][]=['label'=>$label,'data'=>[0.0,0.01],'data_fractions'=>array_map([D::class,'fraction'],$values)];
        }
        $products=$customers=[];
        foreach (['0.005','0.004'] as $index=>$value) {
            $products[]=self::row(['total'=>$value],['description'=>'Producto '.$index]);
            $customers[]=self::row(['total'=>$value],['name'=>'Cliente '.$index]);
        }
        return ['global'=>$global,'data'=>['document'=>$collection,'sale_note'=>$collection,'general'=>$general,'balance'=>$balance],
            'period'=>$period,'growth'=>$growth,'comparison'=>$comparison,'cash'=>$cash,'payment'=>$payment,
            'debtors'=>$debtors,'goal'=>$goal,'utility'=>$utility,'additional'=>['purchase'=>$purchase,'items_by_sales'=>$products,'top_customers'=>$customers],
            'stock'=>['total'=>1,'items'=>[self::row(['stock'=>'1/3'],['product'=>'Stock'])]]];
    }
}
