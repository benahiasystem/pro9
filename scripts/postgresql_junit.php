<?php
declare(strict_types=1);

/** Read PHPUnit's unfiltered discovery output, retaining exact dataset identities. */
function postgresqlTestInventory(string $path, array $excludedGroups): array
{
    $document=new DOMDocument();
    $document->resolveExternals=false;$document->substituteEntities=false;
    $previous=libxml_use_internal_errors(true);
    try {
        if (!is_file($path) || !$document->load($path,LIBXML_NONET) || $document->doctype
            || $document->documentElement?->nodeName!=='tests') {
            throw new RuntimeException('El inventario de PHPUnit está ausente o es inválido.');
        }
    } finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
    $inventory=[];$seen=[];
    foreach ($document->documentElement->childNodes as $class) {
        if (!$class instanceof DOMElement) continue;
        if ($class->tagName!=='testCaseClass' || $class->getAttribute('name')==='') {
            throw new RuntimeException('El inventario contiene una clase o formato no reconocido.');
        }
        foreach ($class->childNodes as $method) {
            if (!$method instanceof DOMElement) continue;
            if ($method->tagName!=='testCaseMethod' || $method->getAttribute('name')===''
                || ($method->hasAttribute('dataSet') && $method->getAttribute('dataSet')==='')) {
                throw new RuntimeException('El inventario contiene una prueba sin identidad válida.');
            }
            $name=$method->getAttribute('name');
            if ($method->hasAttribute('dataSet')) $name.=' with data set '.$method->getAttribute('dataSet');
            $className=$class->getAttribute('name');$key=$className.'::'.$name;
            if (isset($seen[$key])) throw new RuntimeException('El inventario contiene pruebas duplicadas.');
            $seen[$key]=true;
            $groups=array_values(array_filter(explode(',',$method->getAttribute('groups')),static fn($group)=>$group!==''));
            if (array_intersect($excludedGroups,$groups)) continue;
            $inventory[$key]=['class'=>$className,'name'=>$name,'groups'=>$groups];
        }
    }
    if (!$inventory) throw new RuntimeException('El inventario de pruebas obligatorias está vacío.');
    ksort($inventory);
    return $inventory;
}

/** Fresh PHP processes bound the retained library state without changing any test. */
function postgresqlFunctionalFilters(): array
{
    $prefix=preg_quote('Tests\\Unit\\FiscalEmissionSchemaTest::','~');
    $accounting=preg_quote('Modules\\Accounting\\Tests\\','~');
    return ['fiscal'=>'~^'.$prefix.'~', 'general'=>'~^(?!(?:'.$prefix.'|'.$accounting.'))~', 'accounting'=>'~^'.$accounting.'~'];
}

/** The explicit user deferral covers only the existing POS budget test. */
function postgresqlDeferPosPerformance(array $all, array $functional): array
{
    foreach ($functional as $key=>$case) {
        if (!isset($all[$key]) || $all[$key]!==$case) {
            throw new RuntimeException('El aplazamiento alteró el inventario funcional.');
        }
    }
    $class='Tests\\Unit\\PostgresPerformanceTest';
    $pos='test_pos_catalog_p95_matches_the_temporary_mysql_reference';
    $remaining='test_http_and_sales_report_p95_matches_the_temporary_mysql_reference';
    $performance=array_diff_key($all,$functional);
    $deferredKey=$class.'::'.$pos;
    $mandatoryKey=$class.'::'.$remaining;
    if (count($performance)!==2 || !$functional
        || !in_array('postgres-performance', $performance[$deferredKey]['groups'] ?? [], true)
        || !in_array('postgres-performance-pos', $performance[$deferredKey]['groups'] ?? [], true)
        || !in_array('postgres-performance', $performance[$mandatoryKey]['groups'] ?? [], true)
        || in_array('postgres-performance-pos', $performance[$mandatoryKey]['groups'] ?? [], true)
        || ($performance[$deferredKey]['class'] ?? null)!==$class || ($performance[$deferredKey]['name'] ?? null)!==$pos
        || ($performance[$mandatoryKey]['class'] ?? null)!==$class || ($performance[$mandatoryKey]['name'] ?? null)!==$remaining) {
        throw new RuntimeException('El aplazamiento debe conservar HTTP y reportes como obligatorios y excluir sólo el POS.');
    }
    $deferred=[$deferredKey=>$performance[$deferredKey]];
    return ['mandatory'=>array_diff_key($all,$deferred), 'deferred'=>$deferred];
}

/** Resolve reviewed class, method and dataset names against the actual discovery. */
function postgresqlVesTestSelection(array $inventory, array $selectors): array
{
    $excluded=[];
    foreach ($selectors as $selector) {
        [$class,$name]=explode('::',$selector,2);
        $matches=array_filter($inventory,static function (array $case, string $key) use ($class,$name,$selector): bool {
            return $key===$selector || ($case['class']===$class
                && ($name==='*' || str_starts_with($case['name'],$name.' with data set ')));
        },ARRAY_FILTER_USE_BOTH);
        if (!$matches) throw new RuntimeException('La exclusión de factura USD no coincide con el inventario: '.$selector);
        $excluded+=$matches;
    }
    return ['included'=>array_diff_key($inventory,$excluded), 'excluded'=>$excluded];
}

/** Keep the phase prefix and exclude only the exact discovered case identities. */
function postgresqlExcludeCases(string $phaseFilter, array $excluded): string
{
    if (!$excluded) return $phaseFilter;
    $names=array_map(static fn (string $name): string => preg_quote($name,'~'),array_keys($excluded));
    return '~^(?!(?:'.implode('|',$names).')$)'.substr($phaseFilter,2);
}

/** Merge isolated PHPUnit phases without omitting failed, skipped or duplicate cases. */
function postgresqlMergeJunit(array $phases, string $output, ?array $expectedInventory=null): array
{
    if (!$phases) throw new RuntimeException('Faltan las fases obligatorias de PHPUnit.');
    $merged=new DOMDocument('1.0', 'UTF-8');
    $merged->formatOutput=true;
    $root=$merged->appendChild($merged->createElement('testsuites'));
    $suite=$root->appendChild($merged->createElement('testsuite'));
    $suite->setAttribute('name', 'PostgreSQL 17.11: fases aisladas');
    $totals=['tests'=>0, 'assertions'=>0, 'errors'=>0, 'warnings'=>0, 'failures'=>0, 'skipped'=>0, 'time'=>0.0];
    $seen=[];
    foreach ($phases as $name=>$path) {
        if (!is_file($path)) throw new RuntimeException('Falta el informe de la fase '.$name.'.');
        $document=new DOMDocument();
        $document->resolveExternals=false;
        $document->substituteEntities=false;
        $previous=libxml_use_internal_errors(true);
        try {
            if (!$document->load($path, LIBXML_NONET) || $document->doctype) {
                throw new RuntimeException('Informe XML inválido en la fase '.$name.'.');
            }
        } finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
        $cases=$document->getElementsByTagName('testcase');
        if (!$cases->length) throw new RuntimeException('La fase '.$name.' no ejecutó pruebas.');
        $phase=$suite->appendChild($merged->createElement('testsuite'));
        $phase->setAttribute('name', (string)$name);
        foreach ($cases as $case) {
            $key=($case->getAttribute('class') ?: $case->getAttribute('classname')).'::'.$case->getAttribute('name');
            if ($case->getAttribute('name')==='' || isset($seen[$key])) {
                throw new RuntimeException('Prueba ausente o duplicada entre fases: '.$key);
            }
            $seen[$key]=true;
            $totals['tests']++;
            $assertions=$case->getAttribute('assertions');
            $time=$case->getAttribute('time');
            if (!preg_match('/^[0-9]+$/D', $assertions) || !is_numeric($time) || !is_finite((float)$time) || (float)$time<0) {
                throw new RuntimeException('Métricas inválidas en '.$key.'.');
            }
            $totals['assertions']+=(int)$assertions;
            $totals['time']+=(float)$time;
            foreach (['errors'=>'error','warnings'=>'warning','failures'=>'failure','skipped'=>'skipped'] as $counter=>$tag) {
                if ($case->getElementsByTagName($tag)->length) $totals[$counter]++;
            }
            $phase->appendChild($merged->importNode($case, true));
        }
    }
    if ($expectedInventory!==null) {
        $expected=array_keys($expectedInventory);$actual=array_keys($seen);
        sort($expected);sort($actual);
        if (!$expected || $expected!==$actual) {
            throw new RuntimeException('La cobertura no coincide con el inventario: '.count(array_diff($expected,$actual)).
                ' pruebas ausentes y '.count(array_diff($actual,$expected)).' inesperadas.');
        }
    }
    foreach ($totals as $attribute=>$value) $suite->setAttribute($attribute, (string)$value);
    if ($merged->save($output)===false) throw new RuntimeException('No se pudo guardar el informe conjunto.');
    return $totals;
}
