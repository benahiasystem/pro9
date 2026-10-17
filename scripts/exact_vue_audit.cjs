// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
const compiler = require('vue-template-compiler')

/** Parse executable Vue bindings with Vue's own AST, including filters and branches. */
module.exports = function auditVueTemplate(source, file, base, hooks) {
    const compiled = compiler.compile(source, {outputSourceRange: true})
    const visited = new Set()
    const offset = node => base + source.slice(0, node.start || 0).split('\n').length - 1
    function walk(node) {
        if (!node || visited.has(node)) return
        visited.add(node)
        if (node.type === 2) hooks.analyze(node.expression, file, offset(node), '{template}', true)
        if (node.type === 1) {
            const bound = [...(node.attrs || []), ...(node.props || []), ...(node.dynamicAttrs || [])]
            for (const attr of Object.values(node.rawAttrsMap || Object.fromEntries((node.attrsList || []).map(attr=>[attr.name, attr])))) {
                const name = attr.name
                if (name.startsWith('v-model.') && name.split('.').includes('number')) hooks.finding(file, offset(attr) + 1, '{template}', 'numeric_model', attr.value)
                if (name === 'v-for') {
                    if (node.for) hooks.analyze(node.for, file, offset(attr), '{template}', true)
                    continue
                }
                const inlineEvent = /^on[a-z]+$/.test(name)
                if ((!/^(?:v-|:|@|#)/.test(name) && !inlineEvent) || name.startsWith('#') || name.startsWith('v-slot')) continue
                let value = attr.value
                if (name.startsWith(':') || name.startsWith('v-bind:')) {
                    const prop = name.replace(/^(?::|v-bind:)/, '').split('.')[0]
                    value = bound.find(binding => binding.name === prop)?.value || (prop === 'class' ? node.classBinding : prop === 'style' ? node.styleBinding : value) || value
                }
                if (!value || name === 'v-else' || name === 'v-pre' || name === 'v-once' || name === 'v-cloak') continue
                hooks.analyze(value, file, offset(attr), '{template}', !inlineEvent && !name.startsWith('@') && !name.startsWith('v-on:'))
            }
            if (node.tag === 'el-input-number' || (node.tag === 'input' && node.attrsMap?.type === 'number')) {
                hooks.finding(file, offset(node) + 1, '{template}', 'numeric_control', source.slice(node.start, source.indexOf('>', node.start) + 1))
            }
            for (const branch of node.ifConditions || []) walk(branch.block)
            for (const child of node.children || []) walk(child)
            for (const slot of Object.values(node.scopedSlots || {})) walk(slot)
        }
    }
    walk(compiled.ast)
    return compiled.errors || []
}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
