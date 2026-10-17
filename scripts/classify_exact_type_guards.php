<?php
// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
/** Materialize individual AST proofs; never approve monetary arithmetic or whole files. */
$root = dirname(__DIR__);
$commands = [
    'php'=>escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/audit_exact_calculations.php'),
    'javascript'=>'node '.escapeshellarg(__DIR__.'/audit_exact_calculations.cjs'),
];
$path = $root.'/config/exact_rounding_registry.json';
$registry = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$added = 0; $refreshed = 0; $hashes = [];
foreach ($commands as $language=>$command) {
    $pipes = [];
    $process = proc_open($command, [1=>['pipe','w'], 2=>['file','php://stderr','w']], $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar el auditor.');
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    if (proc_close($process) !== 0) throw new RuntimeException('El auditor falló; no se modifica el registro.');
    $audit = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    if ($audit['parse_errors']) throw new RuntimeException('Hay errores de análisis; no se modifica el registro.');
    foreach ($audit[$language === 'php' ? 'php_review' : 'javascript_review'] as $row) {
        if (!in_array($row['technical_proof'] ?? null, ['strict_type_guard','nonnumeric_string_equality'], true)) continue;
        $previous = $registry[$row['id']] ?? null;
        if ($previous && !isset($previous['proof'])) continue; // Never rewrite a manual review.
        if ($previous && ($previous['category'] !== 'technical' || $previous['proof'] !== $row['technical_proof'])) throw new RuntimeException('La aprobación anterior no coincide con la prueba AST actual.');
        $evidence = [$row['file'], 'scripts/exact_guard_proof.'.($language === 'php' ? 'php' : 'cjs'),
            $language === 'php' ? 'tests/Unit/ExactGuardProofTest.php#test_only_type_or_nonnumeric_string_guards_are_proven' : 'tests/js/exact-vue-audit.test.cjs#classification proof never exempts'];
        $review = ['category'=>'technical', 'justification'=>($row['technical_proof'] === 'strict_type_guard'
            ? 'Comparación estricta con null/booleano; comprueba tipo o presencia sin calcular un importe: '
            : 'Igualdad con literal de texto no numérico; comprueba una categoría sin adaptar valores decimales: ').$row['expression'],
            'evidence'=>$evidence, 'evidence_sha256'=>[], 'proof'=>$row['technical_proof'],
            'source'=>['file'=>$row['file'],'symbol'=>$row['symbol'],'operation'=>$row['operation'],'operation_language'=>$language]];
        foreach ($evidence as $reference) {
            $file = explode('#', $reference, 2)[0];
            $hashes[$file] ??= hash_file('sha256', $root.'/'.$file);
            $review['evidence_sha256'][$file] = $hashes[$file];
        }
        if ($previous === $review) continue;
        $registry[$row['id']] = $review;
        if ($previous) $refreshed++; else $added++;
    }
}
if (in_array('--write', $argv, true)) {
    ksort($registry);
    file_put_contents($path, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
}
echo json_encode(['individual_type_guard_reviews'=>$added, 'reproved_type_guards'=>$refreshed, 'written'=>in_array('--write', $argv, true)], JSON_THROW_ON_ERROR).PHP_EOL;
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
