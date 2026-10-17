<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactLateCalculationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); DB::purge('tenant');
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        Schema::connection('tenant')->create('supplies', function ($t) {
            $t->increments('id'); $t->text('cost'); $t->json('calculation_snapshot')->nullable(); $t->timestamps();
        });
    }

    public function test_saving_hook_can_keep_a_periodic_result_and_its_snapshot_in_the_same_write(): void
    {
        $row = new LateExactSupply(['cost'=>'1']); $row->save();
        self::assertSame('1/3', (string)$row->fresh()->exactValue('cost'));
        $row->mode = 'update'; $row->save();
        self::assertSame('2/3', (string)$row->fresh()->exactValue('cost'));
    }

    public function test_scalar_precision_overflow_in_a_late_hook_is_rejected_before_insert(): void
    {
        $row = new LateExactSupply(['cost'=>'1']); $row->mode = 'invalid_scalar';
        try { $row->save(); self::fail('Se guardó una entrada fuera de precisión.'); }
        catch (ValidationException $exception) { self::assertSame(0, DB::connection('tenant')->table('supplies')->count()); }
    }

    public function test_rational_range_overflow_in_a_late_hook_is_rejected_before_insert(): void
    {
        $row = new LateExactSupply(['cost'=>'1']); $row->mode = 'invalid_fraction';
        try { $row->save(); self::fail('Se guardó un resultado fuera de rango.'); }
        catch (ValidationException $exception) { self::assertSame(0, DB::connection('tenant')->table('supplies')->count()); }
    }
}

class LateExactSupply extends \Modules\Restaurant\Models\Supply
{
    protected $table = 'supplies';
    public string $mode = 'normal';
    protected static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            $model->cost = match ($model->mode) {
                'invalid_scalar'=>'1.0000000000001',
                'invalid_fraction'=>Decimal::of('1000000000000000000'),
                'update'=>Decimal::of(2)->dividedBy(3),
                default=>Decimal::of(1)->dividedBy(3),
            };
        });
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
