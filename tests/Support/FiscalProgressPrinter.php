<?php

namespace Tests\Support;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestSuite;
use PHPUnit\TextUI\DefaultResultPrinter;
use PHPUnit\Util\Test as TestName;
use Throwable;

/** Informa fallos y duración sin esperar a que termine toda la batería. */
final class FiscalProgressPrinter extends DefaultResultPrinter
{
    private ?int $fiscalTotal = null;
    private int $fiscalCompleted = 0;

    public function startTestSuite(TestSuite $suite): void
    {
        parent::startTestSuite($suite);
        if ($this->fiscalTotal === null) $this->fiscalTotal = count($suite);
    }

    public function addError(Test $test, Throwable $error, float $time): void
    {
        parent::addError($test, $error, $time);
        fwrite(STDERR, sprintf("\nERROR %s (%.3f s): %s\n", TestName::describeAsString($test), $time, $error->getMessage()));
    }

    public function startTest(Test $test): void
    {
        parent::startTest($test);
        fwrite(STDERR, sprintf("\nINICIO %d/%d %s\n", $this->fiscalCompleted + 1, $this->fiscalTotal ?? 0, TestName::describeAsString($test)));
    }

    public function addFailure(Test $test, AssertionFailedError $error, float $time): void
    {
        parent::addFailure($test, $error, $time);
        fwrite(STDERR, sprintf("\nFALLO %s (%.3f s): %s\n", TestName::describeAsString($test), $time, $error->getMessage()));
    }

    public function endTest(Test $test, float $time): void
    {
        parent::endTest($test, $time);
        $this->fiscalCompleted++;
        $percent = $this->fiscalTotal ? 100 * $this->fiscalCompleted / $this->fiscalTotal : 0;
        fwrite(STDERR, sprintf("\n%d/%d (%.1f%%) %.3f s %s\n", $this->fiscalCompleted, $this->fiscalTotal ?? 0, $percent, $time, TestName::describeAsString($test)));
    }
}
