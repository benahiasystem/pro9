<?php
// ######## INICIO AUDITORIA CALCULOS EXACTOS SAB ########
/** Materialize narrow, individual AST proofs; never approve monetary arithmetic or whole files. */
$root = dirname(__DIR__);
require __DIR__.'/exact_audit_scope.php';
$scope=exactAuditScopeArgument($argv);
$scopePolicy=$scope===null ? null : exactAuditScopePolicy($root,$scope);
$existingOnly=in_array('--existing-only',$argv,true);
$newOnly=in_array('--new-only',$argv,true);
if ($existingOnly && $newOnly) throw new InvalidArgumentException('Seleccione un único modo de revisión del registro.');
$commands = [
    'php'=>escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/audit_exact_calculations.php'),
    'javascript'=>'node '.escapeshellarg(__DIR__.'/audit_exact_calculations.cjs'),
];
$path = $root.'/config/exact_rounding_registry.json';
$registry = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$added = 0; $refreshed = 0; $hashes = []; $retainedManualCategories=[];
foreach ($commands as $language=>$command) {
    $engine = $language === 'php' ? ['scripts/audit_exact_calculations.php','scripts/exact_guard_proof.php','composer.lock']
        : ['scripts/audit_exact_calculations.cjs','scripts/exact_guard_proof.cjs','scripts/exact_vue_audit.cjs','scripts/exact_compare_proof.cjs','resources/js/helpers/exchange-rate-math.js','vite.config.js'];
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
        if ($scopePolicy!==null && !exactAuditScopeContains($row['file'],$scopePolicy)) continue;
        if ($existingOnly && !isset($registry[$row['id']])) continue;
        if ($newOnly && isset($registry[$row['id']])) continue;
        if (!in_array($row['technical_proof'] ?? null, ['strict_type_guard','strict_undefined_guard','strict_empty_string_guard','class_name_equality','nonnumeric_string_equality','loose_nonnumeric_string_equality','nullish_presence_guard','known_date_format','array_union','native_counter_comparison','vue_text_concatenation','rendered_exact_decimal','literal_integer_arithmetic','exact_compare_result'], true)) continue;
        $previous = $registry[$row['id']] ?? null;
        if ($previous && !isset($previous['proof'])) continue; // Never rewrite a manual review.
        $category=match ($row['technical_proof']) {
            'rendered_exact_decimal'=>'presentation', 'literal_integer_arithmetic', 'exact_compare_result'=>'exact_arithmetic', default=>'technical',
        };
        if ($previous && $previous['proof'] !== $row['technical_proof']) throw new RuntimeException('La aprobación anterior no coincide con la prueba AST actual: '.$row['id']);
        // A manual validation/storage review may also cite a structural proof.
        // Retain it for its own review instead of changing its category or hashes.
        if ($previous && $previous['category'] !== $category) {$retainedManualCategories[]=$row['id'];continue;}
        $test = $row['technical_proof'] === 'strict_empty_string_guard' && $language === 'javascript' ? 'tests/js/exact-vue-audit.test.cjs#strict empty string guards preserve zero and never approve loose coercion'
            : ($row['technical_proof'] === 'rendered_exact_decimal' ? 'tests/Unit/ExactPresentationProofTest.php#test_only_resolved_exact_formatters_at_final_output_are_proven'
            : ($row['technical_proof'] === 'vue_text_concatenation' ? 'tests/js/exact-vue-audit.test.cjs#Vue text concatenation proves rendered joins without approving amounts inside interpolations'
            : ($row['technical_proof'] === 'nullish_presence_guard' ? 'tests/js/exact-vue-audit.test.cjs#nullish guards prove presence only and retain nested monetary candidates'
            : ($row['technical_proof'] === 'native_counter_comparison' ? 'tests/Unit/ExactCounterProofTest.php#test_only_resolved_native_counters_compared_with_integer_literals_are_proven'
            : ($row['technical_proof'] === 'loose_nonnumeric_string_equality' ? 'tests/js/exact-vue-audit.test.cjs#loose nonnumeric guards exclude numeric coercions and nested money'
            : ($row['technical_proof'] === 'array_union' ? 'tests/Unit/ExactArrayUnionProofTest.php#test_only_array_literal_unions_have_a_structural_proof' : ($row['technical_proof'] === 'known_date_format'
            ? 'tests/Unit/ExactDateFormatProofTest.php#test_only_resolved_date_receivers_are_approved'
            : ($language === 'php' ? 'tests/Unit/ExactGuardProofTest.php#test_only_type_or_nonnumeric_string_guards_are_proven' : 'tests/js/exact-vue-audit.test.cjs#classification proof never exempts'))))))));
        if ($row['technical_proof'] === 'literal_integer_arithmetic') $test=$language === 'php'
            ? 'tests/Unit/ExactLiteralArithmeticProofTest.php#test_only_literal_integer_arithmetic_is_proven'
            : 'tests/js/exact-literal-arithmetic-proof.test.cjs#literal arithmetic rejects inexact and dynamic operands';
        if ($row['technical_proof'] === 'strict_undefined_guard') $test='tests/js/exact-undefined-proof.test.cjs#scope-resolved global undefined proves strict presence only';
        $evidence = [$row['file'], 'scripts/exact_guard_proof.'.($language === 'php' ? 'php' : 'cjs'),$test];
        if ($row['technical_proof'] === 'exact_compare_result') {
            $evidence = [$row['file'], 'scripts/exact_compare_proof.cjs', 'resources/js/helpers/exchange-rate-math.js', 'vite.config.js', 'package-lock.json',
                'tests/js/exact-compare-proof.test.cjs#recognizes exact comparison outcomes through static named imports',
                'tests/js/exact-compare-proof.test.cjs#rejects shadowed factories and unrelated operands',
                'tests/js/exact-compare-proof.test.cjs#recognizes the comparison sign on a local exact amount with read-only uses',
                'tests/js/exact-compare-proof.test.cjs#leaves escaped, reassigned and unresolved receivers for manual review',
                'tests/js/exact-rational-invariants.test.cjs#rational operations preserve large fractions'];
        }
        if ($row['technical_proof'] === 'strict_undefined_guard') $evidence[]='package-lock.json';
        if ($row['technical_proof'] === 'known_date_format') {
            $evidence[] = 'composer.lock';
            $evidence[] = 'tests/Unit/ExactLocalDateProofTest.php#test_only_a_unique_dominating_local_date_assignment_is_proven';
        }
        if ($row['technical_proof'] === 'rendered_exact_decimal') $evidence[] = 'app/Support/Exact/Decimal.php';
        $reasons = ['exact_compare_result'=>'Una importación resuelta enlaza el comparador racional. Las variables locales provienen de esa fábrica, permanecen constantes y sólo se usan como receptor de métodos de lectura. Se compara el signo entero con un literal exacto. Los operandos y sus cálculos se revisan por separado. Expresión ',
            'strict_undefined_guard'=>'Comparación estricta con undefined global resuelto por ámbitos léxicos, o con void 0 literal. Comprueba presencia sin coerción numérica; excluye bindings sombreados, eval directo, with y contextos de plantilla Vue para el identificador. Sus operandos anidados mantienen revisión independiente: ',
            'strict_type_guard'=>'Comparación estricta con null/booleano; comprueba tipo o presencia sin calcular un importe: ',
            'literal_integer_arithmetic'=>'Cálculo exclusivamente entre literales enteros, comprobado con enteros arbitrarios: cada paso y el resultado permanecen en el rango entero exacto 2^53-1. Rechaza división con residuo, cero divisor, intermedios fuera de rango y operandos dinámicos; no acredita cálculos externos a este árbol: ',
            'strict_empty_string_guard'=>'Comparación estricta con cadena vacía: comprueba una entrada vacía sin convertir cero, texto decimal, booleanos ni null. Los operandos anidados conservan su revisión: ',
            'class_name_equality'=>'Igualdad con un nombre literal de clase PHP (::class), que es texto no numérico; selecciona un tipo sin operar importes: ',
            'nonnumeric_string_equality'=>'Igualdad con literal de texto no numérico; comprueba una categoría sin adaptar valores decimales: ',
            'loose_nonnumeric_string_equality'=>'Igualdad JavaScript con literal cuya conversión numérica es NaN; compara texto/tipo, no importes. Los operandos anidados conservan su propia revisión: ',
            'nullish_presence_guard'=>'Igualdad abstracta JavaScript con literal null: comprueba presencia sin convertir el otro operando a número. No acredita una comparación monetaria ni sus cálculos anidados: ',
            'native_counter_comparison'=>'Contador nativo PHP resuelto comparado con un entero literal; compara longitud/cantidad discreta sin adaptar importes. Los operandos anidados se revisan por separado: ',
            'known_date_format'=>'Formato de fecha desde un constructor/fábrica de fecha resuelto y métodos concretos de fecha; no limita un importe: ',
            'array_union'=>'El operador + de PHP tiene un literal de arreglo: une claves de arreglos o falla por tipo; sus operandos anidados se revisan individualmente: ',
            'vue_text_concatenation'=>'Concatenación generada por Vue exclusivamente para texto renderizado: une literales y resultados de _s, sin calcular importes. Los operadores dentro de las interpolaciones se revisan por separado: ',
            'rendered_exact_decimal'=>'Formato de App\\Support\\Exact\\Decimal resuelto por el AST, con precisión literal válida y salida directa echo/Blade: adaptación final de presentación sin cálculo posterior. No acredita sus operandos, productores ni almacenamiento, que conservan sus revisiones independientes: '];
        $review = ['category'=>$category, 'justification'=>$reasons[$row['technical_proof']].$row['expression'],
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
echo json_encode(['individual_type_guard_reviews'=>$added, 'reproved_type_guards'=>$refreshed, 'scope'=>$scope ?? 'global',
    'existing_only'=>$existingOnly,'new_only'=>$newOnly,'retained_manual_categories'=>$retainedManualCategories,
    'written'=>in_array('--write', $argv, true)], JSON_THROW_ON_ERROR).PHP_EOL;
// ######## FIN AUDITORIA CALCULOS EXACTOS SAB ########
