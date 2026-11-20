<?php

namespace Tests\Unit;

/** Checks stored rate transport only; input validation and write paths have separate tests. */
final class ExactRateCastTest extends \PHPUnit\Framework\TestCase
{
    private $previousResolver;

    protected function setUp(): void
    {
        $this->previousResolver=\Illuminate\Database\Eloquent\Model::getConnectionResolver();
        \Illuminate\Database\Eloquent\Model::setConnectionResolver(new class implements \Illuminate\Database\ConnectionResolverInterface {
            public function connection($name=null) { throw new \LogicException('Esta prueba de casts prohíbe todas las conexiones.'); }
            public function getDefaultConnection() { return 'forbidden'; }
            public function setDefaultConnection($name) { throw new \LogicException('No se permite cambiar la conexión.'); }
        });
    }

    protected function tearDown(): void
    {
        if ($this->previousResolver) \Illuminate\Database\Eloquent\Model::setConnectionResolver($this->previousResolver);
        else \Illuminate\Database\Eloquent\Model::unsetConnectionResolver();
        parent::tearDown();
    }

    /** @dataProvider rateFields */
    public function test_stored_rate_cast_preserves_eight_decimal_digits_without_a_connection(array $field): void
    {
        $class=new \ReflectionClass($field['class']);
        self::assertSame($field['source_sha256'],hash_file('sha256',$class->getFileName()));
        $model=$class->newInstanceWithoutConstructor();
        self::assertSame($field['table'],$model->getTable());
        self::assertSame('decimal:8',$class->getDefaultProperties()['casts'][$field['column']]);
        self::assertSame('decimal:8',$model->getCasts()[$field['column']]);
        $cast=new \ReflectionMethod($model,'castAttribute');
        foreach (['0.00000001','1.00000000','873.86712345','9999999999.99999999'] as $token) {
            self::assertSame($token,$cast->invoke($model,$field['column'],$token));
        }
        self::assertNull($cast->invoke($model,$field['column'],null));
    }

    public static function rateFields(): array
    {
        $fields=json_decode(file_get_contents(__DIR__.'/../Fixtures/exact-rate-casts.json'),true,512,JSON_THROW_ON_ERROR);
        $cases=[];
        foreach ($fields as $field) $cases[$field['class'].'::'.$field['column']]=[$field];
        return $cases;
    }
}
