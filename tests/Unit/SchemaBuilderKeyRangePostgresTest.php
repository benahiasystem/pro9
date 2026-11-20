<?php

namespace Tests\Unit;

use App\Database\SchemaInspector;
use Illuminate\Database\QueryException;

/** @group postgres-integration */
final class SchemaBuilderKeyRangePostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;
    private $db;

    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
        [$this->db]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,false);
        \Tests\Support\PostgresTestEnvironment::assertServer($this->db);
    }

    protected function tearDown(): void
    {
        try {if($this->fixture)$this->fixture->stop();}
        finally {parent::tearDown();}
    }

    /** @dataProvider references */
    public function test_dynamic_references_accept_the_target_bigint_range_and_preserve_unsigned_lower_bounds(string $method,bool $unsigned): void
    {
        $this->db->getSchemaBuilder()->create('schema_key_probe',function($table)use($method){
            $table->increments('id');$table->$method('owner_id');
        });
        self::assertSame('bigint',SchemaInspector::column($this->db,'schema_key_probe','owner_id')->type);
        $this->db->table('schema_key_probe')->insert(['owner_id'=>'9223372036854775807']);
        self::assertSame('9223372036854775807',(string)$this->db->table('schema_key_probe')->value('owner_id'));
        if($unsigned){
            try{$this->db->table('schema_key_probe')->insert(['owner_id'=>'-1']);self::fail('An unsigned reference must reject a negative key.');}
            catch(QueryException $error){self::assertSame('23514',$error->getCode());}
            self::assertSame(1,$this->db->table('schema_key_probe')->count());
        }
    }

    public static function references(): array
    {
        return ['unsigned int'=>['unsignedInteger',true],'signed int'=>['integer',false],'unsigned bigint'=>['unsignedBigInteger',true],
            'signed medium'=>['mediumInteger',false],'unsigned medium'=>['unsignedMediumInteger',true]];
    }

    /** @dataProvider incrementDeclarations */
    public function test_every_incrementing_technical_key_uses_a_bigint_sequence(string $method): void
    {
        $this->db->getSchemaBuilder()->create('schema_key_probe',fn($table)=>$table->$method('id'));
        $column=SchemaInspector::column($this->db,'schema_key_probe','id');
        self::assertSame('bigint',$column->type);self::assertTrue((bool)$column->has_sequence);
        $this->db->selectOne('SELECT setval(pg_get_serial_sequence(?, ?), CAST(? AS bigint), false)',
            ['public.schema_key_probe','id','9007199254740993']);
        self::assertSame('9007199254740993',(string)$this->db->table('schema_key_probe')->insertGetId([]));
    }

    public static function incrementDeclarations(): array
    {
        return [['increments'],['bigIncrements'],['smallIncrements'],['tinyIncrements'],['mediumIncrements']];
    }

    public function test_business_integer_ranges_keep_their_original_bounds(): void
    {
        $this->db->getSchemaBuilder()->create('schema_key_probe',function($table){
            $table->increments('id');$table->unsignedInteger('count');$table->unsignedBigInteger('big_count');$table->integer('signed_count');
        });
        self::assertSame('bigint',SchemaInspector::column($this->db,'schema_key_probe','count')->type);
        self::assertSame('numeric(20,0)',SchemaInspector::column($this->db,'schema_key_probe','big_count')->type);
        self::assertSame('integer',SchemaInspector::column($this->db,'schema_key_probe','signed_count')->type);
        $valid=['count'=>'4294967295','big_count'=>'18446744073709551615','signed_count'=>'2147483647'];
        $this->db->table('schema_key_probe')->insert($valid);
        foreach(['count','big_count','signed_count'] as $field)self::assertSame($valid[$field],(string)$this->db->table('schema_key_probe')->value($field));
        foreach([['count','-1','23514'],['count','4294967296','23514'],['big_count','-1','23514'],
            ['big_count','18446744073709551616','23514'],['signed_count','2147483648','22003']] as [$field,$value,$state]){
            try{$this->db->table('schema_key_probe')->insert(array_replace($valid,[$field=>$value]));self::fail('Out-of-range business integer was accepted.');}
            catch(QueryException $error){self::assertSame($state,$error->getCode());}
            self::assertSame(1,$this->db->table('schema_key_probe')->count());
        }
    }

    /** @dataProvider shortBusinessIntegers */
    public function test_short_business_integers_preserve_their_original_mysql_domains(string $method,string $minimum,string $maximum): void
    {
        $this->db->getSchemaBuilder()->create('schema_key_probe',fn($table)=>$table->$method('business_count'));
        foreach([$minimum,$maximum] as $value)$this->db->table('schema_key_probe')->insert(['business_count'=>$value]);
        self::assertSame(2,$this->db->table('schema_key_probe')->count());
        foreach([(string)((int)$minimum-1),(string)((int)$maximum+1)] as $invalid){
            try{$this->db->table('schema_key_probe')->insert(['business_count'=>$invalid]);self::fail('Business integer domain was widened.');}
            catch(QueryException $error){self::assertContains($error->getCode(),['23514','22003']);}
            self::assertSame(2,$this->db->table('schema_key_probe')->count());
        }
    }

    public static function shortBusinessIntegers(): array
    {
        return [
            'tiny'=>['tinyInteger','-128','127'],'unsigned tiny'=>['unsignedTinyInteger','0','255'],
            'small'=>['smallInteger','-32768','32767'],'unsigned small'=>['unsignedSmallInteger','0','65535'],
            'medium'=>['mediumInteger','-8388608','8388607'],'unsigned medium'=>['unsignedMediumInteger','0','16777215'],
        ];
    }
}
