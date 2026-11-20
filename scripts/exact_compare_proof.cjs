const { existsSync } = require('node:fs')
const { dirname, resolve } = require('node:path')
const traverse = require('@babel/traverse').default

const orderTests = new Set(['<', '<=', '>', '>=', '===', '!=='])
const exactFactories = new Set(['exactAmount', 'exactValue'])
const exactOperations = new Set(['plus', 'minus', 'times', 'dividedBy'])
const readOnlyMethods = new Set([...exactOperations, 'compare', 'fraction', 'decimal', 'final'])

function isSignLiteral(node) {
    if (node.type === 'NumericLiteral') return ['0', '1'].includes(node.extra?.raw)
    return node.type === 'UnaryExpression' && node.operator === '-'
        && node.argument.type === 'NumericLiteral' && node.argument.extra?.raw === '1'
}

function isImportedFactory(identifier, scope, sourceFile, exactModule) {
    const binding = scope.getBinding(identifier.name)
    if (!binding?.constant || !binding.path.isImportSpecifier()) return false
    const { node: specifier, parent: declaration } = binding.path
    if (!exactFactories.has(specifier.imported.name) || specifier.importKind === 'type' || declaration.importKind === 'type') return false
    const source = declaration.source.value
    if (!/^\.\.?\//.test(source)) return false
    const resolved = resolve(dirname(sourceFile), source)
    return resolved === exactModule
        || (resolved + '.js' === exactModule && !existsSync(resolved) && !existsSync(resolved + '.mjs'))
}

function onlyReadOnlyUses(binding) {
    return binding.referencePaths.every(reference => {
        const member = reference.parentPath
        if (!member?.isMemberExpression() || member.node.object !== reference.node || member.node.computed
            || !readOnlyMethods.has(member.node.property.name)) return false
        const call = member.parentPath
        return call?.isCallExpression() && call.node.callee === member.node
    })
}

function isExactReceiver(node, scope, sourceFile, exactModule, checkedBindings = new Set()) {
    if (node.type === 'Identifier') {
        const binding = scope.getBinding(node.name)
        if (!binding?.constant || binding.kind !== 'const' || !binding.path.isVariableDeclarator()
            || binding.path.node.id.type !== 'Identifier' || !binding.path.node.init || checkedBindings.has(binding)
            || binding.path.parentPath.parentPath.isExportNamedDeclaration() || !onlyReadOnlyUses(binding)) return false
        checkedBindings.add(binding)
        return isExactReceiver(binding.path.node.init, binding.path.scope, sourceFile, exactModule, checkedBindings)
    }
    if (node.type !== 'CallExpression' || node.arguments.some(argument => argument.type === 'SpreadElement')) return false
    const method = node.callee
    if (method.type === 'Identifier') return isImportedFactory(method, scope, sourceFile, exactModule)
    return method.type === 'MemberExpression' && !method.computed && exactOperations.has(method.property.name)
        && node.arguments.length === 1 && isExactReceiver(method.object, scope, sourceFile, exactModule, checkedBindings)
}

function isExactComparison(node, scope, sourceFile, exactModule) {
    if (node.type !== 'CallExpression' || node.arguments.length !== 1 || node.arguments[0].type === 'SpreadElement') return false
    const method = node.callee
    return method.type === 'MemberExpression' && !method.computed && method.property.name === 'compare'
        && isExactReceiver(method.object, scope, sourceFile, exactModule)
}

/** Prove the sign of a rational comparison, including local bindings with read-only uses. */
module.exports = function exactComparisonNodes(ast, sourceFile, exactModule) {
    const verified = new Set()
    let dynamicScope = false
    traverse(ast, {
        WithStatement() { dynamicScope = true },
        CallExpression({ node }) {
            if (node.callee.type === 'Identifier' && node.callee.name === 'eval') dynamicScope = true
        },
        BinaryExpression({ node, scope }) {
            if (!orderTests.has(node.operator)) return
            const { left, right } = node
            if ((isSignLiteral(left) && isExactComparison(right, scope, sourceFile, exactModule))
                || (isSignLiteral(right) && isExactComparison(left, scope, sourceFile, exactModule))) verified.add(node)
        },
    })
    return dynamicScope ? new Set() : verified
}
