<?php
// ######## INICIO RENDIMIENTO CALCULOS EXACTOS PRO9 ########
require dirname(__DIR__).'/vendor/autoload.php';
use App\Support\Exact\Decimal as D;
$report=['runtime'=>PHP_VERSION,'source_sha256'=>hash_file('sha256',dirname(__DIR__).'/app/Support/Exact/Decimal.php'),'cases'=>[]];
foreach([100,1000] as $count) {
    $times=[];
    for($sample=0;$sample<5;$sample++) {
        $third=D::of(1)->dividedBy(3);$start=hrtime(true);
        $sum=D::sum(array_fill(0,$count,$third));
        $times[]=(hrtime(true)-$start)/1000000;
    }
    sort($times);$report['cases']['sum_thirds_'.$count]=['median_ms'=>$times[2],'fraction'=>D::fraction($sum)];
}
$record=new Illuminate\Support\Fluent(['amount'=>'0.333333333333','calculation_snapshot'=>json_encode(D::snapshot(['amount'=>D::of(1)->dividedBy(3)]))]);
$times=[];
for($sample=0;$sample<5;$sample++) {
    $start=hrtime(true);for($i=0;$i<10000;$i++)D::read($record,'amount');
    $times[]=(hrtime(true)-$start)/1000000;
}
sort($times);$report['cases']['read_snapshot_10000']=['median_ms'=>$times[2]];
foreach([10,100,1000] as $count) {
    $start=hrtime(true);$totals=[];
    for($i=0;$i<$count;$i++) {
        $line=App\Support\Exact\LineCalculation::calculate(['quantity'=>'1','unit_price'=>'500'],'16');
        $totals[]=D::read($line,'total');
    }
    $report['cases']['invoice_lines_'.$count]=['elapsed_ms'=>(hrtime(true)-$start)/1000000,'total'=>D::represent(D::sum($totals))];
}
$report['peak_memory_bytes']=memory_get_peak_usage(true);
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
// ######## FIN RENDIMIENTO CALCULOS EXACTOS PRO9 ########
