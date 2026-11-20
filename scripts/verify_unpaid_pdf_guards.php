<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/exact_guard_proof.php';

/** Verify any matching occurrence of an audited expression, including duplicates. */
function unpaidInvoiceFlag(Node $node): bool
{
    return $node instanceof Node\Expr\Variable && $node->name === 'isUnpaidInvoice';
}

function unpaidLegacyBranch(Node $node): ?string
{
    while (($parent = $node->getAttribute('parent')) instanceof Node) {
        if ($parent instanceof Node\Expr\Ternary && unpaidInvoiceFlag($parent->cond) && $parent->else === $node) {
            return 'ternary false operand';
        }
        if ($parent instanceof Node\Stmt\Else_) {
            $statement = $parent->getAttribute('parent');
            if ($statement instanceof Node\Stmt\If_ && unpaidInvoiceFlag($statement->cond)) return 'else block of invoice guard';
        }
        $node = $parent;
    }
    return null;
}

$auditFile = $argv[1] ?? null;
if (!$auditFile || !is_file($auditFile)) {
    fwrite(STDERR, "Use php scripts/verify_unpaid_pdf_guards.php RUTA_AUDITORIA_PHP.json\n");
    exit(2);
}
$audit = json_decode(file_get_contents($auditFile), true, 512, JSON_THROW_ON_ERROR);
$files = [];
foreach ($audit['php_review'] as $row) {
    if (preg_match('~^app/CoreFacturalo/Templates/pdf/[a-zA-Z0-9_-]+/unpaid_(?:a4|ticket|ticket_58)\.blade\.php$~D', $row['file'])) {
        $files[$row['file']][] = $row;
    }
}
if (!$files) throw new RuntimeException('La auditoría no incluye las plantillas de cuentas por cobrar.');
$blade = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$finder = new NodeFinder();
$printer = new Standard();
$result = [];
foreach ($files as $file => $rows) {
    $source = file_get_contents(dirname(__DIR__).'/'.$file);
    $sha = hash('sha256', $source);
    if ($sha !== $audit['source_hashes'][$file]) throw new RuntimeException('La fuente difiere de la auditada: '.$file);
    $nodes = exactAuditResolveNames($parser->parse($blade->compileString($source)));
    $guard = $finder->findFirst($nodes, static fn (Node $node) => $node instanceof Node\Stmt\Expression
        && $node->expr instanceof Node\Expr\Assign && unpaidInvoiceFlag($node->expr->var));
    if (!$guard || !in_array($guard, $nodes, true)) throw new RuntimeException('La condición de factura no está en el nivel principal: '.$file);
    $assignment = $guard->expr;
    $type = $assignment->expr;
    if (!$type instanceof Node\Expr\Instanceof_ || !$type->expr instanceof Node\Expr\Variable || $type->expr->name !== 'document'
        || !$type->class instanceof Node\Name\FullyQualified || (string) $type->class !== 'App\\Models\\Tenant\\Document') {
        throw new RuntimeException('El indicador de factura debe depender del modelo Document: '.$file);
    }
    foreach ($finder->find($nodes, 'unpaidInvoiceFlag') as $variable) {
        if ($variable === $assignment->var) continue;
        $parent = $variable->getAttribute('parent');
        if ($variable->getStartFilePos() <= $assignment->getEndFilePos() ||
            (!$parent instanceof Node\Expr\Ternary && !$parent instanceof Node\Stmt\If_) || $parent->cond !== $variable) {
            throw new RuntimeException('El indicador tiene un uso distinto de una condición directa: '.$file);
        }
    }
    $subjects = array_fill_keys(array_column($rows, 'expression'), true);
    $branches = [];
    foreach ($finder->find($nodes, static fn (Node $node) => $node instanceof Node\Expr) as $node) {
        $text = preg_replace('/\s+/', ' ', trim($printer->prettyPrintExpr($node)));
        if (isset($subjects[$text])) $branches[$text][] = unpaidLegacyBranch($node);
    }
    foreach ($rows as $row) {
        $matches = $branches[$row['expression']] ?? [];
        if (!$matches || in_array(null, $matches, true)) continue;
        $result[$row['id']] = ['file' => $file, 'expression' => $row['expression'], 'operation' => $row['operation'],
            'line' => $row['line'], 'guards' => array_values(array_unique($matches)), 'occurrences' => count($matches), 'file_sha256' => $sha];
    }
}
echo json_encode(['template_count' => count($files), 'candidates' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
