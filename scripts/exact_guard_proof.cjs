// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
module.exports = function guardProof(node) {
    if (node.type !== 'BinaryExpression' || !['===','!==','==','!='].includes(node.operator)) return null
    for (const operand of [node.left, node.right]) {
        if (['===','!=='].includes(node.operator) && operand.type === 'StringLiteral' && operand.value.trim() !== '' && !/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/.test(operand.value.trim())) return 'nonnumeric_string_equality'
        if (['===','!=='].includes(node.operator) && ['NullLiteral','BooleanLiteral'].includes(operand.type)) return 'strict_type_guard'
    }
    return null
}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
