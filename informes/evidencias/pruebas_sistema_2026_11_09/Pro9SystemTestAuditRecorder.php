<?php
/** Registra resultados; no modifica el comportamiento de las pruebas ni de la aplicación. */
final class Pro9SystemTestAuditRecorder20261109 implements \PHPUnit\Framework\TestListener
{
    use \PHPUnit\Framework\TestListenerDefaultImplementation;
    private array $pending = [];
    public function startTest(\PHPUnit\Framework\Test $test): void { $this->pending[spl_object_id($test)] = []; }
    private function incident(\PHPUnit\Framework\Test $test, \Throwable $error, string $kind): void
    {
        $this->pending[spl_object_id($test)][] = ['tipo'=>$kind,'clase'=>get_class($error),'mensaje'=>$error->getMessage(),'traza'=>$error->getTraceAsString()];
    }
    public function addError(\PHPUnit\Framework\Test $test, \Throwable $error, float $time): void { $this->incident($test,$error,'error'); }
    public function addWarning(\PHPUnit\Framework\Test $test, \PHPUnit\Framework\Warning $error, float $time): void { $this->incident($test,$error,'warning'); }
    public function addFailure(\PHPUnit\Framework\Test $test, \PHPUnit\Framework\AssertionFailedError $error, float $time): void { $this->incident($test,$error,'failure'); }
    public function addIncompleteTest(\PHPUnit\Framework\Test $test, \Throwable $error, float $time): void { $this->incident($test,$error,'incomplete'); }
    public function addRiskyTest(\PHPUnit\Framework\Test $test, \Throwable $error, float $time): void { $this->incident($test,$error,'risky'); }
    public function addSkippedTest(\PHPUnit\Framework\Test $test, \Throwable $error, float $time): void { $this->incident($test,$error,'skipped'); }
    public function endTest(\PHPUnit\Framework\Test $test, float $time): void
    {
        $issues=$this->pending[spl_object_id($test)] ?? [];
        $class=get_class($test);$name=method_exists($test,'getName') ? $test->getName() : (string)$test;
        $source=(new \ReflectionClass($test))->getFileName();
        $row=['id'=>$class.'::'.$name,'clase'=>$class,'nombre'=>$name,'archivo'=>$source,'segundos'=>$time,
            'aserciones'=>method_exists($test,'getNumAssertions')?$test->getNumAssertions():0,
            'resultado'=>$issues ? $issues[0]['tipo'] : 'passed','incidencias'=>$issues,
            'terminado_utc'=>(new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->format(DATE_ATOM)];
        $file=getenv('PRO9_AUDIT_CHECKPOINT');
        if (!$file) throw new \RuntimeException('Falta el destino persistente del registro de pruebas.');
        $handle=fopen($file,'ab');
        if (!$handle) throw new \RuntimeException('No se pudo abrir el registro persistente.');
        flock($handle,LOCK_EX);
        fwrite($handle,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR)."\n");
        fflush($handle);
        if (function_exists('fsync')) fsync($handle);
        flock($handle,LOCK_UN);fclose($handle);
        unset($this->pending[spl_object_id($test)]);
    }
}
