<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class ExactModelCastTest extends TestCase
{
    private $previousResolver;

    protected function setUp(): void
    {
        $this->previousResolver=Model::getConnectionResolver();
        Model::setConnectionResolver(new class implements \Illuminate\Database\ConnectionResolverInterface {
            public function connection($name=null) {throw new \LogicException('No se permite acceso a ninguna base en esta prueba de casts.');}
            public function getDefaultConnection() {return 'forbidden';}
            public function setDefaultConnection($name) {throw new \LogicException('No se permite cambiar la conexión.');}
        });
    }

    protected function tearDown(): void
    {
        if ($this->previousResolver) Model::setConnectionResolver($this->previousResolver);
        else Model::unsetConnectionResolver();
        parent::tearDown();
    }

    /** @dataProvider reviewedFields */
    public function test_effective_model_cast_preserves_decimal_tokens_without_using_the_legacy_float(array $field): void
    {
        $reflection=new \ReflectionClass($field['class']);
        // Do not boot models/events or resolve a connection merely to inspect a cast.
        $model=$reflection->newInstanceWithoutConstructor();
        self::assertSame($field['source_sha256'],hash_file('sha256',$reflection->getFileName()));
        self::assertSame($field['table'],$model->getTable());
        self::assertSame($field['declared'],$reflection->getDefaultProperties()['casts'][$field['column']]);
        self::assertContains($field['column'],\App\Support\Exact\Persistence::columns($model));
        self::assertSame('string',$model->getCasts()[$field['column']]);
        $cast=new \ReflectionMethod($model,'castAttribute');
        foreach (['0.1','0.2','0.3','0.000000000001','123456789012345678.123456789012','-123456789012345678.123456789012'] as $token) {
            self::assertSame($token,$cast->invoke($model,$field['column'],$token));
        }
        self::assertNull($cast->invoke($model,$field['column'],null));
    }

    public static function reviewedFields(): array
    {
        $report=json_decode(file_get_contents(dirname(__DIR__,2).'/informes/evidencias/postgresql_17_implementacion/casts_exactos_efectivos.json'),true,512,JSON_THROW_ON_ERROR);
        $cases=[];
        foreach ($report['fields'] as $field) $cases[$field['class'].'::'.$field['column']]=[$field];
        return $cases;
    }
}
