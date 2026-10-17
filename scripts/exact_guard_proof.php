<?php
// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
/** Only nonnumeric equality/type guards are proven here, never monetary comparisons. */
function exactAuditGuardProof(\PhpParser\Node $node): ?string
{
    if (!$node instanceof \PhpParser\Node\Expr\BinaryOp) return null;
    $strict = $node instanceof \PhpParser\Node\Expr\BinaryOp\Identical || $node instanceof \PhpParser\Node\Expr\BinaryOp\NotIdentical;
    $equal = $strict || $node instanceof \PhpParser\Node\Expr\BinaryOp\Equal || $node instanceof \PhpParser\Node\Expr\BinaryOp\NotEqual;
    if (!$equal) return null;
    foreach ([$node->left, $node->right] as $operand) {
        if ($operand instanceof \PhpParser\Node\Scalar\String_ && !is_numeric($operand->value) && $operand->value !== '') return 'nonnumeric_string_equality';
        if ($strict && $operand instanceof \PhpParser\Node\Expr\ConstFetch && in_array(strtolower($operand->name->toString()), ['null','true','false'], true)) return 'strict_type_guard';
    }
    return null;
}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
