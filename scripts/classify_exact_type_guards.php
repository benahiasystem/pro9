<?php
// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
/** Materialize narrow, individual AST proofs; never approve monetary arithmetic or whole files. */
$root = dirname(__DIR__);
$commands = [
    'php'=>escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/audit_exact_calculations.php'),
    'javascript'=>'node '.escapeshellarg(__DIR__.'/audit_exact_calculations.cjs'),
];
$path = $root.'/config/exact_rounding_registry.json';
$registry = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$added = 0; $refreshed = 0; $hashes = [];
foreach ($commands as $language=>$command) {
    $engine = $language === 'php' ? ['scripts/audit_exact_calculations.php','scripts/exact_guard_proof.php','composer.lock']
        : ['scripts/audit_exact_calculations.cjs','scripts/exact_guard_proof.cjs','scripts/exact_vue_audit.cjs'];
    $engineHashes = [];
    foreach ($engine as $file) $engineHashes[$file] = hash_file('sha256',$root.'/'.$file);
    $pipes = [];
    $process = proc_open($command, [1=>['pipe','w'], 2=>['file','php://stderr','w']], $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar el auditor.');
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    if (proc_close($process) !== 0) throw new RuntimeException('El auditor falló; no se modifica el registro.');
    $audit = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    if ($audit['parse_errors']) throw new RuntimeException('Hay errores de análisis; no se modifica el registro.');
    foreach ($engineHashes as $file=>$hash) if (hash_file('sha256',$root.'/'.$file) !== $hash) throw new RuntimeException('Cambió el analizador durante la revisión; repita el análisis.');
    foreach ($audit[$language === 'php' ? 'php_review' : 'javascript_review'] as $row) {
        if (!in_array($row['technical_proof'] ?? null, ['strict_type_guard','nonnumeric_string_equality','loose_nonnumeric_string_equality','nullish_presence_guard','known_date_format','array_union','native_counter_comparison'], true)) continue;
        $previous = $registry[$row['id']] ?? null;
        if ($previous && !isset($previous['proof'])) continue; // Never rewrite a manual review.
        if ($previous && ($previous['category'] !== 'technical' || $previous['proof'] !== $row['technical_proof'])) throw new RuntimeException('La aprobación anterior no coincide con la prueba AST actual.');
        $test = $row['technical_proof'] === 'nullish_presence_guard' ? 'tests/js/exact-vue-audit.test.cjs#nullish guards prove presence only and retain nested monetary candidates'
            : ($row['technical_proof'] === 'native_counter_comparison' ? 'tests/Unit/ExactCounterProofTest.php#test_only_resolved_native_counters_compared_with_integer_literals_are_proven'
            : ($row['technical_proof'] === 'loose_nonnumeric_string_equality' ? 'tests/js/exact-vue-audit.test.cjs#loose nonnumeric guards exclude numeric coercions and nested money'
            : ($row['technical_proof'] === 'array_union' ? 'tests/Unit/ExactArrayUnionProofTest.php#test_only_array_literal_unions_have_a_structural_proof' : ($row['technical_proof'] === 'known_date_format'
            ? 'tests/Unit/ExactDateFormatProofTest.php#test_only_resolved_date_receivers_are_approved'
            : ($language === 'php' ? 'tests/Unit/ExactGuardProofTest.php#test_only_type_or_nonnumeric_string_guards_are_proven' : 'tests/js/exact-vue-audit.test.cjs#classification proof never exempts')))));
        $evidence = [$row['file'], 'scripts/exact_guard_proof.'.($language === 'php' ? 'php' : 'cjs'),$test];
        if ($row['technical_proof'] === 'known_date_format') $evidence[] = 'composer.lock';
        $reasons = ['strict_type_guard'=>'Comparación estricta con null/booleano; comprueba tipo o presencia sin calcular un importe: ',
            'nonnumeric_string_equality'=>'Igualdad con literal de texto no numérico; comprueba una categoría sin adaptar valores decimales: ',
            'loose_nonnumeric_string_equality'=>'Igualdad JavaScript con literal cuya conversión numérica es NaN; compara texto/tipo, no importes. Los operandos anidados conservan su propia revisión: ',
            'nullish_presence_guard'=>'Igualdad abstracta JavaScript con literal null: comprueba presencia sin convertir el otro operando a número. No acredita una comparación monetaria ni sus cálculos anidados: ',
            'native_counter_comparison'=>'Contador nativo PHP resuelto comparado con un entero literal; compara longitud/cantidad discreta sin adaptar importes. Los operandos anidados se revisan por separado: ',
            'known_date_format'=>'Formato de fecha desde un constructor/fábrica de fecha resuelto y métodos concretos de fecha; no limita un importe: ',
            'array_union'=>'El operador + de PHP tiene un literal de arreglo: une claves de arreglos o falla por tipo; sus operandos anidados se revisan individualmente: '];
        $review = ['category'=>'technical', 'justification'=>$reasons[$row['technical_proof']].$row['expression'],
            'evidence'=>$evidence, 'evidence_sha256'=>[], 'proof'=>$row['technical_proof'],
            'source'=>['file'=>$row['file'],'symbol'=>$row['symbol'],'operation'=>$row['operation'],'operation_language'=>$language]];
        foreach ($evidence as $reference) {
            $file = explode('#', $reference, 2)[0];
            $hashes[$file] ??= hash_file('sha256', $root.'/'.$file);
            $review['evidence_sha256'][$file] = $hashes[$file];
        }
        if (($audit['source_hashes'][$row['file']] ?? null) !== $hashes[$row['file']]) throw new RuntimeException('La fuente cambió después de analizarla; no se renueva la aprobación.');
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
