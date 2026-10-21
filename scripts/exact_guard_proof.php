<?php
// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
/** Structural proofs apply to individual expressions, never their nested operands. */
function exactAuditGuardProof(\PhpParser\Node $node): ?string
{
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
        if ($strict && $operand instanceof \PhpParser\Node\Expr\ConstFetch && in_array(strtolower($operand->name->toString()), ['null','true','false'], true)) return 'strict_type_guard';
    }
    return null;
}

/** Require a resolved factory/new expression; a variable or a method name alone proves nothing. */
function exactAuditKnownDateReceiver(\PhpParser\Node $node): bool
{
    return exactAuditDateReceiverClass($node) !== null;
}

function exactAuditDateReceiverClass(\PhpParser\Node $node): ?string
{
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
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
