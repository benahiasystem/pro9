<?php
// ######## INICIO AUDITORIA CALCULOS EXACTOS SAB ########
/** Structural proofs apply to individual expressions, never their nested operands. */
function exactAuditResolveNames(array $nodes): array
{
    // Prepare names and ancestry for the whole subtree before proving a parent.
    // A single traversal has not connected the receiver child when the call enters.
    $resolver=new \PhpParser\NodeTraverser();
    $resolver->addVisitor(new \PhpParser\NodeVisitor\NameResolver(null,['replaceNodes'=>false]));
    $nodes=$resolver->traverse($nodes);
    $parents=new \PhpParser\NodeTraverser();
    $parents->addVisitor(new \PhpParser\NodeVisitor\ParentConnectingVisitor());
    return $parents->traverse($nodes);
}

function exactAuditGuardProof(\PhpParser\Node $node): ?string
{
    if (exactAuditRenderedDecimal($node)) return 'rendered_exact_decimal';
    if (exactAuditLiteralIntegerArithmetic($node)) return 'literal_integer_arithmetic';
    // PHP + with an array literal can only union arrays (or fail with TypeError).
    // This does not approve arithmetic nested inside its keys or values.
    if (($node instanceof \PhpParser\Node\Expr\BinaryOp\Plus || $node instanceof \PhpParser\Node\Expr\AssignOp\Plus)
        && (($node instanceof \PhpParser\Node\Expr\BinaryOp && ($node->left instanceof \PhpParser\Node\Expr\Array_ || $node->right instanceof \PhpParser\Node\Expr\Array_))
            || ($node instanceof \PhpParser\Node\Expr\AssignOp && $node->expr instanceof \PhpParser\Node\Expr\Array_))) return 'array_union';
    if (($node instanceof \PhpParser\Node\Expr\MethodCall || $node instanceof \PhpParser\Node\Expr\NullsafeMethodCall)
        && $node->name instanceof \PhpParser\Node\Identifier && strtolower((string)$node->name) === 'format'
        && exactAuditKnownDateReceiver($node->var)) return 'known_date_format';
    if (!$node instanceof \PhpParser\Node\Expr\BinaryOp) return null;
    // Only resolved native counters compared with an integer literal. An unknown
    // namespaced function, monetary operand or nested arithmetic is not covered.
    if ($node instanceof \PhpParser\Node\Expr\BinaryOp\Equal || $node instanceof \PhpParser\Node\Expr\BinaryOp\NotEqual
        || $node instanceof \PhpParser\Node\Expr\BinaryOp\Identical || $node instanceof \PhpParser\Node\Expr\BinaryOp\NotIdentical
        || $node instanceof \PhpParser\Node\Expr\BinaryOp\Greater || $node instanceof \PhpParser\Node\Expr\BinaryOp\GreaterOrEqual
        || $node instanceof \PhpParser\Node\Expr\BinaryOp\Smaller || $node instanceof \PhpParser\Node\Expr\BinaryOp\SmallerOrEqual) {
        foreach ([[$node->left,$node->right],[$node->right,$node->left]] as [$call,$literal]) {
            if (!$literal instanceof \PhpParser\Node\Scalar\Int_ || !$call instanceof \PhpParser\Node\Expr\FuncCall
                || !$call->name instanceof \PhpParser\Node\Name) continue;
            $name=$call->name instanceof \PhpParser\Node\Name\FullyQualified ? $call->name : $call->name->getAttribute('resolvedName');
            if ($name && in_array(strtolower((string)$name),['count','sizeof','strlen'],true)) return 'native_counter_comparison';
        }
    }
    $strict = $node instanceof \PhpParser\Node\Expr\BinaryOp\Identical || $node instanceof \PhpParser\Node\Expr\BinaryOp\NotIdentical;
    $equal = $strict || $node instanceof \PhpParser\Node\Expr\BinaryOp\Equal || $node instanceof \PhpParser\Node\Expr\BinaryOp\NotEqual;
    if (!$equal) return null;
    foreach ([$node->left, $node->right] as $operand) {
        if ($operand instanceof \PhpParser\Node\Scalar\String_ && !is_numeric($operand->value) && $operand->value !== '') return 'nonnumeric_string_equality';
        if ($strict && $operand instanceof \PhpParser\Node\Scalar\String_ && $operand->value === '') return 'strict_empty_string_guard';
        if ($strict && $operand instanceof \PhpParser\Node\Expr\ConstFetch && in_array(strtolower($operand->name->toString()), ['null','true','false'], true)) return 'strict_type_guard';
        if ($operand instanceof \PhpParser\Node\Expr\ClassConstFetch && $operand->class instanceof \PhpParser\Node\Name
            && $operand->name instanceof \PhpParser\Node\Identifier && strtolower((string)$operand->name) === 'class') return 'class_name_equality';
    }
    return null;
}

/** Literal-only integer operations, exact at every intermediate step on 64-bit PHP and JS. */
function exactAuditLiteralIntegerArithmetic(\PhpParser\Node $node): bool
{
    if (!$node instanceof \PhpParser\Node\Expr\BinaryOp\Plus
        && !$node instanceof \PhpParser\Node\Expr\BinaryOp\Minus
        && !$node instanceof \PhpParser\Node\Expr\BinaryOp\Mul
        && !$node instanceof \PhpParser\Node\Expr\BinaryOp\Div
        && !$node instanceof \PhpParser\Node\Expr\BinaryOp\Mod
        && !$node instanceof \PhpParser\Node\Expr\UnaryPlus
        && !$node instanceof \PhpParser\Node\Expr\UnaryMinus) return false;
    return exactAuditLiteralIntegerValue($node) !== null;
}

function exactAuditLiteralIntegerValue(\PhpParser\Node $node): ?\Brick\Math\BigInteger
{
    if ($node instanceof \PhpParser\Node\Scalar\Int_) {
        $value = \Brick\Math\BigInteger::of($node->value);
    } elseif ($node instanceof \PhpParser\Node\Expr\UnaryMinus || $node instanceof \PhpParser\Node\Expr\UnaryPlus) {
        $value = exactAuditLiteralIntegerValue($node->expr);
        if ($value === null) return null;
        if ($node instanceof \PhpParser\Node\Expr\UnaryMinus) $value = $value->negated();
    } elseif ($node instanceof \PhpParser\Node\Expr\BinaryOp) {
        $left = exactAuditLiteralIntegerValue($node->left);
        $right = exactAuditLiteralIntegerValue($node->right);
        if ($left === null || $right === null) return null;
        if ($node instanceof \PhpParser\Node\Expr\BinaryOp\Plus) $value = $left->plus($right);
        elseif ($node instanceof \PhpParser\Node\Expr\BinaryOp\Minus) $value = $left->minus($right);
        elseif ($node instanceof \PhpParser\Node\Expr\BinaryOp\Mul) $value = $left->multipliedBy($right);
        elseif ($node instanceof \PhpParser\Node\Expr\BinaryOp\Div || $node instanceof \PhpParser\Node\Expr\BinaryOp\Mod) {
            if ($right->isZero()) return null;
            [$quotient, $remainder] = $left->quotientAndRemainder($right);
            if ($node instanceof \PhpParser\Node\Expr\BinaryOp\Div) {
                if (!$remainder->isZero() || ($left->isZero() && $right->isNegative())) return null;
                $value = $quotient;
            } else $value = $remainder;
        } else return null;
    } else return null;
    // Checking each subtree excludes an overflowing intermediate that later cancels.
    return $value->abs()->isLessThanOrEqualTo('9007199254740991') ? $value : null;
}

/** A resolved exact formatter at an echo boundary, with literal valid options. */
function exactAuditRenderedDecimal(\PhpParser\Node $node): bool
{
    if (!$node instanceof \PhpParser\Node\Expr\StaticCall || !$node->class instanceof \PhpParser\Node\Name
        || !$node->name instanceof \PhpParser\Node\Identifier
        || !in_array(strtolower((string)$node->name),['format','represent'],true)) return false;
    $representation=strtolower((string)$node->name)==='represent';
    $class=$node->class instanceof \PhpParser\Node\Name\FullyQualified ? $node->class : $node->class->getAttribute('resolvedName');
    if (!$class || strtolower((string)$class) !== 'app\\support\\exact\\decimal'
        || count($node->args) < 1 || count($node->args) > ($representation ? 3 : 4)) return false;
    foreach ($node->args as $arg) if (!$arg instanceof \PhpParser\Node\Arg || $arg->unpack || $arg->name !== null) return false;
    if (isset($node->args[1]) && (!$node->args[1]->value instanceof \PhpParser\Node\Scalar\Int_
        || $node->args[1]->value->value < 0 || $node->args[1]->value->value > 12)) return false;
    if ($representation) {
        if (isset($node->args[2]) && (!$node->args[2]->value instanceof \PhpParser\Node\Expr\ConstFetch
            || !in_array(strtolower((string)$node->args[2]->value->name),['true','false'],true))) return false;
    } else foreach ([2,3] as $index) if (isset($node->args[$index]) && !$node->args[$index]->value instanceof \PhpParser\Node\Scalar\String_) return false;
    $output=$node;
    $parent=$node->getAttribute('parent');
    // Blade's escaped {{ }} output compiles to echo e(expression).
    if ($parent instanceof \PhpParser\Node\Arg && $parent->value === $node) {
        $call=$parent->getAttribute('parent');
        if (!$call instanceof \PhpParser\Node\Expr\FuncCall || !$call->name instanceof \PhpParser\Node\Name
            || count($call->args) !== 1 || $call->args[0] !== $parent || $parent->unpack || $parent->name !== null) return false;
        $name=$call->name instanceof \PhpParser\Node\Name\FullyQualified ? $call->name : $call->name->getAttribute('resolvedName');
        if (!$name || strtolower((string)$name) !== 'e') return false;
        $output=$call;
        $parent=$call->getAttribute('parent');
    }
    return $parent instanceof \PhpParser\Node\Stmt\Echo_ && in_array($output,$parent->exprs,true);
}

/** Require a resolved factory/new expression; a variable or a method name alone proves nothing. */
function exactAuditKnownDateReceiver(\PhpParser\Node $node): bool
{
    return exactAuditDateReceiverClass($node) !== null;
}

function exactAuditDateReceiverClass(\PhpParser\Node $node): ?string
{
    if ($node instanceof \PhpParser\Node\Expr\Variable) return exactAuditLocalDateReceiverClass($node);
    if ($node instanceof \PhpParser\Node\Expr\Ternary && $node->if !== null) {
        $yes=exactAuditDateReceiverClass($node->if);$no=exactAuditDateReceiverClass($node->else);
        $null=static fn($value)=>$value instanceof \PhpParser\Node\Expr\ConstFetch && strtolower((string)$value->name)==='null';
        return $yes !== null && ($no === $yes || $null($node->else)) ? $yes : ($no !== null && $null($node->if) ? $no : null);
    }
    $classes = ['carbon\\carbon','carbon\\carbonimmutable','illuminate\\support\\carbon','datetime','datetimeimmutable'];
    $factories = ['now','today','yesterday','tomorrow','parse','create','createfromformat','createfromdate','instance','createfromtimestamp'];
    if ($node instanceof \PhpParser\Node\Expr\StaticCall || $node instanceof \PhpParser\Node\Expr\New_) {
        if (!$node->class instanceof \PhpParser\Node\Name) return null;
        $name = $node->class instanceof \PhpParser\Node\Name\FullyQualified ? $node->class : $node->class->getAttribute('resolvedName');
        if (!$name || !in_array(strtolower((string)$name),$classes,true)) return null;
        if ($node instanceof \PhpParser\Node\Expr\New_) return (string)$name;
        return $node->name instanceof \PhpParser\Node\Identifier && in_array(strtolower((string)$node->name),$factories,true)
            && method_exists((string)$name,(string)$node->name) ? (string)$name : null;
    }
    if ($node instanceof \PhpParser\Node\Expr\MethodCall && $node->name instanceof \PhpParser\Node\Identifier) {
        // Concrete date-returning methods only; dynamic methods/macros remain pending.
        $class = exactAuditDateReceiverClass($node->var);
        return $class !== null && in_array(strtolower((string)$node->name), ['copy','settimezone','settimestamp','settime','setdate','modify','add','sub',
            'startofday','endofday','startofmonth','endofmonth','startofyear','endofyear'],true)
            && method_exists($class,(string)$node->name) ? $class : null;
    }
    return null;
}

/**
 * Prove a local date binding only when one direct assignment dominates this use.
 * Unknown parameters/captures, aliases, mutation and argument escapes remain pending.
 */
function exactAuditLocalDateReceiverClass(\PhpParser\Node\Expr\Variable $variable): ?string
{
    if (!is_string($variable->name)) return null;
    $function=$variable;
    while ($function && !$function instanceof \PhpParser\Node\FunctionLike) $function=$function->getAttribute('parent');
    if (!$function || $function->byRef) return null;
    $bindings=$function->getAttribute('exactLocalDateBindings');
    if ($bindings===null) {
        $bindings=['unsafe'=>false,'writes'=>[],'escaped'=>[]];
        $finder=new \PhpParser\NodeFinder();
        $names=static function ($node) use ($finder): array {
            return array_values(array_filter(array_map(static fn($v)=>is_string($v->name) ? $v->name : null,
                $finder->findInstanceOf(is_array($node) ? $node : [$node],\PhpParser\Node\Expr\Variable::class)),static fn($name)=>$name!==null));
        };
        foreach ($finder->find($function,static fn($node)=>true) as $node) {
            if (($node instanceof \PhpParser\Node\Expr\Variable && !is_string($node->name))
                || $node instanceof \PhpParser\Node\Expr\AssignRef || $node instanceof \PhpParser\Node\Expr\Eval_
                || $node instanceof \PhpParser\Node\Expr\Include_ || $node instanceof \PhpParser\Node\Stmt\Global_
                || $node instanceof \PhpParser\Node\Stmt\Goto_ || $node instanceof \PhpParser\Node\Stmt\Label
                || ($node instanceof \PhpParser\Node\ClosureUse && $node->byRef)
                || ($node instanceof \PhpParser\Node\ArrayItem && $node->byRef)) $bindings['unsafe']=true;
            if ($node instanceof \PhpParser\Node\Expr\FuncCall && $node->name instanceof \PhpParser\Node\Name) {
                $name=$node->name instanceof \PhpParser\Node\Name\FullyQualified ? $node->name : $node->name->getAttribute('resolvedName');
                if (in_array(strtolower((string)($name ?? $node->name)),['extract','parse_str','mb_parse_str','import_request_variables'],true)) $bindings['unsafe']=true;
            }
            if ($node instanceof \PhpParser\Node\Arg) foreach ($names($node->value) as $name) {
                // A direct variable, array or conditional can expose the binding by reference.
                // A date-method result is a separate expression and does not rebind the local.
                if ($node->value instanceof \PhpParser\Node\Expr\Variable || $node->byRef || $node->unpack
                    || $node->value instanceof \PhpParser\Node\Expr\Array_) $bindings['escaped'][$name]=true;
            }
            $target=null;$assignment=null;
            if ($node instanceof \PhpParser\Node\Expr\Assign || $node instanceof \PhpParser\Node\Expr\AssignOp) {
                $target=$node->var;$assignment=$node instanceof \PhpParser\Node\Expr\Assign && $node->var instanceof \PhpParser\Node\Expr\Variable ? $node : null;
            } elseif ($node instanceof \PhpParser\Node\Expr\PreInc || $node instanceof \PhpParser\Node\Expr\PostInc
                || $node instanceof \PhpParser\Node\Expr\PreDec || $node instanceof \PhpParser\Node\Expr\PostDec) $target=$node->var;
            elseif ($node instanceof \PhpParser\Node\Param || $node instanceof \PhpParser\Node\ClosureUse || $node instanceof \PhpParser\Node\Stmt\Catch_ || $node instanceof \PhpParser\Node\StaticVar) $target=$node->var;
            elseif ($node instanceof \PhpParser\Node\Stmt\Foreach_) $target=array_filter([$node->keyVar,$node->valueVar]);
            elseif ($node instanceof \PhpParser\Node\Stmt\Unset_) $target=$node->vars;
            if ($target!==null) foreach ($names($target) as $name) $bindings['writes'][$name][]=$assignment;
        }
        $function->setAttribute('exactLocalDateBindings',$bindings);
    }
    if ($bindings['unsafe'] || isset($bindings['escaped'][$variable->name])) return null;
    $writes=$bindings['writes'][$variable->name] ?? [];
    if (count($writes)!==1 || !$writes[0] instanceof \PhpParser\Node\Expr\Assign) return null;
    $assignment=$writes[0];$statement=$assignment->getAttribute('parent');
    if (!$statement instanceof \PhpParser\Node\Stmt\Expression || $statement->expr!==$assignment) return null;
    // The binding must be a preceding sibling in a block that contains this use.
    for ($child=$variable;$parent=$child->getAttribute('parent');$child=$parent) {
        if (property_exists($parent,'stmts') && is_array($parent->stmts)) {
            $before=array_search($statement,$parent->stmts,true);$after=array_search($child,$parent->stmts,true);
            if ($before!==false && $after!==false && $before<$after) return exactAuditDateReceiverClass($assignment->expr);
        }
        if ($parent===$function) break;
    }
    return null;
}

// ######## FIN AUDITORIA CALCULOS EXACTOS SAB ########
