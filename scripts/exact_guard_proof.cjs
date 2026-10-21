// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
module.exports = function guardProof(node) {
    if (node.type !== 'BinaryExpression' || !['===','!==','==','!='].includes(node.operator)) return null
    for (const operand of [node.left, node.right]) {
        if (['===','!=='].includes(node.operator) && operand.type === 'StringLiteral' && operand.value.trim() !== '' && !/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/.test(operand.value.trim())) return 'nonnumeric_string_equality'
        // Abstract equality against nonnumeric text cannot compare numeric amounts:
        // string/number coercion yields NaN. Hex, Infinity and whitespace are excluded.
        if (['==','!='].includes(node.operator) && operand.type === 'StringLiteral'
            && operand.value.trim() !== '' && Number.isNaN(Number(operand.value))) return 'loose_nonnumeric_string_equality'
        if (['===','!=='].includes(node.operator) && ['NullLiteral','BooleanLiteral'].includes(operand.type)) return 'strict_type_guard'
        // JavaScript abstract equality with literal null checks presence, without
        // converting the other operand to a numeric amount. Nested operands remain audited.
        if (['==','!='].includes(node.operator) && operand.type === 'NullLiteral') return 'nullish_presence_guard'
    }
    return null
}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
