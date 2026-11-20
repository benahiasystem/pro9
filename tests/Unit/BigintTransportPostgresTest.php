<?php

namespace Tests\Unit;

/** @group postgres-integration */
final class BigintTransportPostgresTest extends \PHPUnit\Framework\TestCase
{
    public function test_temporary_postgres_insert_reload_cursor_relations_and_resource_preserve_adjacent_bigint_keys(): void
    {
        $fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        try {
            $fixture->start();
            [$db,$app]=(new \ReflectionMethod($fixture,'bootHkaFixture'))->invoke($fixture,false);
            config(['view.paths'=>[resource_path('views')],'view.compiled'=>sys_get_temp_dir().'/'.$db->getDatabaseName().'/bigint-views']);
            $app->register(\Illuminate\View\ViewServiceProvider::class);
            \Tests\Support\PostgresTestEnvironment::assertServer($db);
            $db->statement('CREATE TABLE bigint_transport_probe (id bigserial PRIMARY KEY,parent_id bigint REFERENCES bigint_transport_probe(id),enabled boolean NOT NULL,amount numeric(30,12) NOT NULL)');
            $db->selectOne("SELECT setval(pg_get_serial_sequence('bigint_transport_probe','id'),CAST(? AS bigint),false)",['9007199254740993']);
            $first=new BigintTransportProbe(['enabled'=>true,'amount'=>'0.333333333333']);$first->save();
            $second=new BigintTransportProbe(['parent_id'=>$first->id,'enabled'=>false,'amount'=>'0.000000000001']);$second->save();
            self::assertSame('9007199254740993',$first->id);
            self::assertSame('9007199254740994',$second->id);
            $second=$second->fresh('parent');
            self::assertSame($first->id,$second->parent_id);
            self::assertSame($first->id,$second->parent->id);
            self::assertSame('0.000000000001',$second->amount);
            self::assertFalse($second->enabled);
            $array=json_decode($second->toJson(),true,512,JSON_THROW_ON_ERROR);
            self::assertSame('9007199254740994',$array['id']);
            self::assertSame('9007199254740993',$array['parent']['id']);
            $resource=new class($second) extends \Illuminate\Http\Resources\Json\JsonResource {
                public function toArray($request): array {return ['id'=>$this->id,'parent_id'=>$this->parent_id,'enabled'=>$this->enabled,'amount'=>$this->amount];}
            };
            $response=$resource->response(\Illuminate\Http\Request::create('/'));
            $public=json_decode($response->getContent(),true,512,JSON_THROW_ON_ERROR)['data'];
            self::assertSame('9007199254740994',$public['id']);
            self::assertSame('9007199254740993',$public['parent_id']);
            self::assertFalse($public['enabled']);
            self::assertSame('0.000000000001',$public['amount']);
            $rows=$db->table('bigint_transport_probe')->orderBy('id')->get();
            self::assertSame(['9007199254740993','9007199254740994'],$rows->pluck('id')->all());
            self::assertSame([1,0],$rows->pluck('enabled')->all());
            $cursor=iterator_to_array($db->cursor('SELECT id FROM bigint_transport_probe ORDER BY id'));
            self::assertSame(['9007199254740993','9007199254740994'],array_column($cursor,'id'));
            self::assertSame(0,$db->transactionLevel());
        } finally {$fixture->stop();}
    }
}

final class BigintTransportProbe extends \App\Models\Tenant\ModelTenant
{
    protected $table='bigint_transport_probe';
    protected $guarded=[];
    protected $casts=['enabled'=>'boolean'];
    public $timestamps=false;
    public function parent() {return $this->belongsTo(self::class,'parent_id');}
}
