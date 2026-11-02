const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default

for (const file of ['resources/js/views/tenant/cash/form.vue', 'modules/Restaurant/Resources/assets/js/views/cash/form.vue']) {
    test(`${file} displays opening validation errors without clearing the entered balances`, async () => {
        const component = compiler.parseComponent(fs.readFileSync(file, 'utf8'))
        assert.deepEqual(compiler.compile(component.template.content).errors, [])
        assert.match(component.template.content, /payment_method_type_id/)
        assert.match(component.template.content, /currency_type_id/)
        assert.match(component.template.content, /opening_balances\.USD/)
        const ast = parser.parse(component.script.content, {sourceType: 'module'})
        const methods = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties.find(node => node.key.name === 'methods').value
        const submit = vm.runInNewContext(`({${generate(methods.properties.find(node => node.key?.name === 'submit')).code}}).submit`)
        for (const nested of [false, true]) {
            const errors = {payment_method_type_id: ['Configure efectivo USD.']}
            const data = nested ? {message: 'Error', errors} : errors
            const form = {opening_balances: {VES: '100', USD: '5'}}
            const ctx = {recordId: null, form, errors: {}, openingCashCkeck: async () => false,
                $http: {post: () => Promise.reject({response: {status: 422, data}})}, $message: {error() {}}}
            await submit.call(ctx)
            await new Promise(resolve => setImmediate(resolve))
            assert.equal(ctx.errors.payment_method_type_id[0], 'Configure efectivo USD.')
            assert.deepEqual(form.opening_balances, {VES: '100', USD: '5'})
            assert.equal(ctx.loading_submit, false)
        }
    })
}
