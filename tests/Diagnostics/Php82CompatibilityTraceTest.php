<?php

namespace Tests\Diagnostics;

/** Explicit opt-in diagnostic: no parameter values or SQL bindings are recorded. */
final class Php82CompatibilityTraceTest extends \PHPUnit\Framework\TestCase
{
    public function test_record_deprecation_call_sites_in_temporary_fiscal_fixtures(): void
    {
        foreach (['test_free_form_invoices_credit_and_debit_match_cash_products_and_local_pdfs',
            'test_classic_subscription_generation_failure_rolls_back_the_plan'] as $method) {
            $fixture = new class extends \Tests\Unit\FiscalEmissionSchemaTest {
                public function start(): void { parent::setUp(); }
                public function stop(): void { parent::tearDown(); }
            };
            $fixture->start();
            set_error_handler(static function ($level, $message, $file, $line) {
                if ($level !== E_DEPRECATED) return false;
                $frames = array_map(static fn ($frame) => [
                    'file' => $frame['file'] ?? null, 'line' => $frame['line'] ?? null,
                    'call' => ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? ''),
                ], debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 18));
                fwrite(STDERR, 'PHP82_TRACE '.json_encode(compact('message','file','line','frames'), JSON_THROW_ON_ERROR).PHP_EOL);
                return false;
            });
            try { $fixture->$method(); }
            finally { restore_error_handler(); $fixture->stop(); }
        }
    }
}
