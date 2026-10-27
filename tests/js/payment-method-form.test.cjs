const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const babel = require('@babel/core');
const compiler = require('vue-template-compiler');

// ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
const source = fs.readFileSync('modules/Sale/Resources/assets/js/views/payment_method_types/form.vue', 'utf8');
const parsed = compiler.parseComponent(source);
const exported = {};
vm.runInNewContext(babel.transformSync(parsed.script.content, {
    configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs'],
}).code, {exports: exported});
const component = exported.default;

test('duplicate code displays the server error and keeps the form open', async () => {
    for (const data of [{id: ['El código ya existe']}, {message: {id: ['El código ya existe']}}]) {
        let closed = false;
        const ctx = {
            recordId: null, resource: 'payment-method-types', form: {id: '01'}, errors: {},
            $http: {post: (url) => {
                assert.equal(url, '/payment-method-types');
                return Promise.reject({response: {status: 422, data}});
            }},
            $message: {error: () => assert.fail('expected an inline error')},
            close: () => { closed = true; },
        };
        component.methods.submit.call(ctx);
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(ctx.errors.id[0], 'El código ya existe');
        assert.equal(ctx.loading_submit, false);
        assert.equal(closed, false);
    }
});

test('editing sends PUT to the selected method', async () => {
    let saved = false;
    const ctx = {
        recordId: '27', resource: 'payment-method-types', form: {id: '27'},
        $http: {put: (url) => {
            assert.equal(url, '/payment-method-types/27');
            return Promise.resolve({data: {success: true}});
        }},
        $message: {success() {}}, $eventHub: {$emit() {}}, close: () => { saved = true; },
    };
    component.methods.submit.call(ctx);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(saved, true);
    assert.equal(ctx.loading_submit, false);
});

test('payment method form template compiles', () => {
    assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
});
// ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
