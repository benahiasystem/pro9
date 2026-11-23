const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const {test} = require('node:test')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')

// ######## INICIO PREFERENCIAS DE TOTALES VENEZUELA ########
function methodFromVue(file, name, globals = {}) {
    const source = compiler.parseComponent(fs.readFileSync(file, 'utf8')).script.content
    const ast = parser.parse(source, {sourceType: 'module', plugins: ['objectRestSpread']})
    const exported = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const methods = exported.properties.find(node => node.key.name === 'methods').value
    const method = methods.properties.find(node => node.key && node.key.name === name)
    return vm.runInNewContext(`({${source.slice(method.start, method.end)}}).${name}`, globals)
}

const components = [
    'modules/Suscription/Resources/assets/js/payment_receipt/index.vue',
    'modules/FullSuscription/Resources/assets/js/payment_receipt/index.vue',
    'modules/Purchase/Resources/assets/js/views/fixed_asset_purchases/index.vue',
]

for (const file of components) {
    test(`${file}: restaura preferencias sin recuperar títulos antiguos`, () => {
        let saved = JSON.stringify({total_taxed: {title: 'T.Gravado', visible: false, order: 7}, retired: {visible: true}})
        const load = methodFromVue(file, 'loadColumnVisibility', {localStorage: {getItem: () => saved}})
        const context = {columns: {total_taxed: {title: 'Base imponible', visible: true, order: 1}, total_exonerated: {title: 'Total exento', visible: true}}}
        load.call(context)
        assert.deepEqual(context.columns.total_taxed, {title: 'Base imponible', visible: false, order: 7})
        assert.equal(context.columns.total_exonerated.title, 'Total exento')
        assert.equal(context.columns.retired, undefined)
        saved = '{invalid json'
        assert.doesNotThrow(() => load.call(context))
        assert.equal(context.columns.total_taxed.title, 'Base imponible')
    })
}

test('reporte de documentos conserva títulos actuales y preferencias guardadas', async () => {
    const load = methodFromVue('modules/Report/Resources/assets/js/views/documents/index.vue', 'getColumnsToShow')
    const context = {
        columns: {total_taxed: {title: 'Base imponible', visible: true, order: 1}},
        $http: {post: () => Promise.resolve({data: {columns: {total_taxed: {title: 'T.Gravado', visible: false, order: 4}}}})},
    }
    load.call(context)
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(context.columns.total_taxed.title, 'Base imponible')
    assert.equal(context.columns.total_taxed.visible, false)
    assert.equal(context.columns.total_taxed.order, 4)
})
// ######## FIN PREFERENCIAS DE TOTALES VENEZUELA ########
