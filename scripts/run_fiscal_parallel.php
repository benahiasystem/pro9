<?php

// Lanza grupos disjuntos de pruebas en procesos con bases temporales propias.
$root = dirname(__DIR__);
chdir($root);
$workers = filter_var($argv[1] ?? 2, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);
$listOnly = false;
$filter = null;
for ($i = 2; $i < count($argv); $i++) {
    if ($argv[$i] === '--list') $listOnly = true;
    elseif ($argv[$i] === '--filter' && isset($argv[$i + 1])) $filter = $argv[++$i];
    else $workers = false;
}
if ($workers === false) {
    fwrite(STDERR, "Uso: php scripts/run_fiscal_parallel.php [1-4] [--list] [--filter patrón]\n");
    exit(2);
}
$base = [PHP_BINARY, 'vendor/bin/phpunit', '--no-configuration', '--bootstrap', 'vendor/autoload.php'];
$suite = 'tests/Unit/FiscalEmissionSchemaTest.php';
$list = proc_open(array_merge($base, ['--list-tests', $suite]), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
if (!is_resource($list)) throw new RuntimeException('No se pudo enumerar la batería fiscal.');
fclose($pipes[0]);
$output = stream_get_contents($pipes[1]); fclose($pipes[1]);
$errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
if (proc_close($list) !== 0) throw new RuntimeException('No se pudo enumerar la batería fiscal: ' . $errors);
preg_match_all('/^ - Tests\\\\Unit\\\\FiscalEmissionSchemaTest::([A-Za-z0-9_]+)$/m', $output, $matches);
$tests = $matches[1];
$listed = preg_match_all('/^ - /m', $output);
if (!$tests || count($tests) !== $listed || count($tests) !== count(array_unique($tests))) {
    throw new RuntimeException('El inventario contiene pruebas no compatibles o duplicadas; ejecute la batería sin repartir.');
}
if ($filter !== null) {
    $pattern = '~' . str_replace('~', '\\~', $filter) . '~';
    if (@preg_match($pattern, '') === false) throw new InvalidArgumentException('El filtro no es una expresión regular válida.');
    $tests = array_values(array_filter($tests, static fn ($test) => preg_match($pattern, $test)));
    if (!$tests) throw new InvalidArgumentException('El filtro no seleccionó pruebas.');
}
$groups = array_fill(0, min($workers, count($tests)), []);
foreach ($tests as $index => $name) $groups[$index % count($groups)][] = $name;
if ($listOnly) {
    foreach ($groups as $index => $group) foreach ($group as $name) echo ($index + 1) . " " . $name . "\n";
    exit(0);
}
$processes = [];
$interrupted = false;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    $interrupt = static function () use (&$processes, &$interrupted): void {
        $interrupted = true;
        foreach ($processes as $running) proc_terminate($running);
    };
    pcntl_signal(SIGINT, $interrupt);
    pcntl_signal(SIGTERM, $interrupt);
}
putenv('PRO9_FISCAL_MYSQL_TESTS=1');
foreach ($groups as $index => $group) {
    $pattern = '(?:' . implode('|', array_map(static fn ($name) => preg_quote($name, '/'), $group)) . ')$';
    $command = array_merge($base, ['--printer', 'Tests\\Support\\FiscalProgressPrinter', '--filter', $pattern, $suite]);
    $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root);
    if (!is_resource($process)) {
        foreach ($processes as $running) proc_terminate($running);
        throw new RuntimeException('No se pudo iniciar el trabajador ' . ($index + 1));
    }
    $processes[] = $process;
    fwrite(STDERR, sprintf("Trabajador %d: %d pruebas.\n", $index + 1, count($group)));
}
$failed = false;
foreach ($processes as $process) if (proc_close($process) !== 0) $failed = true;
exit($interrupted ? 130 : ($failed ? 1 : 0));
