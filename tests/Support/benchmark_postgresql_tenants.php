<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Tests\Support\BenchmarkTenantCohort;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
if (getenv('SAB_POSTGRES_TESTS') !== '1') {
    throw new RuntimeException('Requiere la instancia temporal de rendimiento.');
}

$directory = realpath(sys_get_temp_dir()).'/sab_http_cohort_'.bin2hex(random_bytes(6));
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('No se pudo crear la cohorte temporal.');
}
$workers = [];
$central = null;
try {
    $central = BenchmarkTenantCohort::createCentral();
    $private = $directory.'/central.json';
    file_put_contents($private, json_encode($central, JSON_THROW_ON_ERROR));
    chmod($private, 0600);
    foreach (['a', 'b'] as $member) {
        $workers[$member] = new Process(
            [PHP_BINARY, '-d', 'memory_limit=384M', $root.'/tests/Support/benchmark_postgresql_http.php', 'http_documents', 'sales_report'],
            $root,
            ['SAB_HTTP_BENCHMARK_COHORT' => $directory, 'SAB_HTTP_BENCHMARK_MEMBER' => $member]
        );
        $workers[$member]->setTimeout(900);
        $workers[$member]->start();
    }

    do {
        $running = false;
        foreach ($workers as $member => $worker) {
            if ($worker->isRunning()) {
                $worker->checkTimeout();
                $running = true;
            } elseif (!$worker->isSuccessful()) {
                throw new RuntimeException('Falló la medición del tenant '.$member.'. '.substr($worker->getErrorOutput(), -3000));
            }
        }
        if ($running) usleep(50000);
    } while ($running);

    $reports = [];
    foreach ($workers as $member => $worker) {
        $report = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        if (($report['tenant']['member'] ?? null) !== $member
            || !preg_match('/^[a-f0-9]{64}$/D', $report['tenant']['database_sha256'] ?? '')
            || array_keys($report['cases'] ?? []) !== [1000, 10000]) {
            throw new RuntimeException('La medición del tenant '.$member.' está incompleta.');
        }
        $reports[$member] = $report;
    }
    if ($reports['a']['tenant']['database_sha256'] === $reports['b']['tenant']['database_sha256']
        || $reports['a']['tenant']['hostname'] === $reports['b']['tenant']['hostname']
        || $reports['a']['engine'] !== $reports['b']['engine']
        || $reports['a']['tenant']['central_sha256'] !== $reports['b']['tenant']['central_sha256']
        || !$reports['a']['tenant']['isolation_verified'] || !$reports['b']['tenant']['isolation_verified']) {
        throw new RuntimeException('La cohorte requiere dos empresas distintas en el mismo motor.');
    }
    echo json_encode($reports, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) $worker->stop(5);
    }
    try {
        if ($central !== null) BenchmarkTenantCohort::clean($central);
    } finally {
        if (!(new Filesystem())->deleteDirectory($directory)) {
            throw new RuntimeException('No se pudo eliminar la cohorte temporal.');
        }
    }
}
