<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

// ########## INICIO CAMBIO POSTGRESQL 17
/**
 * @group postgres-integration
 * @group postgres-performance
 */
final class PostgresPerformanceTest extends TestCase
{
    public function test_http_and_sales_report_p95_matches_the_postgresql_tenancy2_reference(): void
    {
        $workloads = ['http_documents', 'sales_report'];
        $single = $this->measureWorkloads($workloads, 'http-reportes');

        $root = dirname(__DIR__, 2);
        $reference = $root.'/informes/evidencias/postgresql_17_implementacion/referencia_rendimiento_postgresql.json';
        [$baseline, $hash] = $this->readReference($reference);
        $reports = $this->runBenchmark('benchmark_postgresql_tenants.php');
        self::assertSame(['a', 'b'], array_keys($baseline['tenants']));
        self::assertSame(['a', 'b'], array_keys($reports));
        foreach ([$baseline['tenants'], $reports] as $pair) {
            self::assertNotSame($pair['a']['tenant']['database_sha256'], $pair['b']['tenant']['database_sha256']);
            self::assertNotSame($pair['a']['tenant']['hostname'], $pair['b']['tenant']['hostname']);
        }

        $comparisons = [];
        foreach ($reports as $member => $postgres) {
            $beforeReport = $baseline['tenants'][$member];
            self::assertSame($beforeReport['tenant']['hostname'], $postgres['tenant']['hostname']);
            foreach ($this->compareWorkloads($beforeReport, $postgres, $workloads) as $comparison) {
                $comparisons[] = ['tenant' => $member] + $comparison;
            }
        }
        $this->writeCaseResults('dos-tenants', ['postgres_tenants' => $reports], $comparisons, $reference, $hash);
        $this->assertPerformanceBudgets([...$single, ...$comparisons]);
    }

    /** @group postgres-performance-pos */
    public function test_pos_catalog_p95_matches_the_postgresql_tenancy2_reference(): void
    {
        $this->assertPerformanceBudgets($this->measureWorkloads(['pos_catalog'], 'pos'));
    }

    private function measureWorkloads(array $workloads, string $label): array
    {
        if (getenv('SAB_POSTGRES_TESTS') !== '1') $this->markTestSkipped('Requiere el ejecutor PostgreSQL temporal.');
        $root = dirname(__DIR__, 2);
        $reference = $root.'/informes/evidencias/postgresql_17_implementacion/referencia_rendimiento_postgresql.json';
        [$baseline, $hash] = $this->readReference($reference);
        $postgres = $this->runBenchmark('benchmark_postgresql_http.php', $workloads);
        $referenceSection = $label === 'pos' ? 'pos' : 'single';
        $comparisons = $this->compareWorkloads($baseline[$referenceSection], $postgres, $workloads);
        $this->writeCaseResults($label, ['postgres' => $postgres], $comparisons, $reference, $hash);
        return $comparisons;
    }

    private function readReference(string $path): array
    {
        self::assertFileExists($path, 'Falta la referencia PostgreSQL de Tenancy 2.');
        $bytes = file_get_contents($path);
        $reference = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($reference['temporary_only']);
        self::assertSame('9d7d61dea246d8bb2905c46a636cbd63c7336d8b', $reference['baseline_revision']);
        self::assertSame(PHP_VERSION, $reference['php_version']);
        self::assertSame(2, $reference['cpu_limit']);
        self::assertSame(805306368, $reference['server_memory_bytes']);
        self::assertSame(1610612736, $reference['php_memory_bytes']);
        return [$reference, hash('sha256', $bytes)];
    }

    private function runBenchmark(string $filename, array $arguments = []): array
    {
        $root = dirname(__DIR__, 2);
        $process = new Process([PHP_BINARY, '-d', 'memory_limit=768M', $root.'/tests/Support/'.$filename, ...$arguments], $root);
        $process->setTimeout(1800);
        $process->run();
        self::assertSame(0, $process->getExitCode(), substr($process->getErrorOutput(), -5000));
        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function compareWorkloads(array $beforeReport, array $postgres, array $workloads): array
    {
        self::assertSame('pgsql', $beforeReport['engine']);
        self::assertSame('pgsql', $postgres['engine']);
        foreach ([$beforeReport, $postgres] as $report) {
            self::assertSame(30, $report['samples']);
            self::assertSame(3, $report['warmups']);
            self::assertSame([1000, 10000], array_keys($report['cases']));
        }

        $comparisons = [];
        foreach ([1000, 10000] as $count) {
            self::assertSame($workloads, array_keys($postgres['cases'][$count]));
            foreach ($workloads as $workload) {
                $expected = $beforeReport['cases'][$count][$workload];
                $measured = $postgres['cases'][$count][$workload];
                self::assertCount(30, $expected['samples_ms']);
                self::assertCount(30, $measured['samples_ms']);
                $before = $expected['p95_ms'];
                $after = $measured['p95_ms'];
                $budget = $before + max($before * 0.2, 100);
                $comparisons[] = [
                    'documents' => $count, 'workload' => $workload, 'reference_p95_ms' => $before,
                    'postgres_p95_ms' => $after, 'budget_ms' => $budget, 'passed' => $after <= $budget,
                ];
            }
        }
        return $comparisons;
    }

    private function writeCaseResults(string $label, array $report, array $comparisons, string $reference, string $hash): void
    {
        $directory = dirname(__DIR__, 2).'/informes/evidencias/postgresql_17_implementacion/resultados';
        self::assertSame($hash, hash_file('sha256', $reference), 'La referencia no debe cambiar durante la medición.');
        file_put_contents($directory.'/rendimiento-'.getenv('SAB_POSTGRES_RUN_ID').'-'.$label.'.json', json_encode($report + [
            'reference_sha256' => $hash, 'comparisons' => $comparisons,
            'temporary_only' => true, 'finished_at' => date(DATE_ATOM),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
    }

    private function assertPerformanceBudgets(array $comparisons): void
    {
        $overBudget = array_values(array_filter($comparisons, static fn(array $comparison): bool => !$comparison['passed']));
        self::assertSame([], $overBudget, json_encode($overBudget, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
